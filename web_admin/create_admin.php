<?php
// Script to create an admin account for testing
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($name && $email && $password) {
        try {
            $db = Database::getInstance()->getFirestore();
            $adminsRef = $db->collection('admins');
            
            // Check if admin already exists
            $existingAdmin = $adminsRef->where('email', '=', $email)->documents();
            $adminExists = false;
            foreach ($existingAdmin as $admin) {
                $adminExists = true;
                break;
            }
            
            if (!$adminExists) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $adminData = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'super_admin',
                    'created_at' => new Google\Cloud\Core\Timestamp(new DateTime())
                ];
                
                $adminsRef->add($adminData);
                echo "<div class='alert alert-success'>Admin account created successfully!</div>";
            } else {
                echo "<div class='alert alert-warning'>Admin with this email already exists.</div>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error creating admin: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Please fill in all fields.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - MealDeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Create Admin Account</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Admin</button>
                        </form>
                    </div>
                    <div class="card-footer">
                        <a href="login.php" class="btn btn-link">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

