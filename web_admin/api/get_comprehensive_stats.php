<?php
//get_comprehensive_stats.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/data_functions.php';

if (!function_exists('formatTimestamp')) {
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
        } catch (Exception $e) {}
        return null;
    }
}

try {
    // Set a reasonable timeout for API calls
    set_time_limit(30);

    // Simple file-based micro-cache (60 seconds)
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'md_comprehensive_stats_cache.json';
    $cacheTtlSeconds = 60;
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtlSeconds) {
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            header('X-Cache: HIT');
            echo $cached;
            exit;
        }
    }

    // Load stats with better error handling
    $stats = [
        'users' => ['total_users' => 0, 'providers' => 0, 'consumers' => 0, 'verified_users' => 0, 'recent_signups' => 0],
        'listings' => ['active_listings' => 0, 'total_revenue' => 0],
        'orders' => ['total_food_saved' => 0, 'total_savings' => 0, 'total_orders' => 0, 'completed_orders' => 0, 'average_order_value' => 0],
        'reports' => ['pending_reports' => 0, 'total_reports' => 0, 'recent_reports' => []],
        'leaderboard' => ['providers' => [], 'consumers' => []],
        'top_providers' => []
    ];
    
    // Try to load each stat category individually with timeout protection
    try {
        $stats['users'] = getUserStats();
    } catch (Exception $e) {
        error_log("Error loading user stats: " . $e->getMessage());
    }
    
    try {
        $stats['listings'] = getListingStats();
    } catch (Exception $e) {
        error_log("Error loading listing stats: " . $e->getMessage());
    }
    
    try {
        $stats['orders'] = getOrderStats();
    } catch (Exception $e) {
        error_log("Error loading order stats: " . $e->getMessage());
    }
    
    try {
        $stats['reports'] = getReportStats();
    } catch (Exception $e) {
        error_log("Error loading report stats: " . $e->getMessage());
    }

    try {
        // Top providers (all‑time, Firestore aggregates)
        $stats['leaderboard']['providers'] = getTopProviders(10);
    } catch (Exception $e) {
        error_log("Error loading provider leaderboard: " . $e->getMessage());
        $stats['leaderboard']['providers'] = [];
    }

    try {
        // Top consumers (all‑time, Firestore aggregates)
        $stats['leaderboard']['consumers'] = getTopConsumers(10);
    } catch (Exception $e) {
        error_log("Error loading consumer leaderboard: " . $e->getMessage());
        $stats['leaderboard']['consumers'] = [];
    }
    
    $responseJson = json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Save to cache best-effort
    @file_put_contents($cacheFile, $responseJson);

    echo $responseJson;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
