<?php
// Simple connection test to diagnose the issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Firebase Connection Test</h2>";

try {
    require_once 'config/database.php';
    echo "<p>✓ Database config loaded successfully</p>";
    
    $db = Database::getInstance();
    echo "<p>✓ Database instance created</p>";
    
    // Test a simple query with timeout
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(1)->documents();
    
    $count = 0;
    foreach ($users as $user) {
        $count++;
        if ($count >= 1) break; // Stop after first document
    }
    
    echo "<p>✓ Successfully connected to Firestore and retrieved data</p>";
    echo "<p>Found at least $count user document(s)</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h3>PHP Configuration</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Stack Size: " . ini_get('zend.max_allowed_stack_size') . "</p>";

echo "<h3>File Check</h3>";
$keyPath = __DIR__ . '/config/firebase-credentials.json';
echo "<p>Credentials file exists: " . (file_exists($keyPath) ? 'Yes' : 'No') . "</p>";
if (file_exists($keyPath)) {
    $keyData = json_decode(file_get_contents($keyPath), true);
    echo "<p>Valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? 'Yes' : 'No - ' . json_last_error_msg()) . "</p>";
    if ($keyData && isset($keyData['project_id'])) {
        echo "<p>Project ID in file: " . htmlspecialchars($keyData['project_id']) . "</p>";
    }
}
?>