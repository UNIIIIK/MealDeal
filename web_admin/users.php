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
    <title>User Management - MealDeal Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        .user-card.banned {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        .user-card.restricted {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .action-buttons {
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
        .dispute-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .dispute-item.resolved {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        .dispute-item.urgent {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">MealDeal Super Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="users.php">Users</a>
                <a class="nav-link" href="listings.php">Listings</a>
                <a class="nav-link" href="reports.php">Reports</a>
                <a class="nav-link" href="pricing.php">Pricing</a>
                <a class="nav-link" href="impact.php">Impact</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">User Management</h1>
            <div>
                <button class="btn btn-success me-2" onclick="refreshUsers()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-person-plus"></i> Create User
                </button>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="totalUsers">-</h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="activeUsers">-</h3>
                        <p class="mb-0">Active Users</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="bannedUsers">-</h3>
                        <p class="mb-0">Banned Users</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 id="pendingDisputes">-</h3>
                        <p class="mb-0">Pending Disputes</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchUsers" placeholder="Search users by name or email...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterRole">
                    <option value="">All Roles</option>
                    <option value="food_provider">Providers</option>
                    <option value="food_consumer">Consumers</option>
                    <option value="admin">Admins</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="banned">Banned</option>
                    <option value="restricted">Restricted</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="sortBy">
                    <option value="created_at">Sort by Date</option>
                    <option value="name">Sort by Name</option>
                    <option value="email">Sort by Email</option>
                    <option value="last_activity">Sort by Activity</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                    <i class="bi bi-x-circle"></i> Clear
                </button>
            </div>
        </div>

        <!-- Users List -->
        <div class="row">
            <div class="col-lg-8">
                <div id="usersList">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading users...</p>
                    </div>
                </div>
            </div>

            <!-- Disputes Panel -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Recent Disputes</h5>
                    </div>
                    <div class="card-body">
                        <div id="disputesList">
                            <div class="text-center py-3">
                                <div class="spinner-border text-warning" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 small">Loading disputes...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-danger" onclick="bulkBanUsers()">
                                <i class="bi bi-person-x"></i> Bulk Ban Selected
                            </button>
                            <button class="btn btn-outline-warning" onclick="bulkRestrictUsers()">
                                <i class="bi bi-person-dash"></i> Bulk Restrict Selected
                            </button>
                            <button class="btn btn-outline-success" onclick="bulkActivateUsers()">
                                <i class="bi bi-person-check"></i> Bulk Activate Selected
                            </button>
                            <button class="btn btn-outline-primary" onclick="exportUsers()">
                                <i class="bi bi-download"></i> Export User Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- User details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="editUser()">Edit User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm">
                        <div class="mb-3">
                            <label for="newUserName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="newUserName" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUserEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newUserEmail" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUserRole" class="form-label">Role</label>
                            <select class="form-select" id="newUserRole" required>
                                <option value="food_consumer">Consumer</option>
                                <option value="food_provider">Provider</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="newUserPhone" class="form-label">Phone (Optional)</label>
                            <input type="tel" class="form-control" id="newUserPhone">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="createUser()">Create User</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allUsers = [];
        let selectedUsers = new Set();

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            loadDisputes();
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('searchUsers').addEventListener('input', filterUsers);
            document.getElementById('filterRole').addEventListener('change', filterUsers);
            document.getElementById('filterStatus').addEventListener('change', filterUsers);
            document.getElementById('sortBy').addEventListener('change', filterUsers);
        }

        async function loadUsers() {
            try {
                const response = await fetch('api/get_users.php?limit=100');
                const data = await response.json();
                
                if (data.success) {
                    allUsers = data.data;
                    displayUsers(allUsers);
                    updateUserStats(data.data);
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('usersList').innerHTML = 
                    '<div class="alert alert-danger">Failed to load users.</div>';
            }
        }

        async function loadDisputes() {
            try {
                const response = await fetch('api/get_disputes.php');
                const data = await response.json();
                
                if (data.success) {
                    displayDisputes(data.data);
                }
            } catch (error) {
                console.error('Error loading disputes:', error);
                document.getElementById('disputesList').innerHTML = 
                    '<div class="alert alert-warning">Failed to load disputes.</div>';
            }
        }

        function displayUsers(users) {
            const container = document.getElementById('usersList');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No users found.</div>';
                return;
            }

            const html = users.map(user => `
                <div class="user-card ${user.status === 'banned' ? 'banned' : user.status === 'restricted' ? 'restricted' : ''}">
                    <div class="row align-items-center">
                        <div class="col-md-1">
                            <input type="checkbox" class="form-check-input" value="${user.id}" onchange="toggleUserSelection('${user.id}')">
                        </div>
                        <div class="col-md-3">
                            <h6 class="mb-1">${user.name}</h6>
                            <small class="text-muted">${user.email}</small>
                            <br>
                            <span class="badge bg-${getRoleColor(user.role)} status-badge">${user.role}</span>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-${getStatusColor(user.status)} status-badge">${user.status || 'active'}</span>
                            <br>
                            <small class="text-muted">${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'Unknown'}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">
                                <i class="bi bi-telephone"></i> ${user.phone || 'Not provided'}<br>
                                <i class="bi bi-geo-alt"></i> ${user.address || 'Not provided'}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewUserDetails('${user.id}')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="editUser('${user.id}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                ${user.status === 'banned' ? 
                                    `<button class="btn btn-sm btn-outline-success" onclick="activateUser('${user.id}')">
                                        <i class="bi bi-person-check"></i>
                                    </button>` :
                                    `<button class="btn btn-sm btn-outline-danger" onclick="banUser('${user.id}')">
                                        <i class="bi bi-person-x"></i>
                                    </button>`
                                }
                                <button class="btn btn-sm btn-outline-info" onclick="viewUserActivity('${user.id}')">
                                    <i class="bi bi-graph-up"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function displayDisputes(disputes) {
            const container = document.getElementById('disputesList');
            
            if (disputes.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No pending disputes.</div>';
                return;
            }

            const html = disputes.map(dispute => `
                <div class="dispute-item ${dispute.status === 'resolved' ? 'resolved' : dispute.priority === 'high' ? 'urgent' : ''}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-1">${dispute.title}</h6>
                        <span class="badge bg-${dispute.priority === 'high' ? 'danger' : 'warning'}">${dispute.priority}</span>
                    </div>
                    <p class="small mb-2">${dispute.description}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">By: ${dispute.reporter_name}</small>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewDispute('${dispute.id}')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="resolveDispute('${dispute.id}')">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        }

        function filterUsers() {
            const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
            const roleFilter = document.getElementById('filterRole').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const sortBy = document.getElementById('sortBy').value;

            let filtered = allUsers.filter(user => {
                const matchesSearch = user.name.toLowerCase().includes(searchTerm) || 
                                    user.email.toLowerCase().includes(searchTerm);
                const matchesRole = !roleFilter || user.role === roleFilter;
                const matchesStatus = !statusFilter || (user.status || 'active') === statusFilter;
                return matchesSearch && matchesRole && matchesStatus;
            });

            // Sort the filtered results
            filtered.sort((a, b) => {
                if (sortBy === 'name') {
                    return a.name.localeCompare(b.name);
                } else if (sortBy === 'email') {
                    return a.email.localeCompare(b.email);
                } else if (sortBy === 'created_at') {
                    return new Date(b.created_at) - new Date(a.created_at);
                }
                return 0;
            });

            displayUsers(filtered);
        }

        function clearFilters() {
            document.getElementById('searchUsers').value = '';
            document.getElementById('filterRole').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('sortBy').value = 'created_at';
            displayUsers(allUsers);
        }

        function updateUserStats(users) {
            const totalUsers = users.length;
            const activeUsers = users.filter(u => (u.status || 'active') === 'active').length;
            const bannedUsers = users.filter(u => u.status === 'banned').length;
            
            document.getElementById('totalUsers').textContent = totalUsers;
            document.getElementById('activeUsers').textContent = activeUsers;
            document.getElementById('bannedUsers').textContent = bannedUsers;
            document.getElementById('pendingDisputes').textContent = '0'; // Will be updated by disputes API
        }

        function getRoleColor(role) {
            switch(role) {
                case 'food_provider': return 'primary';
                case 'food_consumer': return 'success';
                case 'admin': return 'danger';
                default: return 'secondary';
            }
        }

        function getStatusColor(status) {
            switch(status) {
                case 'active': return 'success';
                case 'banned': return 'danger';
                case 'restricted': return 'warning';
                case 'pending': return 'info';
                default: return 'secondary';
            }
        }

        function toggleUserSelection(userId) {
            if (selectedUsers.has(userId)) {
                selectedUsers.delete(userId);
            } else {
                selectedUsers.add(userId);
            }
        }

        function viewUserDetails(userId) {
            const user = allUsers.find(u => u.id === userId);
            if (user) {
                document.getElementById('userDetailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Basic Information</h6>
                            <p><strong>Name:</strong> ${user.name}</p>
                            <p><strong>Email:</strong> ${user.email}</p>
                            <p><strong>Role:</strong> <span class="badge bg-${getRoleColor(user.role)}">${user.role}</span></p>
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(user.status)}">${user.status || 'active'}</span></p>
                            <p><strong>Phone:</strong> ${user.phone || 'Not provided'}</p>
                            <p><strong>Address:</strong> ${user.address || 'Not provided'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Account Details</h6>
                            <p><strong>Created:</strong> ${user.created_at ? new Date(user.created_at).toLocaleString() : 'Unknown'}</p>
                            <p><strong>Last Login:</strong> ${user.last_login || 'Unknown'}</p>
                            <p><strong>Verified:</strong> ${user.verified ? 'Yes' : 'No'}</p>
                        </div>
                    </div>
                `;
                new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
            }
        }

        function banUser(userId) {
            if (confirm('Are you sure you want to ban this user?')) {
                // Implement ban user API call
                console.log('Banning user:', userId);
                // After successful ban, refresh the users list
                loadUsers();
            }
        }

        function activateUser(userId) {
            if (confirm('Are you sure you want to activate this user?')) {
                // Implement activate user API call
                console.log('Activating user:', userId);
                // After successful activation, refresh the users list
                loadUsers();
            }
        }

        function editUser(userId) {
            // Implement edit user functionality
            console.log('Editing user:', userId);
        }

        function viewUserActivity(userId) {
            // Implement view user activity functionality
            console.log('Viewing activity for user:', userId);
        }

        function createUser() {
            const form = document.getElementById('createUserForm');
            const formData = new FormData(form);
            
            // Implement create user API call
            console.log('Creating user with data:', Object.fromEntries(formData));
            
            // Close modal and refresh users list
            bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
            loadUsers();
        }

        function bulkBanUsers() {
            if (selectedUsers.size === 0) {
                alert('Please select users to ban.');
                return;
            }
            
            if (confirm(`Are you sure you want to ban ${selectedUsers.size} selected users?`)) {
                // Implement bulk ban API call
                console.log('Bulk banning users:', Array.from(selectedUsers));
                selectedUsers.clear();
                loadUsers();
            }
        }

        function bulkRestrictUsers() {
            if (selectedUsers.size === 0) {
                alert('Please select users to restrict.');
                return;
            }
            
            if (confirm(`Are you sure you want to restrict ${selectedUsers.size} selected users?`)) {
                // Implement bulk restrict API call
                console.log('Bulk restricting users:', Array.from(selectedUsers));
                selectedUsers.clear();
                loadUsers();
            }
        }

        function bulkActivateUsers() {
            if (selectedUsers.size === 0) {
                alert('Please select users to activate.');
                return;
            }
            
            if (confirm(`Are you sure you want to activate ${selectedUsers.size} selected users?`)) {
                // Implement bulk activate API call
                console.log('Bulk activating users:', Array.from(selectedUsers));
                selectedUsers.clear();
                loadUsers();
            }
        }

        function exportUsers() {
            // Implement export users functionality
            console.log('Exporting users data...');
        }

        function refreshUsers() {
            loadUsers();
            loadDisputes();
        }
    </script>
</body>
</html>
