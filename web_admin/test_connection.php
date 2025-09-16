<?php
session_start();
require_once 'config/database.php';

echo "<h1>Database Connection Test</h1>";

try {
    echo "<p>Testing Firestore connection...</p>";
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database instance created successfully</p>";
    
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(1)->documents();
    
    $userCount = 0;
    foreach ($users as $user) {
        $userCount++;
        $userData = $user->data();
        echo "<p style='color: green;'>✓ Successfully queried users collection</p>";
        echo "<p>Sample user: " . htmlspecialchars($userData['name'] ?? 'Unknown') . "</p>";
        break;
    }
    
    if ($userCount === 0) {
        echo "<p style='color: orange;'>⚠ No users found in database</p>";
    }
    
    echo "<p style='color: green;'>✓ Database connection test completed successfully</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Error details: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}

echo "<p><a href='index.php'>← Back to Dashboard</a></p>";
?>