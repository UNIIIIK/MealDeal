<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/cache.php';

// Redirect if not logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get cached dashboard stats
$dashboard = getDashboardCache();

// Map cached data to variables for template
$totalUsers     = $dashboard['users']['total_users'] ?? 0;
$providers      = $dashboard['users']['providers'] ?? 0;
$consumers      = $dashboard['users']['consumers'] ?? 0;
$verifiedUsers  = $dashboard['users']['verified_users'] ?? 0;
$recentSignups  = $dashboard['users']['recent_signups'] ?? 0;

$activeListings = $dashboard['listings']['active_listings'] ?? 0;
$totalRevenue   = $dashboard['listings']['total_revenue'] ?? 0;

$pendingReports = $dashboard['reports']['pending_reports'] ?? 0;
$totalReports   = $dashboard['reports']['total_reports'] ?? 0;
$recentReports  = $dashboard['reports']['recent_reports'] ?? [];

$totalOrders        = $dashboard['orders']['total_orders'] ?? 0;
$completedOrders    = $dashboard['orders']['completed_orders'] ?? 0;
$totalFoodSaved     = $dashboard['orders']['total_food_saved'] ?? 0;
$totalSavings       = $dashboard['orders']['total_savings'] ?? 0;
$avgOrderValue      = $dashboard['orders']['average_order_value'] ?? 0;

$topProviders       = $dashboard['top_providers'] ?? [];
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
<?php include 'partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-flag me-2"></i>Reports
                        <?php if ($pendingReports > 0): ?><span class="badge bg-danger ms-2"><?php echo $pendingReports; ?></span><?php endif; ?>
                    </a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i>User Management</a></li>
                    <li class="nav-item"><a class="nav-link" href="listings.php"><i class="fas fa-list me-2"></i>Content Moderation</a></li>
                    <li class="nav-item"><a class="nav-link" href="leaderboard.php"><i class="fas fa-trophy me-2"></i>Leaderboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="impact.php"><i class="fas fa-chart-line me-2"></i>Impact Tracking</a></li>
                    <li class="nav-item"><a class="nav-link" href="pricing.php"><i class="fas fa-tags me-2"></i>Pricing Control</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Overview</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload();">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalUsers); ?></div>
                                <small><?php echo $providers; ?> Providers • <?php echo $consumers; ?> Consumers</small>
                            </div>
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Listings</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($activeListings); ?></div>
                                <small>₱<?php echo number_format($totalRevenue, 0); ?> Revenue</small>
                            </div>
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reports</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pendingReports); ?></div>
                                <small><?php echo number_format($totalReports); ?> Total Reports</small>
                            </div>
                            <i class="fas fa-flag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Food Saved (kg)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalFoodSaved, 1); ?></div>
                                <small>₱<?php echo number_format($totalSavings, 0); ?> Saved</small>
                            </div>
                            <i class="fas fa-leaf fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders & Users Stats -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Orders</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalOrders); ?></div>
                                <small><?php echo number_format($completedOrders); ?> Completed</small>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-dark shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Avg Order Value</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">₱<?php echo number_format($avgOrderValue, 0); ?></div>
                                <small>Per Transaction</small>
                            </div>
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-light shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-light text-uppercase mb-1">Recent Signups</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $recentSignups; ?></div>
                                <small>Last 7 Days</small>
                            </div>
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Verified Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($verifiedUsers); ?></div>
                                <small><?php echo $totalUsers > 0 ? round(($verifiedUsers/$totalUsers)*100,1) : 0; ?>% of Total</small>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports & Top Providers -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6></div>
                        <div class="card-body">
                            <?php if (!empty($recentReports)): ?>
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
                                        <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $reportType = $report['type'] ?? '';
                                                $typeDisplay = !empty($reportType) ? ucfirst(str_replace('_',' ',$reportType)) : 'Unknown';
                                                $typeClass = '';
                                                if (empty($reportType) || strtolower($typeDisplay) === 'unknown') {
                                                    $typeClass = 'report-badge-unknown';
                                                } else {
                                                    $typeClass = $reportType == 'inappropriate' ? 'bg-danger' : ($reportType == 'poor_quality' ? 'bg-warning' : 'bg-info');
                                                }
                                                ?>
                                                <span class="badge <?php echo $typeClass; ?>">
                                                    <?php echo htmlspecialchars($typeDisplay); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['reporter_name'] ?? 'N/A'); ?></td>
                                            <td><span class="badge bg-<?php echo $report['status']=='pending'?'warning':'success'; ?>">
                                                <?php echo ucfirst($report['status'] ?? 'unknown'); ?>
                                            </span></td>
                                            <td><?php echo date('M j, Y', strtotime($report['created_at'] ?? 'now')); ?></td>
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
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">Top Providers</h6></div>
                        <div class="card-body">
                            <?php if (!empty($topProviders)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr><th>Provider</th><th>Listings</th><th>Revenue</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topProviders as $provider): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($provider['name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($provider['email']); ?></small></td>
                                            <td><span class="badge bg-primary"><?php echo $provider['active_listings']; ?> Active</span><br><small class="text-muted"><?php echo $provider['total_listings']; ?> Total</small></td>
                                            <td>₱<?php echo number_format($provider['total_revenue'],0); ?></td>
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
</body>
</html>

