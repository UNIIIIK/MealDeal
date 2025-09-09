<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Get the error message if any
$error = $_GET['error'] ?? 'The verification link is invalid or has expired.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Error - MealDeal</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        .error-icon {
            font-size: 4rem;
            color: #f44336;
            margin-bottom: 1rem;
        }
        h1 {
            color: #c62828;
            margin-bottom: 1rem;
        }
        p {
            color: #333;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            background: #f44336;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            margin: 0.5rem;
        }
        .btn:hover {
            background: #d32f2f;
        }
        .btn.secondary {
            background: #757575;
        }
        .btn.secondary:hover {
            background: #616161;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">✕</div>
        <h1>Verification Failed</h1>
        <p><?php echo htmlspecialchars($error); ?></p>
        <div>
            <a href="/" class="btn">Back to Home</a>
            <a href="/signup.php" class="btn secondary">Create New Account</a>
        </div>
    </div>
</body>
</html>
