<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    'message' => 'Failed to resend verification email'
];

try {
    // Validate input
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (empty($input['email'])) {
        throw new Exception('Email is required');
    }

    $email = $input['email'];
    
    // Try to get user by email
    $auth = $authHandler->getAuth();
    $user = $auth->getUserByEmail($email);
    
    // If user doesn't exist or is already verified, don't reveal that info
    if (!$user || $user->emailVerified) {
        $response['message'] = 'If an account exists with this email, a verification link has been sent.';
        echo json_encode($response);
        exit;
    }
    
    // Resend verification email
    $result = $authHandler->sendVerificationEmail($email);
    if (!$result['success'] && isset($result['debug']['smtp_error'])) {
        error_log('SMTP error: ' . $result['debug']['smtp_error']);
    }
    
    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => 'Verification email has been resent. Please check your inbox.'
        ];
    } else {
        throw new Exception($result['message'] ?? 'Failed to resend verification email');
    }
    
} catch (\Exception $e) {
    error_log('Resend Verification Error: ' . $e->getMessage());
    $response['message'] = 'Failed to resend verification email. Please try again later.';
}

echo json_encode($response);
