<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$period = $_GET['period'] ?? 'all';
$type = $_GET['type'] ?? 'providers';

// Get leaderboard data
$leaderboardData = getLeaderboardData($period, $type);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - MealDeal Admin</title>
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
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-flag me-2"></i>Reports
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
                            <a class="nav-link active" href="leaderboard.php">
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
                    <h1 class="h2">Live Leaderboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshLeaderboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="?type=providers&period=<?php echo $period; ?>" 
                               class="btn btn-outline-primary <?php echo $type === 'providers' ? 'active' : ''; ?>">
                                <i class="fas fa-store me-2"></i>Top Providers
                            </a>
                            <a href="?type=consumers&period=<?php echo $period; ?>" 
                               class="btn btn-outline-primary <?php echo $type === 'consumers' ? 'active' : ''; ?>">
                                <i class="fas fa-users me-2"></i>Top Consumers
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="?type=<?php echo $type; ?>&period=daily" 
                               class="btn btn-outline-success <?php echo $period === 'daily' ? 'active' : ''; ?>">
                                Daily
                            </a>
                            <a href="?type=<?php echo $type; ?>&period=weekly" 
                               class="btn btn-outline-success <?php echo $period === 'weekly' ? 'active' : ''; ?>">
                                Weekly
                            </a>
                            <a href="?type=<?php echo $type; ?>&period=monthly" 
                               class="btn btn-outline-success <?php echo $period === 'monthly' ? 'active' : ''; ?>">
                                Monthly
                            </a>
                            <a href="?type=<?php echo $type; ?>&period=all" 
                               class="btn btn-outline-success <?php echo $period === 'all' ? 'active' : ''; ?>">
                                All Time
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Leaderboard Cards -->
                <div class="row">
                    <?php foreach ($leaderboardData as $index => $entry): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 <?php echo $index < 3 ? 'border-warning' : ''; ?>">
                            <div class="card-body text-center">
                                <!-- Rank Badge -->
                                <div class="position-relative mb-3">
                                    <?php if ($index < 3): ?>
                                        <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="rank-number">#<?php echo $index + 1; ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- User Info -->
                                <h5 class="card-title"><?php echo htmlspecialchars($entry['name']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($entry['email']); ?></p>

                                <!-- Stats -->
                                <div class="row text-center">
                                    <?php if ($type === 'providers'): ?>
                                        <div class="col-6">
                                            <div class="stat-value text-success">
                                                <?php echo number_format($entry['total_listings']); ?>
                                            </div>
                                            <div class="stat-label">Listings</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-value text-info">
                                                <?php echo number_format($entry['food_saved'], 1); ?> kg
                                            </div>
                                            <div class="stat-label">Food Saved</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-6">
                                            <div class="stat-value text-success">
                                                <?php echo number_format($entry['total_orders']); ?>
                                            </div>
                                            <div class="stat-label">Orders</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-value text-info">
                                                ₱<?php echo number_format($entry['total_savings'], 2); ?>
                                            </div>
                                            <div class="stat-label">Money Saved</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Additional Info -->
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Joined: <?php echo formatDate($entry['created_at']); ?>
                                    </small>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewUserDetails('<?php echo $entry['id']; ?>')">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="sendReward('<?php echo $entry['id']; ?>')">
                                        <i class="fas fa-gift me-1"></i>Send Reward
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Statistics Summary -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Leaderboard Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="stat-card">
                                            <div class="stat-icon text-success">
                                                <i class="fas fa-leaf fa-2x"></i>
                                            </div>
                                            <div class="stat-number"><?php echo number_format(getTotalFoodSaved($period)); ?> kg</div>
                                            <div class="stat-label">Total Food Saved</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-card">
                                            <div class="stat-icon text-info">
                                                <i class="fas fa-users fa-2x"></i>
                                            </div>
                                            <div class="stat-number"><?php echo number_format(getActiveUsers($period)); ?></div>
                                            <div class="stat-label">Active Users</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-card">
                                            <div class="stat-icon text-warning">
                                                <i class="fas fa-shopping-cart fa-2x"></i>
                                            </div>
                                            <div class="stat-number"><?php echo number_format(getTotalOrders($period)); ?></div>
                                            <div class="stat-label">Total Orders</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-card">
                                            <div class="stat-icon text-primary">
                                                <i class="fas fa-dollar-sign fa-2x"></i>
                                            </div>
                                            <div class="stat-number">₱<?php echo number_format(getTotalSavings($period), 2); ?></div>
                                            <div class="stat-label">Total Savings</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reward Modal -->
    <div class="modal fade" id="rewardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Reward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/send_reward.php">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="rewardUserId">
                        
                        <div class="mb-3">
                            <label for="rewardType" class="form-label">Reward Type</label>
                            <select class="form-select" name="reward_type" id="rewardType" required>
                                <option value="">Select reward type</option>
                                <option value="badge">Special Badge</option>
                                <option value="discount">Discount Coupon</option>
                                <option value="featured">Featured Listing</option>
                                <option value="points">Bonus Points</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rewardMessage" class="form-label">Reward Message</label>
                            <textarea class="form-control" name="reward_message" id="rewardMessage" rows="3" 
                                      placeholder="Congratulations! You've earned this reward for your outstanding contribution..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Send Reward</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/leaderboard.js"></script>
</body>
</html>

<?php
// Helper functions
function getLeaderboardData($period = 'all', $type = 'providers') {
    global $db;
    
    try {
        $startDate = getStartDate($period);
        
        if ($type === 'providers') {
            return getTopProviders($startDate);
        } else {
            return getTopConsumers($startDate);
        }
    } catch (Exception $e) {
        error_log("Error getting leaderboard data: " . $e->getMessage());
        return [];
    }
}

function getTopProviders($startDate) {
    global $db;
    
    $usersRef = $db->getCollection('users');
    $users = $usersRef->where('role', '=', 'food_provider')->documents();
    
    $providers = [];
    foreach ($users as $user) {
        $userData = $user->data();
        $userId = $user->id();
        
        // Get user's listings and calculate stats
        $listingsRef = $db->getCollection('listings');
        $userListings = $listingsRef->where('provider_id', '=', $userId)->documents();
        
        $totalListings = 0;
        $foodSaved = 0;
        
        foreach ($userListings as $listing) {
            $listingData = $listing->data();
            
            // Only count listings within the period
            if ($startDate === null || $listingData['created_at'] >= $startDate) {
                $totalListings++;
                
                // Calculate food saved from completed orders
                if (isset($listingData['quantity_sold']) && isset($listingData['weight_per_unit'])) {
                    $foodSaved += ($listingData['quantity_sold'] * $listingData['weight_per_unit']);
                }
            }
        }
        
        $providers[] = [
            'id' => $userId,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'total_listings' => $totalListings,
            'food_saved' => $foodSaved,
            'created_at' => $userData['created_at']
        ];
    }
    
    // Sort by food saved (descending)
    usort($providers, function($a, $b) {
        return $b['food_saved'] <=> $a['food_saved'];
    });
    
    return array_slice($providers, 0, 12); // Top 12 providers
}

function getTopConsumers($startDate) {
    global $db;
    
    $usersRef = $db->getCollection('users');
    $users = $usersRef->where('role', '=', 'food_consumer')->documents();
    
    $consumers = [];
    foreach ($users as $user) {
        $userData = $user->data();
        $userId = $user->id();
        
        // Get user's completed orders
        $cartsRef = $db->getCollection('cart');
        $userCarts = $cartsRef->where('user_id', '=', $userId)
                             ->where('status', '=', 'completed')->documents();
        
        $totalOrders = 0;
        $totalSavings = 0;
        
        foreach ($userCarts as $cart) {
            $cartData = $cart->data();
            
            // Only count orders within the period
            if ($startDate === null || $cartData['checkout_date'] >= $startDate) {
                $totalOrders++;
                
                // Calculate savings
                if (isset($cartData['total_savings'])) {
                    $totalSavings += $cartData['total_savings'];
                }
            }
        }
        
        $consumers[] = [
            'id' => $userId,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'total_orders' => $totalOrders,
            'total_savings' => $totalSavings,
            'created_at' => $userData['created_at']
        ];
    }
    
    // Sort by total savings (descending)
    usort($consumers, function($a, $b) {
        return $b['total_savings'] <=> $a['total_savings'];
    });
    
    return array_slice($consumers, 0, 12); // Top 12 consumers
}

function getStartDate($period) {
    switch ($period) {
        case 'daily':
            return strtotime('today');
        case 'weekly':
            return strtotime('this week monday');
        case 'monthly':
            return strtotime('first day of this month');
        default:
            return null; // All time
    }
}

function getTotalFoodSaved($period) {
    global $db;
    
    try {
        $startDate = getStartDate($period);
        $cartsRef = $db->getCollection('cart');
        $carts = $cartsRef->where('status', '=', 'completed')->documents();
        
        $totalFoodSaved = 0;
        foreach ($carts as $cart) {
            $cartData = $cart->data();
            
            if ($startDate === null || $cartData['checkout_date'] >= $startDate) {
                if (isset($cartData['items'])) {
                    foreach ($cartData['items'] as $item) {
                        if (isset($item['quantity']) && isset($item['weight_per_unit'])) {
                            $totalFoodSaved += ($item['quantity'] * $item['weight_per_unit']);
                        }
                    }
                }
            }
        }
        
        return $totalFoodSaved;
    } catch (Exception $e) {
        error_log("Error getting total food saved: " . $e->getMessage());
        return 0;
    }
}

function getActiveUsers($period) {
    global $db;
    
    try {
        $startDate = getStartDate($period);
        $usersRef = $db->getCollection('users');
        $users = $usersRef->documents();
        
        $activeUsers = 0;
        foreach ($users as $user) {
            $userData = $user->data();
            
            if ($startDate === null || $userData['last_activity'] >= $startDate) {
                $activeUsers++;
            }
        }
        
        return $activeUsers;
    } catch (Exception $e) {
        error_log("Error getting active users: " . $e->getMessage());
        return 0;
    }
}

function getTotalOrders($period) {
    global $db;
    
    try {
        $startDate = getStartDate($period);
        $cartsRef = $db->getCollection('cart');
        $carts = $cartsRef->where('status', '=', 'completed')->documents();
        
        $totalOrders = 0;
        foreach ($carts as $cart) {
            $cartData = $cart->data();
            
            if ($startDate === null || $cartData['checkout_date'] >= $startDate) {
                $totalOrders++;
            }
        }
        
        return $totalOrders;
    } catch (Exception $e) {
        error_log("Error getting total orders: " . $e->getMessage());
        return 0;
    }
}

function getTotalSavings($period) {
    global $db;
    
    try {
        $startDate = getStartDate($period);
        $cartsRef = $db->getCollection('cart');
        $carts = $cartsRef->where('status', '=', 'completed')->documents();
        
        $totalSavings = 0;
        foreach ($carts as $cart) {
            $cartData = $cart->data();
            
            if ($startDate === null || $cartData['checkout_date'] >= $startDate) {
                if (isset($cartData['total_savings'])) {
                    $totalSavings += $cartData['total_savings'];
                }
            }
        }
        
        return $totalSavings;
    } catch (Exception $e) {
        error_log("Error getting total savings: " . $e->getMessage());
        return 0;
    }
}

function formatDate($timestamp) {
    if (is_array($timestamp)) {
        $timestamp = $timestamp['seconds'] ?? time();
    }
    return date('M j, Y', $timestamp);
}
?>
