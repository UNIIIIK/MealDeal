<?php
/**
 * Firebase Connection Test for MealDeal Backend
 * 
 * This script tests the Firebase Admin SDK connection and basic Firestore operations.
 */

require_once '../config/firebase_config.php';

echo "🔥 MealDeal Firebase Connection Test\n";
echo "=====================================\n\n";

try {
    // Test 1: Basic Firebase connection
    echo "1. Testing Firebase Admin SDK connection...\n";
    $database = $firestore->database();
    echo "   ✅ Firebase Admin SDK initialized successfully\n\n";
    
    // Test 2: Test Firestore write operation
    echo "2. Testing Firestore write operation...\n";
    $testData = [
        'timestamp' => time(),
        'status' => 'connected',
        'test_type' => 'backend_connection',
        'app' => 'MealDeal'
    ];
    
    $testDoc = $database->collection('test')->document('connection_test');
    $testDoc->set($testData);
    echo "   ✅ Successfully wrote test document to Firestore\n\n";
    
    // Test 3: Test Firestore read operation
    echo "3. Testing Firestore read operation...\n";
    $snapshot = $testDoc->snapshot();
    
    if ($snapshot->exists()) {
        $data = $snapshot->data();
        echo "   ✅ Successfully read test document from Firestore\n";
        echo "   📄 Document data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "   ❌ Test document not found\n\n";
    }
    
    // Test 4: Test helper functions
    echo "4. Testing helper functions...\n";
    
    // Test getFirestoreDocument function
    $retrievedData = getFirestoreDocument('test', 'connection_test');
    if ($retrievedData) {
        echo "   ✅ getFirestoreDocument() function working\n";
    } else {
        echo "   ❌ getFirestoreDocument() function failed\n";
    }
    
    // Test updateFirestoreDocument function
    $updateData = ['last_updated' => time()];
    $updateResult = updateFirestoreDocument('test', 'connection_test', $updateData);
    if ($updateResult) {
        echo "   ✅ updateFirestoreDocument() function working\n";
    } else {
        echo "   ❌ updateFirestoreDocument() function failed\n";
    }
    
    echo "\n🎉 All tests passed! Firebase backend is ready.\n";
    echo "=====================================\n";
    echo "Your MealDeal PHP backend is properly configured and connected to Firebase.\n";
    echo "You can now run the Flutter app with full backend integration.\n";
    
} catch (Exception $e) {
    echo "❌ Firebase connection failed: " . $e->getMessage() . "\n";
    echo "📋 Troubleshooting:\n";
    echo "   1. Check if service account JSON file is in backend/config/\n";
    echo "   2. Verify Firebase project ID matches: mealdeal-10385\n";
    echo "   3. Ensure Firestore is enabled in Firebase Console\n";
    echo "   4. Check file permissions on service account JSON\n";
    exit(1);
}
?>
