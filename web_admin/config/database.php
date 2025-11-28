<?php
// Firebase/Firestore Configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/firestore_rest_adapter.php';

use Google\Cloud\Firestore\FirestoreClient;
use MealDeal\Admin\Firestore\RestFirestoreClient;
use GuzzleHttp\Client as GuzzleClient;

class Database {
    private static $instance = null;
    private $firestore;

    private function __construct() {
        try {
            // Path to Firebase service account
            $keyPath = __DIR__ . '/firebase-credentials.json';
            if (!file_exists($keyPath)) {
                throw new Exception('Firebase credentials file not found at: ' . $keyPath);
            }

            // Validate credentials JSON
            $keyData = json_decode(file_get_contents($keyPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in credentials file: ' . json_last_error_msg());
            }

            $transportOverride = getenv('MEALDEAL_FIRESTORE_TRANSPORT');
            $preferGrpc = PHP_OS_FAMILY !== 'Windows';
            $preferRest = false;
            if ($transportOverride) {
                $transportOverride = strtolower($transportOverride);
                if ($transportOverride === 'rest') {
                    $preferGrpc = false;
                    $preferRest = true;
                } elseif ($transportOverride === 'grpc') {
                    $preferGrpc = true;
                    $preferRest = false;
                }
            }

            $timeoutOverride = getenv('MEALDEAL_FIRESTORE_TIMEOUT');
            $timeoutSeconds = is_numeric($timeoutOverride)
                ? max(5, (int)$timeoutOverride)
                : 10;

            // Build a shared Guzzle client to inject into RestFirestoreClient
            $guzzleOptions = [
                'timeout' => $timeoutSeconds,
                'connect_timeout' => 3,
                // read_timeout set for explicitness (some transports honor it)
                'read_timeout' => 5,
            ];

            $guzzleClient = new GuzzleClient($guzzleOptions);

            $transportErrors = [];

            // Try gRPC if desired and available
            if ($preferGrpc && !$preferRest) {
                try {
                    $this->firestore = new FirestoreClient([
                        'projectId' => 'mealdeal-10385',
                        'keyFilePath' => $keyPath,
                        'retries' => 0,
                        'timeout' => $timeoutSeconds
                    ]);
                    return;
                } catch (Exception $grpcException) {
                    $transportErrors[] = 'gRPC: ' . $grpcException->getMessage();
                    // fall back to REST
                }
            }

            try {
                // Use our REST adapter, inject the httpClient for robust timeouts
                $this->firestore = new RestFirestoreClient([
                    'projectId'   => 'mealdeal-10385',
                    'keyFilePath' => $keyPath,
                    'timeout'     => $timeoutSeconds,
                    'httpClient'  => $guzzleClient,
                    // optional explicit smaller timeouts passed through if needed
                    'connect_timeout' => 3,
                    'read_timeout'    => 5,
                ]);
            } catch (Exception $restException) {
                $transportErrors[] = 'REST: ' . $restException->getMessage();
                $details = implode('; ', $transportErrors);
                throw new Exception('Database connection failed: ' . $details);
            }
        } catch (Exception $e) {
            error_log('Firestore initialization failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getFirestore() {
        return $this->firestore;
    }
}

// Global Firestore client (not the wrapper itself)
$db = Database::getInstance()->getFirestore();
