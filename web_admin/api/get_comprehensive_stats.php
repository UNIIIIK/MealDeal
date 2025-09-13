<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/data_functions.php';

try {
    // Wrap with a short timeout using PCNTL if available (non-Windows). For Windows, rely on collection limits.
    // Fast-path: fetch only a subset to avoid long scans, with small limits
    $stats = [
        'users' => getUserStats(),
        'listings' => getListingStats(),
        'orders' => getOrderStats(),
        'reports' => getReportStats(),
        'top_providers' => []
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
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
