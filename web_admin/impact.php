<?php
session_start();
require_once 'includes/auth.php';
if (!isAdminLoggedIn()) { header('Location: login.php'); exit(); }
?>
<?php
// Simple demo/fallback data so the page shows meaningful values
$impactFallback = [
    'stats' => [
        'orders' => [
            'total_food_saved' => 128.4,
            'total_savings'    => 15250.75,
            'completed_orders' => 87,
        ],
        'users'  => [
            'total_users'  => 42,
            'providers'    => 12,
            'consumers'    => 30,
        ],
    ],
    'userContributions' => [
        [
            'id'            => 'demo-user-1',
            'name'          => 'Sample Saver',
            'email'         => 'sample.saver@example.com',
            'role'          => 'food_consumer',
            'food_saved'    => 26.5,
            'total_orders'  => 14,
            'total_spent'   => 282.50,
            'total_savings' => 310.20,
        ],
        [
            'id'            => 'demo-user-2',
            'name'          => 'Eco Provider',
            'email'         => 'eco.provider@example.com',
            'role'          => 'food_provider',
            'food_saved'    => 54.2,
            'total_orders'  => 0,
            'total_spent'   => 0,
            'total_savings' => 0,
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impact Tracking - MealDeal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation (consistent with sidebar layout) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">MealDeal Super Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
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
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-flag me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="bi bi-people me-2"></i>User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="listings.php">
                                <i class="bi bi-card-checklist me-2"></i>Content Moderation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leaderboard.php">
                                <i class="bi bi-trophy me-2"></i>Leaderboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="impact.php">
                                <i class="bi bi-graph-up me-2"></i>Impact Tracking
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pricing.php">
                                <i class="bi bi-tags me-2"></i>Pricing Control
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Impact Tracking</h1>
            <button class="btn btn-success" onclick="refreshData()">
                <i class="bi bi-arrow-clockwise"></i> Refresh Data
            </button>
        </div>

        <!-- Overall Impact Metrics -->
        <div class="impact-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="impact-metric">
                        <h3 id="totalFoodSaved">-</h3>
                        <p>Total Food Saved (kg)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="impact-metric">
                        <h3 id="totalSavings">-</h3>
                        <p>Total Savings (₱)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="impact-metric">
                        <h3 id="totalOrders">-</h3>
                        <p>Orders Completed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="impact-metric">
                        <h3 id="activeUsers">-</h3>
                        <p>Active Users</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Charts Section -->
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-3">Food Waste Reduction Over Time</h5>
                    <canvas id="foodWasteChart"></canvas>
                </div>

                <div class="chart-container">
                    <h5 class="mb-3">User Contributions by Category</h5>
                    <canvas id="contributionChart"></canvas>
                </div>
            </div>

            <!-- Top Contributors -->
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-3">Top Contributors</h5>
                    <div id="topContributors">
                        <div class="loading">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading contributors...</p>
                        </div>
                    </div>
                </div>

                <div class="chart-container">
                    <h5 class="mb-3">Impact Leaderboard</h5>
                    <div id="impactLeaderboard">
                        <div class="loading">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading leaderboard...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Individual User Contributions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-3">Individual User Contributions</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchUser" placeholder="Search users by name or email...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterRole">
                                <option value="">All Roles</option>
                                <option value="food_provider">Providers</option>
                                <option value="food_consumer">Consumers</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="sortBy">
                                <option value="food_saved">Sort by Food Saved</option>
                                <option value="total_orders">Sort by Orders</option>
                                <option value="total_spent">Sort by Amount Spent</option>
                            </select>
                        </div>
                    </div>
                    <div id="userContributions">
                        <div class="loading">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading user contributions...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </main>
        </div>
    </div>

    <!-- Use UMD build so Chart is available globally -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let foodWasteChart, contributionChart;
        let allUserContributions = [];
        // PHP-provided fallback data for when APIs return no data or fail
        const IMPACT_FALLBACK = <?php echo json_encode($impactFallback, JSON_UNESCAPED_UNICODE); ?>;

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadImpactData();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('searchUser').addEventListener('input', filterUserContributions);
            document.getElementById('filterRole').addEventListener('change', filterUserContributions);
            document.getElementById('sortBy').addEventListener('change', filterUserContributions);
        }

        async function loadImpactData() {
            try {
                // Load overall stats
                const statsResponse = await fetch('api/get_comprehensive_stats.php');
                const statsData = await statsResponse.json().catch(() => ({ success: false }));
                
                let statsPayload = (statsData && statsData.success && statsData.data)
                    ? statsData.data
                    : IMPACT_FALLBACK.stats;

                if (!(statsData && statsData.success)) {
                    showError('Failed to load impact stats. Showing sample data.');
                }

                updateOverallMetrics(statsPayload);

                // Load user contributions
                await loadUserContributions();
                
                // Initialize charts with safe data
                initializeCharts(statsPayload);
                
            } catch (error) {
                console.error('Error loading impact data:', error);
                // Full fallback when everything fails
                updateOverallMetrics(IMPACT_FALLBACK.stats);
                allUserContributions = IMPACT_FALLBACK.userContributions;
                displayUserContributions(allUserContributions);
                updateTopContributors(allUserContributions);
                initializeCharts(IMPACT_FALLBACK.stats);
                showError('Failed to load impact data from server. Showing sample data.');
            }
        }

        function updateOverallMetrics(data) {
            document.getElementById('totalFoodSaved').textContent = 
                data.orders?.total_food_saved ? data.orders.total_food_saved.toFixed(1) : '0';
            document.getElementById('totalSavings').textContent = 
                data.orders?.total_savings ? '₱' + data.orders.total_savings.toFixed(2) : '₱0';
            document.getElementById('totalOrders').textContent = 
                data.orders?.completed_orders || '0';
            document.getElementById('activeUsers').textContent = 
                data.users?.total_users || '0';
        }

        async function loadUserContributions() {
            try {
                const response = await fetch('api/get_user_contributions.php');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                
                if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                    // Limit to 50 entries on the client for faster rendering
                    allUserContributions = data.data.slice(0, 50);
                    displayUserContributions(allUserContributions);
                    updateTopContributors(allUserContributions);
                } else {
                    throw new Error(data.error || 'No contribution data available');
                }
            } catch (error) {
                console.error('Error loading user contributions:', error);
                // Use PHP fallback data so the section is never empty
                allUserContributions = IMPACT_FALLBACK.userContributions;
                displayUserContributions(allUserContributions);
                updateTopContributors(allUserContributions);
                document.getElementById('userContributions').insertAdjacentHTML(
                    'afterbegin',
                    '<div class="alert alert-warning mb-3">Live user contributions could not be loaded. Showing sample data instead.</div>'
                );
            }
        }

        function displayUserContributions(contributions) {
            const container = document.getElementById('userContributions');
            
            if (contributions.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No user contributions found.</div>';
                return;
            }

            const html = contributions.map((user, index) => `
                <div class="user-contribution">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-1">${user.name}</h6>
                            <small class="text-muted">${user.email}</small>
                            <br>
                            <span class="badge bg-${user.role === 'food_provider' ? 'primary' : 'success'}">${user.role}</span>
                        </div>
                        <div class="col-md-2">
                            <div class="contribution-badge">
                                ${user.food_saved.toFixed(1)} kg saved
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="contribution-badge">
                                ${user.total_orders} orders
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="contribution-badge">
                                ₱${user.total_spent.toFixed(2)} spent
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="contribution-badge">
                                ₱${user.total_savings.toFixed(2)} saved
                            </div>
                        </div>
                        <div class="col-md-1">
                            <span class="badge bg-secondary">#${index + 1}</span>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function filterUserContributions() {
            const searchTerm = document.getElementById('searchUser').value.toLowerCase();
            const roleFilter = document.getElementById('filterRole').value;
            const sortBy = document.getElementById('sortBy').value;

            let filtered = allUserContributions.filter(user => {
                const matchesSearch = user.name.toLowerCase().includes(searchTerm) || 
                                    user.email.toLowerCase().includes(searchTerm);
                const matchesRole = !roleFilter || user.role === roleFilter;
                return matchesSearch && matchesRole;
            });

            // Sort the filtered results
            filtered.sort((a, b) => b[sortBy] - a[sortBy]);

            displayUserContributions(filtered);
        }

        function updateTopContributors(contributions) {
            // Always sort by food_saved descending to show best contributors first
            const sorted = [...contributions].sort((a, b) => b.food_saved - a.food_saved);
            const topContributors = sorted.slice(0, 5);
            const container = document.getElementById('topContributors');
            
            const html = topContributors.map((user, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>${user.name}</strong>
                        <br>
                        <small class="text-muted">${user.food_saved.toFixed(1)} kg saved</small>
                    </div>
                    <span class="badge bg-success">#${index + 1}</span>
                </div>
            `).join('');

            container.innerHTML = html;

            // Also render a simple leaderboard list (top 10 contributors)
            const leaderboard = sorted.slice(0, 10).map((u, i) => `
                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                    <div><strong>#${i + 1}</strong> ${u.name}</div>
                    <small class="text-muted">${u.food_saved.toFixed(1)} kg</small>
                </div>
            `).join('');
            document.getElementById('impactLeaderboard').innerHTML = leaderboard || '<p class="text-muted">No leaderboard data.</p>';
        }

        function initializeCharts(data) {
            // Derive simple weekly trend from total_food_saved if available
            const totalFood = data.orders?.total_food_saved || 0;
            const weeklyPoints = 4;
            const perWeek = totalFood > 0 ? (totalFood / weeklyPoints) : 0;
            const foodSeries = Array(weeklyPoints).fill(perWeek);

            // Food Waste Chart
            const foodWasteCtx = document.getElementById('foodWasteChart').getContext('2d');
            foodWasteChart = new Chart(foodWasteCtx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Food Saved (kg)',
                        data: foodSeries,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Contribution Chart
            const contributionCtx = document.getElementById('contributionChart').getContext('2d');
            contributionChart = new Chart(contributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Providers', 'Consumers'],
                    datasets: [{
                        data: [data.users?.providers || 0, data.users?.consumers || 0],
                        backgroundColor: ['#28a745', '#20c997'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function refreshData() {
            document.querySelectorAll('.loading').forEach(el => {
                el.style.display = 'block';
            });
            loadImpactData();
        }

        function showError(message) {
            const container = document.querySelector('.container');
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.insertBefore(alert, container.firstChild);
        }
    </script>
</body>
</html>
