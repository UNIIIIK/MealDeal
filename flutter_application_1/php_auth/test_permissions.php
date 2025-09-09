<?php
header('Content-Type: text/plain');

echo "=== PHP Environment Test ===\n";

echo "PHP Version: " . phpversion() . "\n\n";

// Check if file exists
$credentialsFile = __DIR__ . '/firebase-credentials.json';
echo "Checking file: $credentialsFile\n";

if (file_exists($credentialsFile)) {
    echo "✅ File exists\n";
    
    // Check if file is readable
    echo "Is readable: " . (is_readable($credentialsFile) ? '✅ Yes' : '❌ No') . "\n";
    
    // Get file permissions
    $perms = fileperms($credentialsFile);
    echo "Permissions: " . substr(sprintf('%o', $perms), -4) . "\n";
    
    // Try to read the file
    $content = @file_get_contents($credentialsFile);
    if ($content === false) {
        echo "❌ Could not read file. Last error: " . error_get_last()['message'] . "\n";
    } else {
        echo "✅ File read successfully\n";
        // Don't output the actual content for security
        echo "File size: " . strlen($content) . " bytes\n";
    }
} else {
    echo "❌ File does not exist\n";
}

echo "\n=== PHP Extensions ===\n";
$requiredExtensions = ['json', 'openssl', 'curl'];
foreach ($requiredExtensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅ Loaded' : '❌ Not loaded') . "\n";
}

echo "\n=== Test Complete ===\n";
?>
