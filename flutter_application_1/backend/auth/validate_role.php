<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['user_id']) || !isset($input['required_role'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: user_id, required_role'
    ]);
    exit();
}

$userId = $input['user_id'];
$requiredRole = $input['required_role'];

// Validate role value
$validRoles = ['food_provider', 'food_consumer'];
if (!in_array($requiredRole, $validRoles)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role. Must be food_provider or food_consumer.'
    ]);
    exit();
}

try {
    // Initialize Firebase Admin SDK (you'll need to configure this)
    // For now, we'll use Firestore REST API
    
    // Get user document from Firestore
    $userData = getUserFromFirestore($userId);
    
    if (!$userData) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Check if user has required role
    $userRole = $userData['role'] ?? null;
    
    if ($userRole !== $requiredRole) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => "Access denied. Required role: {$requiredRole}, user role: {$userRole}"
        ]);
        exit();
    }
    
    // Check if user is verified
    if (!($userData['verified'] ?? false)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'User email not verified'
        ]);
        exit();
    }
    
    // Role validation successful
    echo json_encode([
        'success' => true,
        'message' => 'Role validation successful',
        'data' => [
            'user_id' => $userId,
            'role' => $userRole,
            'verified' => true
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}

/**
 * Get user data from Firestore using REST API
 * In production, use Firebase Admin SDK
 */
function getUserFromFirestore($userId) {
    // Replace with your Firebase project ID
    $projectId = 'your-firebase-project-id';
    
    // Firestore REST API endpoint
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users/{$userId}";
    
    // You'll need to include proper authentication headers
    // For now, this is a placeholder implementation
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Content-Type: application/json',
                // Add your Firebase service account key authorization here
                // 'Authorization: Bearer ' . $accessToken
            ]
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['fields'])) {
        return null;
    }
    
    // Convert Firestore fields format to simple array
    $userData = [];
    foreach ($data['fields'] as $key => $value) {
        if (isset($value['stringValue'])) {
            $userData[$key] = $value['stringValue'];
        } elseif (isset($value['booleanValue'])) {
            $userData[$key] = $value['booleanValue'];
        } elseif (isset($value['integerValue'])) {
            $userData[$key] = (int)$value['integerValue'];
        }
        // Add more type conversions as needed
    }
    
    return $userData;
}

/**
 * Alternative implementation using Firebase Admin SDK
 * Uncomment and configure when you have the SDK installed
 */
/*
require_once 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

function getUserFromFirebaseAdmin($userId) {
    $serviceAccount = ServiceAccount::fromJsonFile('/path/to/service-account.json');
    $firebase = (new Factory)
        ->withServiceAccount($serviceAccount)
        ->create();
    
    $firestore = $firebase->createFirestore();
    $database = $firestore->database();
    
    try {
        $userDoc = $database->collection('users')->document($userId)->snapshot();
        
        if (!$userDoc->exists()) {
            return null;
        }
        
        return $userDoc->data();
    } catch (Exception $e) {
        throw new Exception('Failed to fetch user data: ' . $e->getMessage());
    }
}
*/
?>
