<?php
// dashboard.php
session_start();

// Simple auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MealDeal Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="page-dashboard">
<div class="container dashboard-container">
    <h1 class="dashboard-title">Admin Dashboard</h1>
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2 id="users_count">--</h2>
            <span>Users</span>
        </div>
        <div class="dashboard-card">
            <h2 id="posts_count">--</h2>
            <span>Food Posts</span>
        </div>
        <div class="dashboard-card">
            <h2 id="orders_count">--</h2>
            <span>Orders</span>
        </div>
        <div class="dashboard-card">
            <h2 id="revenue_total">--</h2>
            <span>Total Revenue</span>
        </div>
    </div>
</div>

<script>
async function loadStats() {
    try {
        const res = await fetch('api/dashboard_stats.php');
        const data = await res.json();

        if (data.success) {
            document.getElementById('users_count').textContent   = data.stats.users_count;
            document.getElementById('posts_count').textContent   = data.stats.posts_count;
            document.getElementById('orders_count').textContent  = data.stats.orders_count;
            document.getElementById('revenue_total').textContent = 
                new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
                .format(data.stats.revenue_total);
        } else {
            console.error('Error loading stats:', data.error);
        }
    } catch (err) {
        console.error('Request failed:', err);
    }
}

document.addEventListener('DOMContentLoaded', loadStats);
</script>
</body>
</html>
