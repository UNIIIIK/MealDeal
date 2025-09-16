<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/data_functions.php';

// If not logged in, send to login page
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Load comprehensive statistics
$comprehensiveStats = [
    'users' => ['total_users' => 0, 'providers' => 0, 'consumers' => 0, 'verified_users' => 0, 'recent_signups' => 0],
    'listings' => ['active_listings' => 0, 'total_revenue' => 0],
    'reports' => ['pending_reports' => 0, 'total_reports' => 0, 'recent_reports' => []],
    'orders' => ['total_food_saved' => 0, 'total_savings' => 0, 'total_orders' => 0, 'completed_orders' => 0, 'average_order_value' => 0],
    'top_providers' => []
];

try {
    set_time_limit(30); // Increased timeout for better data loading
    
    // Load user statistics with proper verified user counting
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(100)->documents();
    
    $userCount = 0;
    $providerCount = 0;
    $consumerCount = 0;
    $verifiedCount = 0;
    $recentSignups = 0;
    
    $oneWeekAgo = new DateTime('-1 week');
    
    foreach ($users as $user) {
        $userCount++;
        $userData = $user->data();
        
        // Count by role
        if (isset($userData['role'])) {
            if ($userData['role'] === 'food_provider') {
                $providerCount++;
            } elseif ($userData['role'] === 'food_consumer') {
                $consumerCount++;
            }
        }
        
        // Count verified users - check for verified field
        if (isset($userData['verified']) && $userData['verified'] === true) {
            $verifiedCount++;
        }
        
        // Count recent signups
        if (isset($userData['created_at'])) {
            $createdAt = $userData['created_at'];
            if ($createdAt instanceof Google\Cloud\Core\Timestamp) {
                $createdDate = $createdAt->get();
                if ($createdDate > $oneWeekAgo) {
                    $recentSignups++;
                }
            }
        }
    }
    
    $comprehensiveStats['users'] = [
        'total_users' => $userCount,
        'providers' => $providerCount,
        'consumers' => $consumerCount,
        'verified_users' => $verifiedCount,
        'recent_signups' => $recentSignups
    ];
    
    // Load listing statistics
    $listingsRef = $db->getCollection('listings');
    $listings = $listingsRef->limit(50)->documents();
    
    $activeListings = 0;
    $totalRevenue = 0;
    
    foreach ($listings as $listing) {
        $listingData = $listing->data();
        if (isset($listingData['status']) && $listingData['status'] === 'active') {
            $activeListings++;
        }
        if (isset($listingData['discounted_price']) && isset($listingData['quantity'])) {
            $totalRevenue += floatval($listingData['discounted_price']) * intval($listingData['quantity']);
        }
    }
    
    $comprehensiveStats['listings'] = [
        'active_listings' => $activeListings,
        'total_revenue' => $totalRevenue
    ];
    
    // Load order statistics
    $cartsRef = $db->getCollection('cart');
    $carts = $cartsRef->limit(50)->documents();
    
    $totalOrders = 0;
    $completedOrders = 0;
    $totalFoodSaved = 0;
    $totalSavings = 0;
    $totalOrderValue = 0;
    $orderCount = 0;
    
    foreach ($carts as $cart) {
        $cartData = $cart->data();
        $totalOrders++;
        
        // Count completed orders
        if (isset($cartData['status'])) {
            $status = strtolower(trim($cartData['status']));
            if (in_array($status, ['completed', 'delivered', 'fulfilled', 'done', 'success', 'finished', 'picked_up'])) {
                $completedOrders++;
            }
        }
        
        // Calculate savings and food saved
        if (isset($cartData['total_price'])) {
            $orderValue = floatval($cartData['total_price']);
            $totalOrderValue += $orderValue;
            $orderCount++;
            $totalSavings += ($orderValue * 0.5); // Assuming 50% discount
        }
        
        // Calculate food saved from items
        if (isset($cartData['items']) && is_array($cartData['items'])) {
            foreach ($cartData['items'] as $item) {
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                if (isset($item['weight_per_unit'])) {
                    $weightKg = floatval($item['weight_per_unit']);
                    $totalFoodSaved += ($quantity * $weightKg);
                } else {
                    // Estimate weight
                    $totalFoodSaved += ($quantity * 0.5); // Default 500g per item
                }
            }
        }
    }
    
    $averageOrderValue = $orderCount > 0 ? round($totalOrderValue / $orderCount, 2) : 0;
    
    $comprehensiveStats['orders'] = [
        'total_food_saved' => $totalFoodSaved,
        'total_savings' => $totalSavings,
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'average_order_value' => $averageOrderValue
    ];
    
    // Load top providers
    $topProviders = [];
    $providersRef = $db->getCollection('users');
    $providers = $providersRef->where('role', '=', 'food_provider')->limit(20)->documents();
    
    foreach ($providers as $provider) {
        $providerData = $provider->data();
        $providerId = $provider->id();
        
        // Get provider's listings
        $providerListings = $listingsRef->where('provider_id', '=', $providerId)->limit(50)->documents();
        
        $providerStats = [
            'name' => $providerData['name'] ?? 'Unknown',
            'email' => $providerData['email'] ?? '',
            'active_listings' => 0,
            'total_listings' => 0,
            'total_revenue' => 0
        ];
        
        foreach ($providerListings as $listing) {
            $listingData = $listing->data();
            $providerStats['total_listings']++;
            
            if (isset($listingData['status']) && $listingData['status'] === 'active') {
                $providerStats['active_listings']++;
            }
            
            if (isset($listingData['discounted_price']) && isset($listingData['quantity'])) {
                $price = floatval($listingData['discounted_price']);
                $quantity = intval($listingData['quantity']);
                $providerStats['total_revenue'] += ($price * $quantity);
            }
        }
        
        $topProviders[] = $providerStats;
    }
    
    // Sort by revenue and take top 5
    usort($topProviders, function($a, $b) {
        return $b['total_revenue'] <=> $a['total_revenue'];
    });
    
    $comprehensiveStats['top_providers'] = array_slice($topProviders, 0, 5);
    
} catch (Exception $e) {
    error_log("Error loading stats: " . $e->getMessage());
    $errorMessage = "Error loading some data. Please refresh the page.";
}
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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-leaf me-2"></i>MealDeal Super Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i><?php echo $_SESSION['admin_name']; ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-flag me-2"></i>Reports
                                <?php if ($stats['pending_reports'] > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $stats['pending_reports']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="listings.php">
                                <i class="fas fa-list me-2"></i>Content Moderation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leaderboard.php">
                                <i class="fas fa-trophy me-2"></i>Leaderboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="impact.php">
                                <i class="fas fa-chart-line me-2"></i>Impact Tracking
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">
                                <i class="fas fa-tags me-2"></i>Pricing Control
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Overview</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshStats()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['users']['total_users']); ?></div>
                                        <div class="text-xs text-muted">
                                            <?php echo $comprehensiveStats['users']['providers']; ?> Providers • 
                                            <?php echo $comprehensiveStats['users']['consumers']; ?> Consumers
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Listings
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['listings']['active_listings']); ?></div>
                                        <div class="text-xs text-muted">
                                            ₱<?php echo number_format($comprehensiveStats['listings']['total_revenue'], 0); ?> Revenue
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-list fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Reports
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['reports']['pending_reports']); ?></div>
                                        <div class="text-xs text-muted">
                                            <?php echo $comprehensiveStats['reports']['total_reports']; ?> Total Reports
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-flag fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Food Saved (kg)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['orders']['total_food_saved'], 1); ?></div>
                                        <div class="text-xs text-muted">
                                            ₱<?php echo number_format($comprehensiveStats['orders']['total_savings'], 0); ?> Saved
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-leaf fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Statistics Row -->
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-secondary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                            Total Orders
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['orders']['total_orders']); ?></div>
                                        <div class="text-xs text-muted">
                                            <?php echo $comprehensiveStats['orders']['completed_orders']; ?> Completed
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-dark shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                            Avg Order Value
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($comprehensiveStats['orders']['average_order_value'], 0); ?></div>
                                        <div class="text-xs text-muted">
                                            Per Transaction
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-light shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-light text-uppercase mb-1">
                                            Recent Signups
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['users']['recent_signups']); ?></div>
                                        <div class="text-xs text-muted">
                                            Last 7 Days
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Verified Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($comprehensiveStats['users']['verified_users']); ?></div>
                                        <div class="text-xs text-muted">
                                            <?php echo $comprehensiveStats['users']['total_users'] > 0 ? round(($comprehensiveStats['users']['verified_users'] / $comprehensiveStats['users']['total_users']) * 100, 1) : 0; ?>% of Total
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($comprehensiveStats['reports']['recent_reports'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Reporter</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($comprehensiveStats['reports']['recent_reports'], 0, 5) as $report): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-<?php echo $report['type'] === 'inappropriate' ? 'danger' : ($report['type'] === 'poor_quality' ? 'warning' : 'info'); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $report['type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $report['status'] === 'pending' ? 'warning' : 'success'; ?>">
                                                            <?php echo ucfirst($report['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No recent reports found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-success">Top Providers</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($comprehensiveStats['top_providers'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Provider</th>
                                                    <th>Listings</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($comprehensiveStats['top_providers'], 0, 5) as $provider): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($provider['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($provider['email']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary"><?php echo $provider['active_listings']; ?> Active</span>
                                                        <br><small class="text-muted"><?php echo $provider['total_listings']; ?> Total</small>
                                                    </td>
                                                    <td>₱<?php echo number_format($provider['total_revenue'], 0); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No provider data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
