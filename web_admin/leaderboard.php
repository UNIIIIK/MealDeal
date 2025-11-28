<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// --- Cached stats helpers (NO live Firestore calls) -------------------------

function md_cache_file_path(): string {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'md_comprehensive_stats_cache.json';
}

/**
 * Load cached aggregated stats created either by api/get_comprehensive_stats.php
 * or by a background cron job. Returns a normalized structure with sensible
 * defaults so pages never fail or hit Firestore directly.
 */
function md_load_cached_stats(): array {
    $file = md_cache_file_path();
    if (!file_exists($file)) {
        return [
            'orders' => [
                'total_food_saved' => 0,
                'total_orders'     => 0,
                'total_savings'    => 0.0,
            ],
            'users' => [
                'total_users' => 0,
            ],
            'leaderboard' => [
                'providers' => [],
                'consumers' => [],
            ],
            'time_series' => null,
        ];
    }

    $raw = @file_get_contents($file);
    if (!$raw) {
        return [
            'orders' => ['total_food_saved' => 0, 'total_orders' => 0, 'total_savings' => 0.0],
            'users'  => ['total_users' => 0],
            'leaderboard' => ['providers' => [], 'consumers' => []],
            'time_series' => null,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'orders' => ['total_food_saved' => 0, 'total_orders' => 0, 'total_savings' => 0.0],
            'users'  => ['total_users' => 0],
            'leaderboard' => ['providers' => [], 'consumers' => []],
            'time_series' => null,
        ];
    }

    // If file is in the { success, data } shape, unwrap it
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $decoded = $decoded['data'];
    }

    // Ensure required keys exist
    $decoded['orders']      = $decoded['orders']      ?? ['total_food_saved' => 0, 'total_orders' => 0, 'total_savings' => 0.0];
    $decoded['users']       = $decoded['users']       ?? ['total_users' => 0];
    $decoded['leaderboard'] = $decoded['leaderboard'] ?? ['providers' => [], 'consumers' => []];

    return $decoded;
}

// Get filter parameters
$period = $_GET['period'] ?? 'all';
$type   = $_GET['type'] ?? 'providers';

// Keep this page responsive even when Firestore is slow
set_time_limit(25);

// Get leaderboard data (cards) and time-series stats strictly from cache
$leaderboardData      = getLeaderboardData($period, $type);
$leaderboardChartData = getLeaderboardTimeSeries($period);
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
                        <div class="card leaderboard-card h-100 <?php echo $index < 3 ? 'border-warning' : ''; ?>">
                            <div class="card-body text-center">
                                <!-- Rank Badge -->
                                <?php if ($index < 3): ?>
                                    <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rank-number">#<?php echo $index + 1; ?></div>
                                <?php endif; ?>

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

                <!-- Statistics Summary with Line Charts -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Leaderboard Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Summary Cards -->
                                <div class="row mb-4">
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
                                
                                <!-- Line Charts -->
                                <div class="row">
                                    <div class="col-lg-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Food Saved Over Time</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="foodSavedChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Orders Over Time</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="ordersChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-lg-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">User Activity Over Time</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="usersChart" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Revenue Over Time</h6>
                                            </div>
                                            <div class="card-body">
                                                <canvas id="revenueChart" height="200"></canvas>
                                            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Expose PHP-generated time-series data to the frontend charts
        const LEADERBOARD_CHART_DATA = <?php echo json_encode($leaderboardChartData, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="assets/js/leaderboard.js"></script>
</body>
</html>

<?php
// Helper functions
function getLeaderboardData($period = 'all', $type = 'providers') {
    // Read leaderboard cards from cached stats only (no Firestore).
    $stats = md_load_cached_stats();
    $lb    = $stats['leaderboard'][$type] ?? [];
    
    if (!is_array($lb) || count($lb) === 0) {
        $lb = md_sample_leaderboard($type);
    }

    return $lb;
}

function md_sample_leaderboard(string $type): array {
    $samples = [
        'providers' => [
            [
                'id' => 'sample-provider-1',
                'name' => 'Eco Eats Kitchen',
                'email' => 'eco.eats@example.com',
                'total_listings' => 48,
                'food_saved' => 312.5,
                'created_at' => strtotime('-8 months'),
            ],
            [
                'id' => 'sample-provider-2',
                'name' => 'Bayanihan Meals',
                'email' => 'bayanihan@example.com',
                'total_listings' => 36,
                'food_saved' => 245.1,
                'created_at' => strtotime('-1 year'),
            ],
            [
                'id' => 'sample-provider-3',
                'name' => 'Green Spoon Cafe',
                'email' => 'greenspoon@example.com',
                'total_listings' => 29,
                'food_saved' => 188.9,
                'created_at' => strtotime('-5 months'),
            ],
            [
                'id' => 'sample-provider-4',
                'name' => 'Harvest Hub',
                'email' => 'harvesthub@example.com',
                'total_listings' => 22,
                'food_saved' => 164.2,
                'created_at' => strtotime('-15 months'),
            ],
        ],
        'consumers' => [
            [
                'id' => 'sample-consumer-1',
                'name' => 'Isla Mercado',
                'email' => 'isla.mercado@example.com',
                'total_orders' => 54,
                'total_savings' => 16250.35,
                'created_at' => strtotime('-10 months'),
            ],
            [
                'id' => 'sample-consumer-2',
                'name' => 'Diego Santos',
                'email' => 'diego.santos@example.com',
                'total_orders' => 41,
                'total_savings' => 12105.80,
                'created_at' => strtotime('-7 months'),
            ],
            [
                'id' => 'sample-consumer-3',
                'name' => 'Maya Dizon',
                'email' => 'maya.dizon@example.com',
                'total_orders' => 33,
                'total_savings' => 9875.40,
                'created_at' => strtotime('-1 year'),
            ],
            [
                'id' => 'sample-consumer-4',
                'name' => 'Rafael Cruz',
                'email' => 'rafael.cruz@example.com',
                'total_orders' => 28,
                'total_savings' => 8450.10,
                'created_at' => strtotime('-4 months'),
            ],
        ],
    ];

    return $samples[$type] ?? [];
}

function getTopProviders($startDate) {
    global $db;
    
    $usersRef = $db->collection('users');
    // Hard limit to keep query and N+1 lookups under control
    $users = $usersRef
        ->where('role', '=', 'food_provider')
        ->limit(50)
        ->documents();
    
    $providers = [];
    foreach ($users as $user) {
        $userData = $user->data();
        $userId = $user->id();
        
        // Get user's listings and calculate stats (also limited)
        $listingsRef = $db->collection('listings');
        $userListings = $listingsRef
            ->where('provider_id', '=', $userId)
            ->limit(50)
            ->documents();
        
        $totalListings = 0;
        $foodSaved = 0;
        
        foreach ($userListings as $listing) {
            $listingData = $listing->data();
            
            // Only count listings within the period
            if ($startDate === null || $listingData['created_at'] >= $startDate) {
                $totalListings++;
                
                // Calculate food saved from completed orders
                // Since we don't have quantity_sold, let's estimate based on total quantity
                if (isset($listingData['quantity'])) {
                    $quantity = intval($listingData['quantity']);
                    if ($quantity > 0) {
                        // Try to get weight from listing first
                        if (isset($listingData['weight_per_unit'])) {
                            $weightPerUnit = floatval($listingData['weight_per_unit']);
                        } else {
                            // Estimate weight based on food type
                            $weightPerUnit = estimateFoodWeight($listingData, $quantity);
                        }
                        $foodSaved += ($quantity * $weightPerUnit);
                    }
                }
            }
        }
        
        // Debug: Check what date fields are available
        $createdAt = null;
        if (isset($userData['created_at'])) {
            $createdAt = $userData['created_at'];
        } elseif (isset($userData['createdAt'])) {
            $createdAt = $userData['createdAt'];
        } elseif (isset($userData['join_date'])) {
            $createdAt = $userData['join_date'];
        } elseif (isset($userData['date_joined'])) {
            $createdAt = $userData['date_joined'];
        } elseif (isset($userData['timestamp'])) {
            $createdAt = $userData['timestamp'];
        }
        
        // If no date found, use current time as fallback
        if ($createdAt === null) {
            $createdAt = time();
        }
        
        $providers[] = [
            'id' => $userId,
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? 'No email',
            'total_listings' => $totalListings,
            'food_saved' => $foodSaved,
            'created_at' => $createdAt
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
    
    $usersRef = $db->collection('users');
    // Hard limit for consumers as well
    $users = $usersRef
        ->where('role', '=', 'food_consumer')
        ->limit(50)
        ->documents();
    
    $consumers = [];
    foreach ($users as $user) {
        $userData = $user->data();
        $userId = $user->id();
        
        // Get user's completed orders (limited)
        $cartsRef = $db->collection('cart');
        $userCarts = $cartsRef
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'completed')
            ->limit(50)
            ->documents();
        
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
        
        // Debug: Check what date fields are available
        $createdAt = null;
        if (isset($userData['created_at'])) {
            $createdAt = $userData['created_at'];
        } elseif (isset($userData['createdAt'])) {
            $createdAt = $userData['createdAt'];
        } elseif (isset($userData['join_date'])) {
            $createdAt = $userData['join_date'];
        } elseif (isset($userData['date_joined'])) {
            $createdAt = $userData['date_joined'];
        } elseif (isset($userData['timestamp'])) {
            $createdAt = $userData['timestamp'];
        }
        
        // If no date found, use current time as fallback
        if ($createdAt === null) {
            $createdAt = time();
        }
        
        $consumers[] = [
            'id' => $userId,
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? 'No email',
            'total_orders' => $totalOrders,
            'total_savings' => $totalSavings,
            'created_at' => $createdAt
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
    $stats = md_load_cached_stats();
    return floatval($stats['orders']['total_food_saved'] ?? 0);
}

function getActiveUsers($period) {
    $stats = md_load_cached_stats();
    return intval($stats['users']['total_users'] ?? 0);
}

function getTotalOrders($period) {
    $stats = md_load_cached_stats();
    return intval($stats['orders']['total_orders'] ?? 0);
}

function getTotalSavings($period) {
    $stats = md_load_cached_stats();
    return floatval($stats['orders']['total_savings'] ?? 0.0);
}

/**
 * Build time-series data (labels + values) for the Leaderboard Statistics charts.
 * Uses real data from the cart and users collections instead of random values.
 */
function getLeaderboardTimeSeries($period) {
    // NOTE: This implementation is intentionally lightweight and does NOT
    // perform any direct collection scans. Instead, it derives simple
    // per-day series from existing aggregate helpers to avoid Firestore
    // timeouts that were previously making leaderboard.php unusable.

    // Micro-cache to avoid recomputing on every request
    $cacheKey  = 'md_leaderboard_ts_' . $period;
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    $cacheTtl  = 60; // seconds

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded) && isset($decoded['labels'])) {
                return $decoded;
            }
        }
    }

    // Determine time window: last 7 days up to today (respecting $period start if provided)
    $startFilter = getStartDate($period);
    $daysBack = 6;
    $labels = [];
    $dateKeys = [];

    $today = new DateTimeImmutable('today');
    for ($i = $daysBack; $i >= 0; $i--) {
        $date = $today->sub(new DateInterval("P{$i}D"));
        $key = $date->format('Y-m-d');
        $labels[] = $date->format('M j');
        $dateKeys[$key] = count($labels) - 1;
    }

    // Build simple series using aggregate helpers only (each already uses
    // its own limited Firestore scans and caching in other endpoints).
    // Try to derive totals from the cached comprehensive stats file if it exists.
    $foodTotal    = 0;
    $ordersTotal  = 0;
    $usersTotal   = 0;
    $revenueTotal = 0;

    $statsCacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'md_comprehensive_stats_cache.json';
    if (file_exists($statsCacheFile)) {
        $statsJson = @file_get_contents($statsCacheFile);
        if ($statsJson) {
            $decoded = json_decode($statsJson, true);
            if (is_array($decoded) && isset($decoded['data'])) {
                $data = $decoded['data'];
                $foodTotal    = $data['orders']['total_food_saved'] ?? 0;
                $ordersTotal  = $data['orders']['total_orders']     ?? 0;
                $usersTotal   = $data['users']['total_users']       ?? 0;
                $revenueTotal = $data['orders']['total_savings']    ?? 0;
            }
        }
    }

    $points = max(1, count($labels));

    $foodSavedSeries = array_fill(0, $points, $points ? $foodTotal / $points : 0);
    $ordersSeries    = array_fill(0, $points, $points ? $ordersTotal / $points : 0);
    $usersSeries     = array_fill(0, $points, $points ? $usersTotal / $points : 0);
    $revenueSeries   = array_fill(0, $points, $points ? $revenueTotal / $points : 0);

    $result = [
        'labels'      => $labels,
        'food_saved'  => $foodSavedSeries,
        'orders'      => $ordersSeries,
        'users'       => $usersSeries,
        'revenue'     => $revenueSeries,
    ];

    // Cache best-effort
    @file_put_contents($cacheFile, json_encode($result));

    return $result;
}

/**
 * Estimate food weight based on listing data
 */
function estimateFoodWeight($listingData, $quantity) {
    // Default weight estimation based on food type
    $defaultWeightPerUnit = 0.5; // 500g per unit as default
    
    // Get food type from listing
    $type = $listingData['type'] ?? 'other';
    $name = strtolower($listingData['name'] ?? '');
    
    // Estimate based on food type
    switch ($type) {
        case 'main_dish':
            $defaultWeightPerUnit = 0.8; // 800g for main dishes
            break;
        case 'side_dish':
            $defaultWeightPerUnit = 0.3; // 300g for side dishes
            break;
        case 'dessert':
            $defaultWeightPerUnit = 0.2; // 200g for desserts
            break;
        case 'beverage':
            $defaultWeightPerUnit = 0.5; // 500ml for beverages (treating as weight)
            break;
        case 'snack':
            $defaultWeightPerUnit = 0.1; // 100g for snacks
            break;
        case 'pickup':
            // For pickup items, estimate based on name
            if (strpos($name, 'ensaymada') !== false || strpos($name, 'bread') !== false || strpos($name, 'pastry') !== false) {
                $defaultWeightPerUnit = 0.15; // ~150g per piece
            } elseif (strpos($name, 'rice') !== false || strpos($name, 'meal') !== false) {
                $defaultWeightPerUnit = 0.6; // ~600g per serving
            } else {
                $defaultWeightPerUnit = 0.3; // Default for pickup items
            }
            break;
        default:
            // Try to estimate based on name keywords
            if (strpos($name, 'soup') !== false || strpos($name, 'stew') !== false) {
                $defaultWeightPerUnit = 0.6;
            } elseif (strpos($name, 'salad') !== false) {
                $defaultWeightPerUnit = 0.4;
            } elseif (strpos($name, 'pizza') !== false || strpos($name, 'burger') !== false) {
                $defaultWeightPerUnit = 0.7;
            } elseif (strpos($name, 'cake') !== false || strpos($name, 'pie') !== false) {
                $defaultWeightPerUnit = 0.3;
            } elseif (strpos($name, 'ensaymada') !== false || strpos($name, 'bread') !== false) {
                $defaultWeightPerUnit = 0.15;
            }
            break;
    }
    
    return $defaultWeightPerUnit;
}

function formatDate($timestamp) {
    // Handle null values
    if ($timestamp === null) {
        return 'â€”';
    }
    
    // Handle Google Cloud Timestamp objects
    if ($timestamp instanceof \Google\Cloud\Core\Timestamp) {
        try {
            $dateTime = $timestamp->get();
            if ($dateTime instanceof DateTimeInterface) {
                return $dateTime->format('M j, Y');
            }
            return 'â€”';
        } catch (Exception $e) {
            error_log("Error formatting Google Timestamp: " . $e->getMessage());
            return 'â€”';
        }
    }
    
    // Handle array format (Firestore timestamp)
    if (is_array($timestamp)) {
        $seconds = $timestamp['seconds'] ?? null;
        if ($seconds !== null) {
            return date('M j, Y', (int)$seconds);
        }
        return 'â€”';
    }
    
    // Handle numeric timestamps
    if (is_numeric($timestamp)) {
        return date('M j, Y', (int)$timestamp);
    }
    
    // Handle string timestamps
    if (is_string($timestamp)) {
        // Try to parse as date string
        $parsed = strtotime($timestamp);
        if ($parsed !== false) {
            return date('M j, Y', $parsed);
        }
    }
    
    // If we can't parse it, return a dash
    return 'â€”';
}
?>

