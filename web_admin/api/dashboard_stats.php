<?php
// api/dashboard_stats.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/stats.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getFirestore();
    $doc = $db->collection('stats')->document('global')->snapshot();

    $data = $doc->exists() ? $doc->data() : [];

    // Ensure all expected fields exist with defaults
    $stats = [
        'users_count'   => $data['users_count']   ?? 0,
        'posts_count'   => $data['posts_count']   ?? 0,
        'orders_count'  => $data['orders_count']  ?? 0,
        'revenue_total' => $data['revenue_total'] ?? 0,
    ];

    echo json_encode([
        'success' => true,
        'stats'   => $stats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
