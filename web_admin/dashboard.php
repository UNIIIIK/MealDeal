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
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f9fc; }
        .container { padding: 20px; }
        h1 { margin-bottom: 20px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card h2 { margin: 10px 0; font-size: 28px; color: #2c3e50; }
        .card span { font-size: 14px; color: #7f8c8d; }
    </style>
</head>
<body>
<div class="container">
    <h1>Admin Dashboard</h1>
    <div class="stats-grid">
        <div class="card">
            <h2 id="users_count">--</h2>
            <span>Users</span>
        </div>
        <div class="card">
            <h2 id="posts_count">--</h2>
            <span>Food Posts</span>
        </div>
        <div class="card">
            <h2 id="orders_count">--</h2>
            <span>Orders</span>
        </div>
        <div class="card">
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
