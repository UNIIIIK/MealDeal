<?php
// Simple test to verify basic functionality
require_once 'config/database.php';

echo "Testing basic Firestore connection...\n";

try {
    $db = Database::getInstance();
    echo "✅ Database connection established\n";
    
    // Test a simple query
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(1)->documents();
    
    $count = 0;
    foreach ($users as $user) {
        $count++;
        $userData = $user->data();
        echo "✅ Found user: " . ($userData['name'] ?? 'Unknown') . "\n";
        break; // Just get one user
    }
    
    echo "✅ Successfully fetched $count user(s)\n";
    echo "✅ Basic functionality is working!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
