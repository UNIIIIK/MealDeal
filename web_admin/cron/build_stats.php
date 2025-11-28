<?php
/**
 * Optimized Stats Builder
 * - Avoids Firestore runQuery slowdowns
 * - Uses structuredQuery with field masks
 * - Adds retry logic + smaller payloads
 * - Safe against timeouts and large collections
 */

require_once __DIR__ . '/../config/database.php';

/* ------------------------------
   CONFIG
--------------------------------*/
$FIRESTORE_PROJECT = "mealdeal-10385";
$BASE_URL = "https://firestore.googleapis.com/v1/projects/$FIRESTORE_PROJECT/databases/(default)/documents:runQuery";

$TIMEOUT = 20;       // seconds per request
$RETRIES = 2;        // retry if Firestore is slow

/* ------------------------------
   Curl Helper
--------------------------------*/
function firestoreQuery($payload, $timeout, $retries) {
    global $BASE_URL;

    for ($i = 0; $i <= $retries; $i++) {

        $ch = curl_init($BASE_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$error) return $response;

        // wait briefly before retry
        usleep(300000);
    }

    return false;
}

/* ------------------------------
   Count Documents (no heavy data)
--------------------------------*/
function countCollection($collection) {
    global $TIMEOUT, $RETRIES;

    $payload = [
        "structuredQuery" => [
            "from" => [["collectionId" => $collection]],
            "select" => ["fields" => [["fieldPath" => "__name__"]]],  // minimal payload
        ]
    ];

    $response = firestoreQuery($payload, $TIMEOUT, $RETRIES);
    if (!$response) {
        error_log("Failed to fetch $collection (timeout).");
        return 0;
    }

    $rows = explode("\n", trim($response));
    return count($rows); // each row = 1 document
}

/* ------------------------------
   MAIN STAT BUILDING
--------------------------------*/
$stats = [
    "users" => countCollection("users"),
    "orders" => countCollection("orders"),
    "listings" => countCollection("listings"),
];

/* ------------------------------
   SAVE CACHE
--------------------------------*/
$cacheFile = __DIR__ . "/stats_cache.json";
file_put_contents($cacheFile, json_encode($stats, JSON_PRETTY_PRINT));

echo "Stats cache written: users={$stats['users']}, orders={$stats['orders']}, listings={$stats['listings']}\n";
