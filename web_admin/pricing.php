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
    <title>Pricing & Market Compliance - MealDeal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .compliance-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        .compliance-card.violation {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .compliance-card.warning {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .compliance-card.compliant {
            border-left-color: #28a745;
            background-color: #f8fff8;
        }
        .pricing-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .price-comparison {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        .discount-badge {
            font-size: 0.9rem;
            padding: 0.3rem 0.8rem;
        }
        .discount-badge.compliant {
            background-color: #d4edda;
            color: #155724;
        }
        .discount-badge.violation {
            background-color: #f8d7da;
            color: #721c24;
        }
        .discount-badge.warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .compliance-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .alert-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .alert-item.high-priority {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-item.resolved {
            background: #d4edda;
            border-color: #c3e6cb;
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
                <a class="nav-link" href="listings.php">Moderation</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link active" href="pricing.php">Pricing</a>
                <a class="nav-link" href="impact.php">Impact</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Pricing & Market Compliance</h1>
            <div>
                <button class="btn btn-success me-2" onclick="refreshPricingData()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-warning" onclick="runComplianceScan()">
                    <i class="bi bi-shield-check"></i> Run Compliance Scan
                </button>
            </div>
        </div>

        <!-- Compliance Statistics -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="totalListings">-</h3>
                        <p class="mb-0">Total Listings</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="compliantListings">-</h3>
                        <p class="mb-0">Compliant Listings</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="violations">-</h3>
                        <p class="mb-0">Pricing Violations</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="averageDiscount">-</h3>
                        <p class="mb-0">Average Discount</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Charts -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">Discount Distribution</h5>
                    <canvas id="discountChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">Compliance Status</h5>
                    <canvas id="complianceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchListings" placeholder="Search listings...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterCompliance">
                    <option value="">All Compliance</option>
                    <option value="compliant">Compliant</option>
                    <option value="warning">Warning</option>
                    <option value="violation">Violation</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterCategory">
                    <option value="">All Categories</option>
                    <option value="main_dish">Main Dishes</option>
                    <option value="side_dish">Side Dishes</option>
                    <option value="dessert">Desserts</option>
                    <option value="beverage">Beverages</option>
                    <option value="pickup">Pickup Items</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterDiscountRange">
                    <option value="">All Discounts</option>
                    <option value="0-50">0-50%</option>
                    <option value="50-60">50-60%</option>
                    <option value="60-70">60-70%</option>
                    <option value="70+">70%+</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="sortBy">
                    <option value="discount_percentage">Sort by Discount</option>
                    <option value="created_at">Sort by Date</option>
                    <option value="price_violation">Sort by Violations</option>
                    <option value="original_price">Sort by Price</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Listings Compliance Panel -->
            <div class="col-lg-8">
                <div id="listingsList">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading pricing compliance data...</p>
                    </div>
                </div>
            </div>

            <!-- Compliance Tools Panel -->
            <div class="col-lg-4">
                <!-- Pricing Alerts -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Pricing Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div id="pricingAlerts">
                            <div class="text-center py-3">
                                <div class="spinner-border text-warning" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 small">Loading alerts...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compliance Settings -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Compliance Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="minDiscount" class="form-label">Minimum Discount (%)</label>
                            <input type="number" class="form-control" id="minDiscount" value="50" min="0" max="100">
                            <small class="text-muted">Current: 50% minimum discount required</small>
                        </div>
                        <div class="mb-3">
                            <label for="maxDiscount" class="form-label">Maximum Discount (%)</label>
                            <input type="number" class="form-control" id="maxDiscount" value="90" min="0" max="100">
                            <small class="text-muted">Current: 90% maximum discount allowed</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoApprove" checked>
                                <label class="form-check-label" for="autoApprove">
                                    Auto-approve compliant listings
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoFlag" checked>
                                <label class="form-check-label" for="autoFlag">
                                    Auto-flag violations
                                </label>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100" onclick="updateComplianceSettings()">
                            <i class="bi bi-save"></i> Update Settings
                        </button>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success" onclick="bulkApproveCompliant()">
                                <i class="bi bi-check-circle"></i> Approve All Compliant
                            </button>
                            <button class="btn btn-outline-warning" onclick="bulkFlagViolations()">
                                <i class="bi bi-flag"></i> Flag All Violations
                            </button>
                            <button class="btn btn-outline-info" onclick="generateComplianceReport()">
                                <i class="bi bi-download"></i> Generate Report
                            </button>
                            <button class="btn btn-outline-secondary" onclick="exportPricingData()">
                                <i class="bi bi-table"></i> Export Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listing Details Modal -->
    <div class="modal fade" id="listingDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pricing Compliance Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="listingDetailsContent">
                    <!-- Listing details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="approveListing()">Approve</button>
                    <button type="button" class="btn btn-warning" onclick="flagListing()">Flag for Review</button>
                    <button type="button" class="btn btn-danger" onclick="rejectListing()">Reject</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script>
        let allListings = [];
        let selectedListings = new Set();
        let discountChart, complianceChart;

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPricingData();
            loadPricingAlerts();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('searchListings').addEventListener('input', filterListings);
            document.getElementById('filterCompliance').addEventListener('change', filterListings);
            document.getElementById('filterCategory').addEventListener('change', filterListings);
            document.getElementById('filterDiscountRange').addEventListener('change', filterListings);
            document.getElementById('sortBy').addEventListener('change', filterListings);
        }

        async function loadPricingData() {
            try {
                const response = await fetch('api/get_listings.php?limit=100');
                const data = await response.json();
                
                if (data.success) {
                    allListings = data.data.map(listing => {
                        // Calculate discount percentage
                        const originalPrice = parseFloat(listing.original_price) || 0;
                        const discountedPrice = parseFloat(listing.discounted_price) || 0;
                        const discountPercentage = originalPrice > 0 ? 
                            Math.round(((originalPrice - discountedPrice) / originalPrice) * 100) : 0;
                        
                        // Determine compliance status
                        let complianceStatus = 'compliant';
                        if (discountPercentage < 50) {
                            complianceStatus = 'violation';
                        } else if (discountPercentage < 60) {
                            complianceStatus = 'warning';
                        }
                        
                        return {
                            ...listing,
                            discount_percentage: discountPercentage,
                            compliance_status: complianceStatus,
                            price_violation: discountPercentage < 50 ? 1 : 0
                        };
                    });
                    
                    displayListings(allListings);
                    updateComplianceStats(allListings);
                    initializeCharts(allListings);
                }
            } catch (error) {
                console.error('Error loading pricing data:', error);
                document.getElementById('listingsList').innerHTML = 
                    '<div class="alert alert-danger">Failed to load pricing data.</div>';
            }
        }

        async function loadPricingAlerts() {
            try {
                const response = await fetch('api/get_pricing_alerts.php');
                const data = await response.json();
                
                if (data.success) {
                    displayPricingAlerts(data.data);
                }
            } catch (error) {
                console.error('Error loading pricing alerts:', error);
                document.getElementById('pricingAlerts').innerHTML = 
                    '<div class="alert alert-warning">Failed to load pricing alerts.</div>';
            }
        }

        function displayListings(listings) {
            const container = document.getElementById('listingsList');
            
            if (listings.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No listings found.</div>';
                return;
            }

            const html = listings.map(listing => `
                <div class="compliance-card ${listing.compliance_status}">
                    <div class="row align-items-center">
                        <div class="col-md-1">
                            <input type="checkbox" class="form-check-input" value="${listing.id}" onchange="toggleListingSelection('${listing.id}')">
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-1">${listing.title}</h6>
                            <p class="small text-muted mb-1">${listing.description.substring(0, 80)}...</p>
                            <span class="badge bg-${getCategoryColor(listing.category)}">${listing.category}</span>
                        </div>
                        <div class="col-md-4">
                            <div class="pricing-info">
                                <div>
                                    <strong>Original:</strong> $${listing.original_price}<br>
                                    <strong>Discounted:</strong> $${listing.discounted_price}
                                </div>
                                <div class="price-comparison">
                                    <div class="discount-badge ${listing.compliance_status}">
                                        ${listing.discount_percentage}% OFF
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        ${getComplianceMessage(listing.compliance_status, listing.discount_percentage)}
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="compliance-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewListingDetails('${listing.id}')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="approveListing('${listing.id}')">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="flagListing('${listing.id}')">
                                    <i class="bi bi-flag"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="rejectListing('${listing.id}')">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> ${new Date(listing.created_at).toLocaleDateString()}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function displayPricingAlerts(alerts) {
            const container = document.getElementById('pricingAlerts');
            
            if (alerts.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No pricing alerts.</div>';
                return;
            }

            const html = alerts.map(alert => `
                <div class="alert-item ${alert.priority === 'high' ? 'high-priority' : ''} ${alert.status === 'resolved' ? 'resolved' : ''}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-1">${alert.title}</h6>
                        <span class="badge bg-${alert.priority === 'high' ? 'danger' : 'warning'}">${alert.priority}</span>
                    </div>
                    <p class="small mb-2">${alert.description}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">${alert.created_at}</small>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewAlert('${alert.id}')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="resolveAlert('${alert.id}')">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function filterListings() {
            const searchTerm = document.getElementById('searchListings').value.toLowerCase();
            const complianceFilter = document.getElementById('filterCompliance').value;
            const categoryFilter = document.getElementById('filterCategory').value;
            const discountRangeFilter = document.getElementById('filterDiscountRange').value;
            const sortBy = document.getElementById('sortBy').value;

            let filtered = allListings.filter(listing => {
                const matchesSearch = listing.title.toLowerCase().includes(searchTerm) || 
                                    listing.description.toLowerCase().includes(searchTerm);
                const matchesCompliance = !complianceFilter || listing.compliance_status === complianceFilter;
                const matchesCategory = !categoryFilter || listing.category === categoryFilter;
                
                let matchesDiscountRange = true;
                if (discountRangeFilter) {
                    const [min, max] = discountRangeFilter.split('-').map(Number);
                    if (max) {
                        matchesDiscountRange = listing.discount_percentage >= min && listing.discount_percentage <= max;
                    } else {
                        matchesDiscountRange = listing.discount_percentage >= min;
                    }
                }
                
                return matchesSearch && matchesCompliance && matchesCategory && matchesDiscountRange;
            });

            // Sort the filtered results
            filtered.sort((a, b) => {
                if (sortBy === 'discount_percentage') {
                    return b.discount_percentage - a.discount_percentage;
                } else if (sortBy === 'created_at') {
                    return new Date(b.created_at) - new Date(a.created_at);
                } else if (sortBy === 'price_violation') {
                    return b.price_violation - a.price_violation;
                } else if (sortBy === 'original_price') {
                    return b.original_price - a.original_price;
                }
                return 0;
            });

            displayListings(filtered);
        }

        function clearFilters() {
            document.getElementById('searchListings').value = '';
            document.getElementById('filterCompliance').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterDiscountRange').value = '';
            document.getElementById('sortBy').value = 'discount_percentage';
            displayListings(allListings);
        }

        function updateComplianceStats(listings) {
            const totalListings = listings.length;
            const compliantListings = listings.filter(l => l.compliance_status === 'compliant').length;
            const violations = listings.filter(l => l.compliance_status === 'violation').length;
            const averageDiscount = listings.length > 0 ? 
                Math.round(listings.reduce((sum, l) => sum + l.discount_percentage, 0) / listings.length) : 0;
            
            document.getElementById('totalListings').textContent = totalListings;
            document.getElementById('compliantListings').textContent = compliantListings;
            document.getElementById('violations').textContent = violations;
            document.getElementById('averageDiscount').textContent = averageDiscount + '%';
        }

        function initializeCharts(listings) {
            // Discount Distribution Chart
            const discountCtx = document.getElementById('discountChart').getContext('2d');
            const discountRanges = {
                '0-50%': listings.filter(l => l.discount_percentage < 50).length,
                '50-60%': listings.filter(l => l.discount_percentage >= 50 && l.discount_percentage < 60).length,
                '60-70%': listings.filter(l => l.discount_percentage >= 60 && l.discount_percentage < 70).length,
                '70%+': listings.filter(l => l.discount_percentage >= 70).length
            };
            
            discountChart = new Chart(discountCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(discountRanges),
                    datasets: [{
                        label: 'Number of Listings',
                        data: Object.values(discountRanges),
                        backgroundColor: ['#dc3545', '#ffc107', '#20c997', '#28a745'],
                        borderWidth: 0
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

            // Compliance Status Chart
            const complianceCtx = document.getElementById('complianceChart').getContext('2d');
            const complianceData = {
                'Compliant': listings.filter(l => l.compliance_status === 'compliant').length,
                'Warning': listings.filter(l => l.compliance_status === 'warning').length,
                'Violation': listings.filter(l => l.compliance_status === 'violation').length
            };
            
            complianceChart = new Chart(complianceCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(complianceData),
                    datasets: [{
                        data: Object.values(complianceData),
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
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

        function getCategoryColor(category) {
            switch(category) {
                case 'main_dish': return 'primary';
                case 'side_dish': return 'success';
                case 'dessert': return 'warning';
                case 'beverage': return 'info';
                case 'pickup': return 'secondary';
                default: return 'light';
            }
        }

        function getComplianceMessage(status, discount) {
            switch(status) {
                case 'violation':
                    return `Violation: ${discount}% < 50% minimum`;
                case 'warning':
                    return `Warning: ${discount}% close to minimum`;
                case 'compliant':
                    return `Compliant: ${discount}% meets requirements`;
                default:
                    return 'Unknown status';
            }
        }

        function toggleListingSelection(listingId) {
            if (selectedListings.has(listingId)) {
                selectedListings.delete(listingId);
            } else {
                selectedListings.add(listingId);
            }
        }

        function viewListingDetails(listingId) {
            const listing = allListings.find(l => l.id === listingId);
            if (listing) {
                document.getElementById('listingDetailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Listing Information</h6>
                            <p><strong>Title:</strong> ${listing.title}</p>
                            <p><strong>Description:</strong> ${listing.description}</p>
                            <p><strong>Category:</strong> <span class="badge bg-${getCategoryColor(listing.category)}">${listing.category}</span></p>
                            <p><strong>Quantity:</strong> ${listing.quantity}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Pricing Details</h6>
                            <div class="price-comparison">
                                <p><strong>Original Price:</strong> $${listing.original_price}</p>
                                <p><strong>Discounted Price:</strong> $${listing.discounted_price}</p>
                                <p><strong>Discount Percentage:</strong> ${listing.discount_percentage}%</p>
                                <p><strong>Compliance Status:</strong> 
                                    <span class="badge bg-${listing.compliance_status === 'compliant' ? 'success' : listing.compliance_status === 'warning' ? 'warning' : 'danger'}">
                                        ${listing.compliance_status.toUpperCase()}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                `;
                new bootstrap.Modal(document.getElementById('listingDetailsModal')).show();
            }
        }

        function approveListing(listingId) {
            if (confirm('Are you sure you want to approve this listing?')) {
                console.log('Approving listing:', listingId);
                loadPricingData();
            }
        }

        function flagListing(listingId) {
            if (confirm('Are you sure you want to flag this listing for review?')) {
                console.log('Flagging listing:', listingId);
                loadPricingData();
            }
        }

        function rejectListing(listingId) {
            if (confirm('Are you sure you want to reject this listing?')) {
                console.log('Rejecting listing:', listingId);
                loadPricingData();
            }
        }

        function bulkApproveCompliant() {
            const compliantListings = allListings.filter(l => l.compliance_status === 'compliant');
            if (compliantListings.length === 0) {
                alert('No compliant listings to approve.');
                return;
            }
            
            if (confirm(`Are you sure you want to approve ${compliantListings.length} compliant listings?`)) {
                console.log('Bulk approving compliant listings');
                loadPricingData();
            }
        }

        function bulkFlagViolations() {
            const violations = allListings.filter(l => l.compliance_status === 'violation');
            if (violations.length === 0) {
                alert('No violations to flag.');
                return;
            }
            
            if (confirm(`Are you sure you want to flag ${violations.length} violations?`)) {
                console.log('Bulk flagging violations');
                loadPricingData();
            }
        }

        function updateComplianceSettings() {
            const minDiscount = document.getElementById('minDiscount').value;
            const maxDiscount = document.getElementById('maxDiscount').value;
            const autoApprove = document.getElementById('autoApprove').checked;
            const autoFlag = document.getElementById('autoFlag').checked;
            
            console.log('Updating compliance settings:', {
                minDiscount, maxDiscount, autoApprove, autoFlag
            });
            
            alert('Compliance settings updated successfully!');
        }

        function runComplianceScan() {
            console.log('Running compliance scan...');
            loadPricingData();
        }

        function generateComplianceReport() {
            console.log('Generating compliance report...');
        }

        function exportPricingData() {
            console.log('Exporting pricing data...');
        }

        function refreshPricingData() {
            loadPricingData();
            loadPricingAlerts();
        }
    </script>
</body>
</html>
