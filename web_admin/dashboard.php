<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/data_functions.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Defer expensive dashboard queries to the client via API to prevent timeouts
$comprehensiveStats = [
    'users' => ['total_users' => 0, 'providers' => 0, 'consumers' => 0, 'verified_users' => 0, 'recent_signups' => 0],
    'listings' => ['active_listings' => 0, 'total_revenue' => 0],
    'reports' => ['pending_reports' => 0, 'total_reports' => 0, 'recent_reports' => []],
    'orders' => ['total_food_saved' => 0, 'total_savings' => 0, 'total_orders' => 0, 'completed_orders' => 0, 'average_order_value' => 0],
    'top_providers' => []
];
$stats = $comprehensiveStats['orders'];
$stats['pending_reports'] = $comprehensiveStats['reports']['pending_reports'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MealDeal Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'index.php'; /* Reuse the existing dashboard markup via inclusion if needed */ ?>
</body>
</html>


