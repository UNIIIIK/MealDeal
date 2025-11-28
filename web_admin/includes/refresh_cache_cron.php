<?php
// refresh_cache_cron.php
require_once __DIR__ . '/../config/database.php';

$cacheFile = __DIR__ . '/dashboard_cache.json';

function logMessage($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

try {
    global $db;

    $dashboard = [
        'total_users'     => 0,
        'active_listings' => 0,
        'pending_reports' => 0,
        'food_saved'      => 0,
        'recent_reports'  => [],
        'top_providers'   => [],
    ];

    // Users
    $users = $db->collection('users')->documents();
    $dashboard['total_users'] = iterator_count($users);

    // Active listings
    $listings = $db->collection('listings')
        ->where('status', '=', 'active')
        ->documents();
    $dashboard['active_listings'] = iterator_count($listings);

    // Pending reports
    $reports = $db->collection('reports')
        ->where('status', '=', 'pending')
        ->documents();
    $dashboard['pending_reports'] = iterator_count($reports);

    // Food saved
    $completedCarts = $db->collection('cart')
        ->where('status', '=', 'completed')
        ->documents();

    $totalFood = 0;
    foreach ($completedCarts as $cart) {
        if (!$cart->exists()) continue;
        $items = $cart->data()['items'] ?? [];
        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 0;
            $weight = $item['weight_per_unit'] ?? 0.5;
            $totalFood += $qty * $weight;
        }
    }
    $dashboard['food_saved'] = $totalFood;

    // Recent reports
    $recentReports = $db->collection('reports')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->documents();

    foreach ($recentReports as $report) {
        if (!$report->exists()) continue;
        $data = $report->data();
        $dashboard['recent_reports'][] = [
            'id'            => $report->id(),
            'type'          => $data['type'] ?? 'unknown',
            'status'        => $data['status'] ?? 'pending',
            'reporter_name' => $data['reporter_name'] ?? 'Anonymous',
            'created_at'    => $data['created_at'] ?? null,
            'description'   => $data['description'] ?? ''
        ];
    }

    // Save cache
    file_put_contents($cacheFile, json_encode($dashboard));

    logMessage("Dashboard cache refreshed successfully.");

} catch (Exception $e) {
    logMessage("Error refreshing dashboard cache: " . $e->getMessage());
}
