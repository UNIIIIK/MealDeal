<?php
session_start();
require_once 'includes/auth.php';
if (!isAdminLoggedIn()) { header('Location: login.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impact Tracking - MealDeal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .impact-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        .impact-metric {
            text-align: center;
            padding: 1rem;
        }
        .impact-metric h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .impact-metric p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .user-contribution {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        .contribution-badge {
            background: #e8f5e8;
            color: #28a745;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .loading {
            text-align: center;
            padding: 3rem;
        }
        .spinner-border {
            color: #28a745;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">MealDeal Super Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="users.php">Users</a>
                <a class="nav-link" href="listings.php">Listings</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link" href="pricing.php">Pricing</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
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
                    <canvas id="foodWasteChart" width="400" height="200"></canvas>
                </div>

                <div class="chart-container">
                    <h5 class="mb-3">User Contributions by Category</h5>
                    <canvas id="contributionChart" width="400" height="200"></canvas>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script>
        let foodWasteChart, contributionChart;
        let allUserContributions = [];

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
                const statsData = await statsResponse.json();
                
                if (statsData.success) {
                    updateOverallMetrics(statsData.data);
                }

                // Load user contributions
                await loadUserContributions();
                
                // Initialize charts
                initializeCharts(statsData.data);
                
            } catch (error) {
                console.error('Error loading impact data:', error);
                showError('Failed to load impact data. Please try again.');
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
                const data = await response.json();
                
                if (data.success) {
                    allUserContributions = data.data;
                    displayUserContributions(allUserContributions);
                    updateTopContributors(data.data);
                }
            } catch (error) {
                console.error('Error loading user contributions:', error);
                document.getElementById('userContributions').innerHTML = 
                    '<div class="alert alert-danger">Failed to load user contributions.</div>';
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
                                $${user.total_spent.toFixed(2)} spent
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="contribution-badge">
                                $${user.total_savings.toFixed(2)} saved
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
            const topContributors = contributions.slice(0, 5);
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
        }

        function initializeCharts(data) {
            // Food Waste Chart
            const foodWasteCtx = document.getElementById('foodWasteChart').getContext('2d');
            foodWasteChart = new Chart(foodWasteCtx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Food Saved (kg)',
                        data: [12, 19, 15, 25],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
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
