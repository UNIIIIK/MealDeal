<?php
// Simple test to verify basic functionality
require_once 'config/database.php';

echo "Testing basic Firestore connection...\n";

try {
    $db = Database::getInstance()->getFirestore();
    echo "âœ… Database connection established\n";
    
    // Test a simple query
    $usersRef = $db->collection('users');
    $users = $usersRef->limit(1)->documents();
    
    $count = 0;
    foreach ($users as $user) {
        $count++;
        $userData = $user->data();
        echo "âœ… Found user: " . ($userData['name'] ?? 'Unknown') . "\n";
        break; // Just get one user
    }
    
    echo "âœ… Successfully fetched $count user(s)\n";
    echo "âœ… Basic functionality is working!\n";
    
} catch (Exception $e) {
    echo "âŒ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>

