<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../vendor/autoload.php';
use MealDeal\Auth\AuthHandler;

// Load configuration
$config = require __DIR__ . '/../config/config.php';
$authHandler = new AuthHandler($config);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'data' => null
];

try {
    // Validate input
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Required fields
    $required = ['email', 'password', 'name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Normalize role from client (supports 'food_provider'/'food_consumer' and 'provider'/'consumer')
    $rawRole = $input['role'] ?? 'consumer';
    $normalizedRole = in_array($rawRole, ['food_provider', 'provider'], true)
        ? 'food_provider'
        : (in_array($rawRole, ['food_consumer', 'consumer'], true) ? 'food_consumer' : 'food_consumer');

    // Prepare user data
    $userData = [
        'email' => $input['email'],
        'password' => $input['password'],
        'name' => $input['name'],
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'role' => $normalizedRole,
        'verified' => false,
        'createdAt' => date('Y-m-d H:i:s')
    ];

    // Register the user
    $result = $authHandler->registerUser(
        $userData['email'],
        $userData['password'],
        [
            'role' => $userData['role'],
            'displayName' => $userData['name'],
            'phone' => $userData['phone'],
            'address' => $userData['address'],
            'verified' => $userData['verified'],
            'createdAt' => $userData['createdAt']
        ]
    );

    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => 'Registration successful! Please check your email to verify your account.',
            'data' => [
                'userId' => $result['userId'] ?? null,
                'email' => $userData['email']
            ]
        ];
    } else {
        // Registration succeeded even if email sending failed; surface softer message
        $response = [
            'success' => true,
            'message' => 'Registration successful. If you do not receive a verification email, use "Resend Verification".',
            'data' => [
                'userId' => $result['userId'] ?? null,
                'email' => $userData['email']
            ]
        ];
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
    
    // Log the error
    error_log('Registration Error: ' . $e->getMessage());
}

echo json_encode($response);
