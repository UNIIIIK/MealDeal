<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

// Format Firestore timestamps safely across different representations
function formatTimestamp($value) {
    try {
        if ($value instanceof Google\Cloud\Core\Timestamp) {
            $dt = $value->get();
            if ($dt instanceof DateTimeInterface) {
                return $dt->format('Y-m-d H:i:s');
            }
        } elseif (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int)$value);
        } elseif (is_string($value)) {
            $t = strtotime($value);
            if ($t !== false) {
                return date('Y-m-d H:i:s', $t);
            }
        }
    } catch (Exception $e) {
        // fall through
    }
    return null;
}

try {
    $db = Database::getInstance();
    $listingsRef = $db->getCollection('listings');
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $query = $listingsRef;
    if ($status) {
        $query = $query->where('status', '=', $status);
    }
    
    $listings = $query->limit($limit)->documents();
    
    $listingList = [];
    foreach ($listings as $listing) {
        $listingData = $listing->data();
        $listingList[] = [
            'id' => $listing->id(),
            'title' => $listingData['title'] ?? 'Untitled',
            'description' => $listingData['description'] ?? '',
            'category' => $listingData['category'] ?? 'Other',
            'original_price' => $listingData['original_price'] ?? 0,
            'discounted_price' => $listingData['discounted_price'] ?? 0,
            'quantity' => $listingData['quantity'] ?? 0,
            'status' => $listingData['status'] ?? 'active',
            'provider_id' => $listingData['provider_id'] ?? '',
            'created_at' => isset($listingData['created_at']) ? formatTimestamp($listingData['created_at']) : null,
            'images' => $listingData['images'] ?? [],
            'location' => $listingData['location'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $listingList,
        'count' => count($listingList),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
