<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use MealDeal\Auth\AuthHandler;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize handler
$authHandler = new AuthHandler($config);
$appConfig = $authHandler->getAppConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo htmlspecialchars($appConfig['name']); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: #4CAF50;
            margin-bottom: 1rem;
        }
        p {
            margin-bottom: 1.5rem;
            color: #333;
        }
        a.btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        a.btn:hover {
            background: #3e8e41;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Email Verified!</h1>
        <p>Your email address has been successfully verified. You can now log in to your account.</p>
        <a href="<?php echo htmlspecialchars($appConfig['login_url']); ?>" class="btn">Go to Login</a>
    </div>
</body>
</html>
