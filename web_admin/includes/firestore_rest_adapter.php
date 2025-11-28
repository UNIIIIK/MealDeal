<?php

namespace MealDeal\Admin\Firestore;

use DateTimeInterface;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Google\Cloud\Core\Timestamp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * Minimal Firestore client that talks to the public REST API.
 *
 * It implements only the subset of features the admin panel needs:
 *  - collection queries with where/orderBy/limit
 *  - document snapshots
 *  - add/update helpers for simple arrays
 *
 * Improvements:
 *  - Accepts injected httpClient for easier timeout control
 *  - Adds retry logic to requests
 *  - Uses connect_timeout & read_timeout to avoid long hangs
 */
class RestFirestoreClient
{
    private const DEFAULT_TIMEOUT = 10;

    private string $projectId;
    private string $databaseId;
    private ServiceAccountCredentials $credentials;
    private Client $httpClient;
    private $httpHandler;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct(array $config)
    {
        $this->projectId = $config['projectId'] ?? '';
        $keyFilePath = $config['keyFilePath'] ?? '';

        if (!$this->projectId || !$keyFilePath) {
            throw new RuntimeException('RestFirestoreClient requires projectId and keyFilePath.');
        }

        $this->databaseId = $config['database'] ?? '(default)';

        $this->credentials = new ServiceAccountCredentials(
            [
                'https://www.googleapis.com/auth/cloud-platform',
                'https://www.googleapis.com/auth/datastore'
            ],
            $keyFilePath
        );

        $this->httpHandler = HttpHandlerFactory::build();

        // Allow injection of a pre-configured Guzzle client (for timeouts)
        if (!empty($config['httpClient']) && $config['httpClient'] instanceof Client) {
            $this->httpClient = $config['httpClient'];
        } else {
            // Build a safe default Guzzle client with aggressive timeouts
            $timeout = isset($config['timeout']) ? (int)$config['timeout'] : self::DEFAULT_TIMEOUT;
            $connectTimeout = $config['connect_timeout'] ?? 3;
            $readTimeout = $config['read_timeout'] ?? 5;

            $this->httpClient = new Client([
                'base_uri'        => 'https://firestore.googleapis.com/v1/',
                'timeout'         => max(3, $timeout),
                'connect_timeout' => max(1, (int)$connectTimeout),
                // read_timeout is supported by Guzzle 7 as an option when using stream context,
                // but set here for explicitness. Many transports will honour connect_timeout & timeout.
                'read_timeout'    => max(1, (int)$readTimeout),
                'http_errors'     => true,
            ]);
        }
    }

    public function collection(string $name): RestCollectionQuery
    {
        return new RestCollectionQuery($this, $name);
    }

    public function addDocument(string $collection, array $data): string
    {
        $payload = [
            'fields' => $this->encodeFields($data),
        ];

        $uri = sprintf('%s/%s', $this->documentsRoot(), rawurlencode($collection));
        $response = $this->requestJson('POST', $uri, ['json' => $payload]);

        return $this->extractDocumentId($response['name'] ?? '');
    }

    public function updateDocument(string $collection, string $documentId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $maskQuery = [];
        foreach (array_keys($data) as $field) {
            $maskQuery[] = 'updateMask.fieldPaths=' . rawurlencode($field);
        }

        $uri = sprintf(
            '%s/%s/%s?%s',
            $this->documentsRoot(),
            rawurlencode($collection),
            rawurlencode($documentId),
            implode('&', $maskQuery)
        );

        $payload = [
            'fields' => $this->encodeFields($data),
        ];

        $this->requestJson('PATCH', $uri, ['json' => $payload]);
        return true;
    }

    public function getDocument(string $collection, string $documentId): RestDocumentSnapshot
    {
        $uri = sprintf('%s/%s/%s', $this->documentsRoot(), rawurlencode($collection), rawurlencode($documentId));

        try {
            $response = $this->requestJson('GET', $uri);
            return $this->snapshotFromDocument($response);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return new RestDocumentSnapshot($documentId, [], false);
            }

            throw $e;
        }
    }

    /**
     * Execute a structured query and return document snapshots.
     *
     * @param string $collection
     * @param array<int, array{field:string,value:mixed}> $filters
     * @param array<int, array{field:string,direction:string}> $orders
     * @param int|null $limit
     * @param array|null $select
     * @return RestDocumentSnapshot[]
     */
    public function runQuery(string $collection, array $filters, array $orders, ?int $limit, ?array $select = null): array
    {
        $structuredQuery = [
            'from' => [
                ['collectionId' => $collection]
            ],
        ];

        if ($select) {
            $structuredQuery['select'] = [
                'fields' => array_map(
                    fn(string $field) => ['fieldPath' => $field],
                    $select
                ),
            ];
        }

        if ($filters) {
            $structuredQuery['where'] = $this->buildWhereClause($filters);
        }

        if ($orders) {
            $structuredQuery['orderBy'] = array_map(function ($order) {
                return [
                    'field' => ['fieldPath' => $order['field']],
                    'direction' => strtoupper($order['direction']) === 'DESC' ? 'DESCENDING' : 'ASCENDING',
                ];
            }, $orders);
        }

        if ($limit !== null) {
            $structuredQuery['limit'] = max(1, (int) $limit);
        }

        $payload = [
            'parent' => $this->documentsRoot(),
            'structuredQuery' => $structuredQuery,
        ];

       $uri = $this->documentsRoot() . ':runQuery';


        $responseLines = $this->requestJsonLines('POST', $uri, ['json' => $payload]);

        $documents = [];
        foreach ($responseLines as $line) {
            if (!isset($line['document'])) {
                continue;
            }
            $documents[] = $this->snapshotFromDocument($line['document']);
        }

        return $documents;
    }

    private function documentsRoot(): string
{
    return sprintf(
        'https://firestore.googleapis.com/v1/projects/%s/databases/%s/documents',
        $this->projectId,
        $this->databaseId
    );
}


    /**
     * requestJson with retry logic (small number of retries)
     *
     * @throws RuntimeException
     */
    private function requestJson(string $method, string $uri, array $options = []): array
    {
        $maxAttempts = 2;
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                // Do not prepend anything; the URI should already be absolute
                $response = $this->httpClient->request($method, $uri, $this->applyRequestOptions($options));
                return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            } catch (RequestException $e) {
                if ($attempt < $maxAttempts) {
                    usleep(150000); // 150ms backoff
                    continue;
                }
                $message = $e->getResponse()
                    ? (string) $e->getResponse()->getBody()
                    : $e->getMessage();
                throw new RuntimeException("Firestore REST request failed ({$method} {$uri}): {$message}");
            }
        }
    }

    /**
     * Firestore runQuery returns newline-delimited JSON.
     *
     * @return array<int, array>
     * @throws RuntimeException
     */
    private function requestJsonLines(string $method, string $uri, array $options = []): array
    {
        $maxAttempts = 2;
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                // Do not prepend anything; the URI should already be absolute
                $response = $this->httpClient->request($method, $uri, $this->applyRequestOptions($options));
                $body = trim((string) $response->getBody());
                if ($body === '') {
                    return [];
                }

                if (str_starts_with($body, '[')) {
                    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                }

                $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
                $decoded = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $decoded[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                }

                return $decoded;
            } catch (RequestException $e) {
                if ($attempt < $maxAttempts) {
                    usleep(150000);
                    continue;
                }
                $message = $e->getResponse()
                    ? (string) $e->getResponse()->getBody()
                    : $e->getMessage();
                throw new RuntimeException("Firestore REST stream failed ({$method} {$uri}): {$message}");
            }
        }
    }

    private function applyRequestOptions(array $options): array
    {
        // Merge auth headers
        $options['headers'] = array_merge($this->authHeaders(), $options['headers'] ?? []);
        return $options;
    }

    private function authHeaders(): array
    {
        if ($this->accessToken && $this->tokenExpiry - 60 > time()) {
            return $this->defaultHeaders($this->accessToken);
        }

        $tokenData = $this->credentials->fetchAuthToken($this->httpHandler);
        if (!isset($tokenData['access_token'])) {
            throw new RuntimeException('Unable to fetch Firestore access token.');
        }

        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = isset($tokenData['expires_at'])
            ? (int) $tokenData['expires_at']
            : time() + 3600;

        return $this->defaultHeaders($this->accessToken);
    }

    private function defaultHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function buildWhereClause(array $filters): array
    {
        $clauses = array_map(function ($filter) {
            return [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $filter['field']],
                    'op' => 'EQUAL',
                    'value' => $this->encodeValue($filter['value']),
                ]
            ];
        }, $filters);

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        return [
            'compositeFilter' => [
                'op' => 'AND',
                'filters' => $clauses,
            ]
        ];
    }

    private function snapshotFromDocument(array $document): RestDocumentSnapshot
    {
        $docId = $this->extractDocumentId($document['name'] ?? '');
        $fields = $document['fields'] ?? [];

        return new RestDocumentSnapshot($docId, $this->decodeFields($fields), true);
    }

    private function extractDocumentId(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $parts = explode('/', $name);
        return end($parts);
    }

    private function decodeFields(array $fields): array
    {
        $decoded = [];
        foreach ($fields as $key => $value) {
            $decoded[$key] = $this->decodeValue($value);
        }
        return $decoded;
    }

    private function decodeValue(array $value): mixed
    {
        if (isset($value['stringValue'])) {
            return $value['stringValue'];
        }
        if (isset($value['integerValue'])) {
            return (int) $value['integerValue'];
        }
        if (isset($value['doubleValue'])) {
            return (float) $value['doubleValue'];
        }
        if (isset($value['booleanValue'])) {
            return (bool) $value['booleanValue'];
        }
        if (isset($value['timestampValue'])) {
            try {
                $dt = new \DateTimeImmutable($value['timestampValue']);
            } catch (\Exception $e) {
                $dt = new \DateTimeImmutable('@' . strtotime($value['timestampValue']));
            }
            return new Timestamp($dt);
        }
        if (isset($value['mapValue'])) {
            return $this->decodeFields($value['mapValue']['fields'] ?? []);
        }
        if (isset($value['arrayValue'])) {
            $items = $value['arrayValue']['values'] ?? [];
            return array_map(fn($item) => $this->decodeValue($item), $items);
        }
        if (isset($value['nullValue'])) {
            return null;
        }

        return $value;
    }

    private function encodeFields(array $data): array
    {
        $encoded = [];
        foreach ($data as $key => $value) {
            $encoded[$key] = $this->encodeValue($value);
        }
        return $encoded;
    }

    private function encodeValue(mixed $value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }

        if ($value instanceof Timestamp) {
            return ['timestampValue' => $value->formatAsString()];
        }

        if ($value instanceof DateTimeInterface) {
            return ['timestampValue' => $value->format('c')];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                return [
                    'mapValue' => [
                        'fields' => $this->encodeFields($value)
                    ]
                ];
            }

            return [
                'arrayValue' => [
                    'values' => array_map(fn($item) => $this->encodeValue($item), $value)
                ]
            ];
        }

        return ['stringValue' => (string) $value];
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

class RestCollectionQuery
{
    private RestFirestoreClient $client;
    private string $collection;
    /** @var array<int, array{field:string,value:mixed}> */
    private array $filters;
    /** @var array<int, array{field:string,direction:string}> */
    private array $orders;
    private ?int $limit;
    private ?array $select;

    public function __construct(RestFirestoreClient $client, string $collection, array $filters = [], array $orders = [], ?int $limit = null, ?array $select = null)
    {
        $this->client = $client;
        $this->collection = $collection;
        $this->filters = $filters;
        $this->orders = $orders;
        $this->limit = $limit;
        $this->select = $select;
    }

    public function where(string $field, string $operator, mixed $value): self
    {
        if ($operator !== '=') {
            throw new RuntimeException('Only equality filters are supported in REST fallback.');
        }

        $filters = $this->filters;
        $filters[] = ['field' => $field, 'value' => $value];

        return new self($this->client, $this->collection, $filters, $this->orders, $this->limit, $this->select);
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $orders = $this->orders;
        $orders[] = [
            'field' => $field,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return new self($this->client, $this->collection, $this->filters, $orders, $this->limit, $this->select);
    }

    public function limit(int $limit): self
    {
        return new self($this->client, $this->collection, $this->filters, $this->orders, max(1, $limit), $this->select);
    }

    public function select(array $fields): self
    {
        $sanitized = array_values(array_filter(array_map('strval', $fields), fn($field) => $field !== ''));
        return new self($this->client, $this->collection, $this->filters, $this->orders, $this->limit, $sanitized ?: null);
    }

    public function documents(): \Iterator
    {
        $documents = $this->client->runQuery($this->collection, $this->filters, $this->orders, $this->limit, $this->select);
        return new \ArrayIterator($documents);
    }

    public function add(array $data): string
    {
        return $this->client->addDocument($this->collection, $data);
    }

    public function document(string $documentId): RestDocumentReference
    {
        return new RestDocumentReference($this->client, $this->collection, $documentId);
    }
}

class RestDocumentReference
{
    private RestFirestoreClient $client;
    private string $collection;
    private string $documentId;

    public function __construct(RestFirestoreClient $client, string $collection, string $documentId)
    {
        $this->client = $client;
        $this->collection = $collection;
        $this->documentId = $documentId;
    }

    public function snapshot(): RestDocumentSnapshot
    {
        return $this->client->getDocument($this->collection, $this->documentId);
    }
}

class RestDocumentSnapshot
{
    private string $id;
    private array $data;
    private bool $exists;

    public function __construct(string $id, array $data, bool $exists)
    {
        $this->id = $id;
        $this->data = $data;
        $this->exists = $exists;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function exists(): bool
    {
        return $this->exists;
    }
}
