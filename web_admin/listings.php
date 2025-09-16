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
    <title>Content Moderation - MealDeal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .listing-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        .listing-card.flagged {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .listing-card.pending {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .listing-card.approved {
            border-left-color: #28a745;
            background-color: #f8fff8;
        }
        .listing-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .moderation-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        .flag-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .flag-item.high-priority {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .ai-analysis {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .quality-score {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .quality-score.excellent { color: #28a745; }
        .quality-score.good { color: #20c997; }
        .quality-score.fair { color: #ffc107; }
        .quality-score.poor { color: #dc3545; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">MealDeal Super Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="users.php">Users</a>
                <a class="nav-link active" href="listings.php">Moderation</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link" href="pricing.php">Pricing</a>
                <a class="nav-link" href="impact.php">Impact</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Content Moderation</h1>
            <div>
                <button class="btn btn-success me-2" onclick="refreshListings()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-warning" onclick="runAutomatedScan()">
                    <i class="bi bi-robot"></i> Run AI Scan
                </button>
            </div>
        </div>

        <!-- Moderation Statistics -->
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
                        <h3 id="pendingReview">-</h3>
                        <p class="mb-0">Pending Review</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="flaggedListings">-</h3>
                        <p class="mb-0">Flagged Listings</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="aiFlags">-</h3>
                        <p class="mb-0">AI Detected Issues</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchListings" placeholder="Search listings...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="pending">Pending Review</option>
                    <option value="approved">Approved</option>
                    <option value="flagged">Flagged</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterPriority">
                    <option value="">All Priority</option>
                    <option value="high">High Priority</option>
                    <option value="medium">Medium Priority</option>
                    <option value="low">Low Priority</option>
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
                <select class="form-select" id="sortBy">
                    <option value="created_at">Sort by Date</option>
                    <option value="priority">Sort by Priority</option>
                    <option value="quality_score">Sort by Quality</option>
                    <option value="reports">Sort by Reports</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Listings Review Panel -->
            <div class="col-lg-8">
                <div id="listingsList">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading listings for review...</p>
                    </div>
                </div>
            </div>

            <!-- Moderation Tools Panel -->
            <div class="col-lg-4">
                <!-- AI Analysis Results -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-robot"></i> AI Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div id="aiAnalysisResults">
                            <div class="text-center py-3">
                                <div class="spinner-border text-info" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 small">Running AI analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Flags -->
                <div class="card mt-3">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-flag"></i> Recent Flags</h5>
                    </div>
                    <div class="card-body">
                        <div id="recentFlags">
                            <div class="text-center py-3">
                                <div class="spinner-border text-warning" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 small">Loading flags...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success" onclick="bulkApproveListings()">
                                <i class="bi bi-check-circle"></i> Bulk Approve Selected
                            </button>
                            <button class="btn btn-outline-danger" onclick="bulkRejectListings()">
                                <i class="bi bi-x-circle"></i> Bulk Reject Selected
                            </button>
                            <button class="btn btn-outline-warning" onclick="bulkFlagListings()">
                                <i class="bi bi-flag"></i> Bulk Flag Selected
                            </button>
                            <button class="btn btn-outline-info" onclick="exportModerationReport()">
                                <i class="bi bi-download"></i> Export Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listing Details Modal -->
    <div class="modal fade" id="listingDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Listing Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="listingDetailsContent">
                    <!-- Listing details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="approveListing()">Approve</button>
                    <button type="button" class="btn btn-danger" onclick="rejectListing()">Reject</button>
                    <button type="button" class="btn btn-warning" onclick="flagListing()">Flag for Review</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allListings = [];
        let selectedListings = new Set();

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadListings();
            loadAIAnalysis();
            loadRecentFlags();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('searchListings').addEventListener('input', filterListings);
            document.getElementById('filterStatus').addEventListener('change', filterListings);
            document.getElementById('filterPriority').addEventListener('change', filterListings);
            document.getElementById('filterCategory').addEventListener('change', filterListings);
            document.getElementById('sortBy').addEventListener('change', filterListings);
        }

        async function loadListings() {
            try {
                const response = await fetch('api/get_listings.php?limit=100');
                const data = await response.json();
                
                if (data.success) {
                    allListings = data.data;
                    displayListings(allListings);
                    updateModerationStats(data.data);
                }
            } catch (error) {
                console.error('Error loading listings:', error);
                document.getElementById('listingsList').innerHTML = 
                    '<div class="alert alert-danger">Failed to load listings.</div>';
            }
        }

        async function loadAIAnalysis() {
            try {
                const response = await fetch('api/get_ai_analysis.php');
                const data = await response.json();
                
                if (data.success) {
                    displayAIAnalysis(data.data);
                }
            } catch (error) {
                console.error('Error loading AI analysis:', error);
                document.getElementById('aiAnalysisResults').innerHTML = 
                    '<div class="alert alert-info">AI analysis not available.</div>';
            }
        }

        async function loadRecentFlags() {
            try {
                const response = await fetch('api/get_recent_flags.php');
                const data = await response.json();
                
                if (data.success) {
                    displayRecentFlags(data.data);
                }
            } catch (error) {
                console.error('Error loading recent flags:', error);
                document.getElementById('recentFlags').innerHTML = 
                    '<div class="alert alert-warning">Failed to load recent flags.</div>';
            }
        }

        function displayListings(listings) {
            const container = document.getElementById('listingsList');
            
            if (listings.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No listings found.</div>';
                return;
            }

            const html = listings.map(listing => `
                <div class="listing-card ${getListingStatusClass(listing.status)}">
                    <div class="row align-items-center">
                        <div class="col-md-1">
                            <input type="checkbox" class="form-check-input" value="${listing.id}" onchange="toggleListingSelection('${listing.id}')">
                        </div>
                        <div class="col-md-2">
                            ${listing.images && listing.images.length > 0 ? 
                                `<img src="${listing.images[0]}" class="listing-image" alt="Listing image">` :
                                `<div class="listing-image bg-light d-flex align-items-center justify-content-center">
                                    <i class="bi bi-image text-muted"></i>
                                </div>`
                            }
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-1">${listing.title}</h6>
                            <p class="small text-muted mb-1">${listing.description.substring(0, 100)}...</p>
                            <span class="badge bg-${getCategoryColor(listing.category)}">${listing.category}</span>
                            <span class="badge bg-${getStatusColor(listing.status)} ms-1">${listing.status}</span>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <div class="quality-score ${getQualityScoreClass(listing.quality_score || 85)}">
                                    ${listing.quality_score || 85}%
                                </div>
                                <small class="text-muted">Quality Score</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="moderation-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewListingDetails('${listing.id}')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="approveListing('${listing.id}')">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="rejectListing('${listing.id}')">
                                    <i class="bi bi-x"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="flagListing('${listing.id}')">
                                    <i class="bi bi-flag"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> ${new Date(listing.created_at).toLocaleDateString()}
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI Analysis Results -->
                    ${listing.ai_analysis ? `
                        <div class="ai-analysis mt-3">
                            <h6><i class="bi bi-robot"></i> AI Analysis</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Image Quality:</strong> ${listing.ai_analysis.image_quality || 'Good'}</p>
                                    <p class="mb-1"><strong>Content Safety:</strong> ${listing.ai_analysis.content_safety || 'Safe'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Price Reasonableness:</strong> ${listing.ai_analysis.price_reasonable || 'Yes'}</p>
                                    <p class="mb-1"><strong>Issues Detected:</strong> ${listing.ai_analysis.issues_detected || 'None'}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function displayAIAnalysis(analysis) {
            const container = document.getElementById('aiAnalysisResults');
            
            const html = `
                <div class="mb-3">
                    <h6>Overall Analysis</h6>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" style="width: ${analysis.overall_quality || 85}%"></div>
                    </div>
                    <small class="text-muted">Overall Quality: ${analysis.overall_quality || 85}%</small>
                </div>
                <div class="mb-3">
                    <h6>Issues Detected</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success"></i> Image Quality: ${analysis.image_issues || 0} issues</li>
                        <li><i class="bi bi-check-circle text-success"></i> Content Safety: ${analysis.safety_issues || 0} issues</li>
                        <li><i class="bi bi-check-circle text-success"></i> Price Compliance: ${analysis.pricing_issues || 0} issues</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6>Recommendations</h6>
                    <p class="small">${analysis.recommendations || 'No specific recommendations at this time.'}</p>
                </div>
            `;
            
            container.innerHTML = html;
        }

        function displayRecentFlags(flags) {
            const container = document.getElementById('recentFlags');
            
            if (flags.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No recent flags.</div>';
                return;
            }

            const html = flags.map(flag => `
                <div class="flag-item ${flag.priority === 'high' ? 'high-priority' : ''}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-1">${flag.title}</h6>
                        <span class="badge bg-${flag.priority === 'high' ? 'danger' : 'warning'}">${flag.priority}</span>
                    </div>
                    <p class="small mb-2">${flag.description}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">${flag.created_at}</small>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewFlag('${flag.id}')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="resolveFlag('${flag.id}')">
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
            const statusFilter = document.getElementById('filterStatus').value;
            const priorityFilter = document.getElementById('filterPriority').value;
            const categoryFilter = document.getElementById('filterCategory').value;
            const sortBy = document.getElementById('sortBy').value;

            let filtered = allListings.filter(listing => {
                const matchesSearch = listing.title.toLowerCase().includes(searchTerm) || 
                                    listing.description.toLowerCase().includes(searchTerm);
                const matchesStatus = !statusFilter || listing.status === statusFilter;
                const matchesPriority = !priorityFilter || (listing.priority || 'medium') === priorityFilter;
                const matchesCategory = !categoryFilter || listing.category === categoryFilter;
                return matchesSearch && matchesStatus && matchesPriority && matchesCategory;
            });

            // Sort the filtered results
            filtered.sort((a, b) => {
                if (sortBy === 'created_at') {
                    return new Date(b.created_at) - new Date(a.created_at);
                } else if (sortBy === 'quality_score') {
                    return (b.quality_score || 0) - (a.quality_score || 0);
                } else if (sortBy === 'priority') {
                    const priorityOrder = { 'high': 3, 'medium': 2, 'low': 1 };
                    return (priorityOrder[b.priority] || 2) - (priorityOrder[a.priority] || 2);
                }
                return 0;
            });

            displayListings(filtered);
        }

        function clearFilters() {
            document.getElementById('searchListings').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('sortBy').value = 'created_at';
            displayListings(allListings);
        }

        function updateModerationStats(listings) {
            const totalListings = listings.length;
            const pendingReview = listings.filter(l => l.status === 'pending').length;
            const flaggedListings = listings.filter(l => l.status === 'flagged').length;
            const aiFlags = listings.filter(l => l.ai_analysis && l.ai_analysis.issues_detected > 0).length;
            
            document.getElementById('totalListings').textContent = totalListings;
            document.getElementById('pendingReview').textContent = pendingReview;
            document.getElementById('flaggedListings').textContent = flaggedListings;
            document.getElementById('aiFlags').textContent = aiFlags;
        }

        function getListingStatusClass(status) {
            switch(status) {
                case 'flagged': return 'flagged';
                case 'pending': return 'pending';
                case 'approved': return 'approved';
                default: return '';
            }
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

        function getStatusColor(status) {
            switch(status) {
                case 'approved': return 'success';
                case 'flagged': return 'danger';
                case 'pending': return 'warning';
                case 'rejected': return 'secondary';
                default: return 'light';
            }
        }

        function getQualityScoreClass(score) {
            if (score >= 90) return 'excellent';
            if (score >= 75) return 'good';
            if (score >= 60) return 'fair';
            return 'poor';
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
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(listing.status)}">${listing.status}</span></p>
                            <p><strong>Price:</strong> $${listing.discounted_price} (Original: $${listing.original_price})</p>
                            <p><strong>Quantity:</strong> ${listing.quantity}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Images</h6>
                            ${listing.images && listing.images.length > 0 ? 
                                listing.images.map(img => `<img src="${img}" class="img-fluid mb-2" style="max-width: 200px;">`).join('') :
                                '<p class="text-muted">No images available</p>'
                            }
                        </div>
                    </div>
                    ${listing.ai_analysis ? `
                        <div class="ai-analysis mt-3">
                            <h6><i class="bi bi-robot"></i> AI Analysis Results</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Image Quality:</strong> ${listing.ai_analysis.image_quality || 'Good'}</p>
                                    <p><strong>Content Safety:</strong> ${listing.ai_analysis.content_safety || 'Safe'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Price Reasonableness:</strong> ${listing.ai_analysis.price_reasonable || 'Yes'}</p>
                                    <p><strong>Issues Detected:</strong> ${listing.ai_analysis.issues_detected || 'None'}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
                new bootstrap.Modal(document.getElementById('listingDetailsModal')).show();
            }
        }

        function approveListing(listingId) {
            if (confirm('Are you sure you want to approve this listing?')) {
                // Implement approve listing API call
                console.log('Approving listing:', listingId);
                loadListings();
            }
        }

        function rejectListing(listingId) {
            if (confirm('Are you sure you want to reject this listing?')) {
                // Implement reject listing API call
                console.log('Rejecting listing:', listingId);
                loadListings();
            }
        }

        function flagListing(listingId) {
            if (confirm('Are you sure you want to flag this listing for review?')) {
                // Implement flag listing API call
                console.log('Flagging listing:', listingId);
                loadListings();
            }
        }

        function bulkApproveListings() {
            if (selectedListings.size === 0) {
                alert('Please select listings to approve.');
                return;
            }
            
            if (confirm(`Are you sure you want to approve ${selectedListings.size} selected listings?`)) {
                // Implement bulk approve API call
                console.log('Bulk approving listings:', Array.from(selectedListings));
                selectedListings.clear();
                loadListings();
            }
        }

        function bulkRejectListings() {
            if (selectedListings.size === 0) {
                alert('Please select listings to reject.');
                return;
            }
            
            if (confirm(`Are you sure you want to reject ${selectedListings.size} selected listings?`)) {
                // Implement bulk reject API call
                console.log('Bulk rejecting listings:', Array.from(selectedListings));
                selectedListings.clear();
                loadListings();
            }
        }

        function bulkFlagListings() {
            if (selectedListings.size === 0) {
                alert('Please select listings to flag.');
                return;
            }
            
            if (confirm(`Are you sure you want to flag ${selectedListings.size} selected listings?`)) {
                // Implement bulk flag API call
                console.log('Bulk flagging listings:', Array.from(selectedListings));
                selectedListings.clear();
                loadListings();
            }
        }

        function runAutomatedScan() {
            // Implement automated AI scan
            console.log('Running automated AI scan...');
            loadAIAnalysis();
        }

        function exportModerationReport() {
            // Implement export moderation report
            console.log('Exporting moderation report...');
        }

        function refreshListings() {
            loadListings();
            loadAIAnalysis();
            loadRecentFlags();
        }
    </script>
</body>
</html>
