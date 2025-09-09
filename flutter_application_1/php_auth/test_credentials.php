<?php
// Simple test to verify credentials file can be read
$file = __DIR__ . '/firebase-credentials.json';

echo "Testing file: $file\n";

if (file_exists($file)) {
    echo "✅ File exists\n";
    
    $content = file_get_contents($file);
    if ($content === false) {
        echo "❌ Could not read file. Error: " . error_get_last()['message'] . "\n";
    } else {
        echo "✅ File read successfully (" . strlen($content) . " bytes)\n";
        // Verify it's valid JSON
        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Valid JSON\n";
            echo "Project ID: " . ($json['project_id'] ?? 'Not found') . "\n";
        } else {
            echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
        }
    }
} else {
    echo "❌ File does not exist\n";
    echo "Current directory: " . __DIR__ . "\n";
    echo "Files in directory:\n";
    foreach (scandir(__DIR__) as $item) {
        echo "- $item\n";
    }
}
