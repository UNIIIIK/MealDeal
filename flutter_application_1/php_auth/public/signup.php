<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use MealDeal\Auth\AuthHandler;

// Load configuration
$config = require __DIR__ . '/../config/config.php';
$authHandler = new AuthHandler($config);

$error = '';
$success = '';
$formData = [
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'role' => 'consumer' // Default role
];

// Process signup form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_merge($formData, $_POST);
    
    // Basic validation
    if (empty($formData['email']) || empty($formData['password']) || empty($formData['confirm_password'])) {
        $error = 'All fields are required';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $error = 'Passwords do not match';
    } else {
        // Register the user
        $result = $authHandler->registerUser(
            $formData['email'],
            $formData['password'],
            [
                'role' => in_array($formData['role'], ['consumer', 'provider']) ? $formData['role'] : 'consumer',
                'verified' => false
            ]
        );
        
        if ($result['success']) {
            $success = 'Registration successful! Please check your email to verify your account.';
            // Clear form on success
            $formData = [
                'email' => '',
                'password' => '',
                'confirm_password' => '',
                'role' => 'consumer'
            ];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo htmlspecialchars($config['app']['name']); ?></title>
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
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #444;
        }
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .radio-group {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background: #3e8e41;
        }
        .error {
            color: #f44336;
            background: #ffebee;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .login-link {
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }
        .login-link a {
            color: #4CAF50;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create an Account</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($formData['email']); ?>" 
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    minlength="6"
                >
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required
                    minlength="6"
                >
            </div>
            
            <div class="form-group">
                <label>I am a:</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input 
                            type="radio" 
                            name="role" 
                            value="consumer" 
                            <?php echo $formData['role'] === 'consumer' ? 'checked' : ''; ?>
                        >
                        Food Consumer
                    </label>
                    <label class="radio-option">
                        <input 
                            type="radio" 
                            name="role" 
                            value="provider"
                            <?php echo $formData['role'] === 'provider' ? 'checked' : ''; ?>
                        >
                        Food Provider
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn">Sign Up</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>
</body>
</html>
