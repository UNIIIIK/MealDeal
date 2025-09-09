<?php

return [
    'firebase_credentials' => dirname(__DIR__) . '/firebase-credentials.json',
    
    'app' => [
        'name' => 'MealDeal',
        'base_url' => 'http://localhost:8000',
        'login_url' => '/login.php',
        'signup_url' => '/signup.php',
        'verification_success_url' => '/verification-success.php',
        'verification_error_url' => '/verification-error.php'
    ],
    
    'firestore' => [
        'users_collection' => 'users',
        'verified_field' => 'verified'
    ],
    
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'sanchezjamesss02@gmail.com',
        'password' => 'repxfryctlkrxuzr',
        'from_email' => 'sanchezjamesss02@gmail.com',
        'from_name' => 'MealDeal App',
        'debug' => 0,
        'smtp_debug' => false,
        'auth' => true,
        'smtp_secure' => 'tls'
    ]
];
