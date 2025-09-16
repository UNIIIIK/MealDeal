<?php
session_start();
require_once 'config/database.php';

echo "<h1>Debug Data Structure</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h2>Users Data</h2>";
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(5)->documents();
    
    foreach ($users as $user) {
        $userData = $user->data();
        echo "<h3>User: " . htmlspecialchars($userData['name'] ?? 'Unknown') . "</h3>";
        echo "<pre>" . htmlspecialchars(print_r($userData, true)) . "</pre>";
        break; // Just show first user
    }
    
    echo "<h2>Cart/Orders Data</h2>";
    $cartsRef = $db->getCollection('cart');
    $carts = $cartsRef->limit(3)->documents();
    
    foreach ($carts as $cart) {
        $cartData = $cart->data();
        echo "<h3>Cart ID: " . $cart->id() . "</h3>";
        echo "<pre>" . htmlspecialchars(print_r($cartData, true)) . "</pre>";
        break; // Just show first cart
    }
    
    echo "<h2>Listings Data</h2>";
    $listingsRef = $db->getCollection('listings');
    $listings = $listingsRef->limit(3)->documents();
    
    foreach ($listings as $listing) {
        $listingData = $listing->data();
        echo "<h3>Listing: " . htmlspecialchars($listingData['name'] ?? 'Unknown') . "</h3>";
        echo "<pre>" . htmlspecialchars(print_r($listingData, true)) . "</pre>";
        break; // Just show first listing
    }
    
    echo "<h2>Reports Data</h2>";
    $reportsRef = $db->getCollection('reports');
    $reports = $reportsRef->limit(3)->documents();
    
    $reportCount = 0;
    foreach ($reports as $report) {
        $reportData = $report->data();
        echo "<h3>Report ID: " . $report->id() . "</h3>";
        echo "<pre>" . htmlspecialchars(print_r($reportData, true)) . "</pre>";
        $reportCount++;
    }
    
    if ($reportCount === 0) {
        echo "<p>No reports found in database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='index.php'>← Back to Dashboard</a></p>";
?>