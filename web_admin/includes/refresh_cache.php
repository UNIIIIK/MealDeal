<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/cache.php';

// Refresh dashboard cache
$dashboard = getDashboardCache();

// Optional: log success
file_put_contents(__DIR__ . '/../logs/cache_refresh.log', 
    "[".date('Y-m-d H:i:s')."] Dashboard cache refreshed successfully.\n", 
    FILE_APPEND
);

echo "Dashboard cache refreshed.\n";
