<?php
// Keep this page lightweight and side-effect free to avoid Firestore client issues on shared hosts.
// The Flutter app will sync verification status to Firestore after next sign-in.
$email = $_GET['email'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified - MealDeal</title>
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
        .success-icon {
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 1rem;
        }
        h1 {
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        p {
            color: #333;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background: #3e8e41;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Email Verified Successfully!</h1>
        <?php if ($email): ?>
            <p>Your email <strong><?php echo htmlspecialchars($email); ?></strong> has been successfully verified.</p>
        <?php else: ?>
            <p>Your email has been successfully verified.</p>
        <?php endif; ?>
        <p>You can now log in to your MealDeal account.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
</body>
</html>
