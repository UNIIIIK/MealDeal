<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reportId = $_POST['report_id'] ?? '';
    $warningType = $_POST['warning_type'] ?? '';
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    if (!empty($action) && !empty($reportId)) {
        handleReportAction($action, $reportId, $warningType, $adminNotes);
    }
}

// Get reports with pagination
$page = $_GET['page'] ?? 1;
$status = $_GET['status'] ?? 'all';
$reports = getReports($page, $status);
$totalPages = getTotalReportPages($status);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - MealDeal Admin</title>
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
                            <a class="nav-link active" href="reports.php">
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
                    <h1 class="h2">Reports Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshReports()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status === 'all' ? 'active' : ''; ?>" 
                           href="?status=all">All Reports</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>" 
                           href="?status=pending">Pending</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status === 'resolved' ? 'active' : ''; ?>" 
                           href="?status=resolved">Resolved</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status === 'dismissed' ? 'active' : ''; ?>" 
                           href="?status=dismissed">Dismissed</a>
                    </li>
                </ul>

                <!-- Reports Table -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Reporter</th>
                                        <th>Target</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['id']); ?></td>
                                        <td>
                                            <?php $rtype = $report['type'] ?? 'other'; ?>
                                            <span class="badge bg-<?php echo getReportTypeColor($rtype); ?>">
                                                <?php echo htmlspecialchars($rtype); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['reporter_name'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($report['target_name'] ?? '—'); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-link" onclick="viewReportDetails('<?php echo $report['id']; ?>')">
                                                View Details
                                            </button>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusColor($report['status']); ?>">
                                                <?php echo htmlspecialchars($report['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($report['created_at'] ?? null); ?></td>
                                        <td>
                                            <?php if ($report['status'] === 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-warning" onclick="showWarningModal('<?php echo $report['id']; ?>')">
                                                        <i class="fas fa-exclamation-triangle"></i> Warn
                                                    </button>
                                                    <button class="btn btn-danger" onclick="showBanModal('<?php echo $report['id']; ?>')">
                                                        <i class="fas fa-ban"></i> Ban
                                                    </button>
                                                    <button class="btn btn-success" onclick="resolveReport('<?php echo $report['id']; ?>')">
                                                        <i class="fas fa-check"></i> Resolve
                                                    </button>
                                                    <button class="btn btn-secondary" onclick="dismissReport('<?php echo $report['id']; ?>')">
                                                        <i class="fas fa-times"></i> Dismiss
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-info" onclick="viewReportHistory('<?php echo $report['id']; ?>')">
                                                    <i class="fas fa-history"></i> History
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Reports pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Warning Modal -->
    <div class="modal fade" id="warningModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Warning</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="warn">
                        <input type="hidden" name="report_id" id="warningReportId">
                        
                        <div class="mb-3">
                            <label for="warningType" class="form-label">Warning Type</label>
                            <select class="form-select" name="warning_type" id="warningType" required>
                                <option value="">Select warning type</option>
                                <option value="first">First Warning</option>
                                <option value="second">Second Warning</option>
                                <option value="final">Final Warning</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">Admin Notes</label>
                            <textarea class="form-control" name="admin_notes" id="adminNotes" rows="3" 
                                      placeholder="Add notes about this warning..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Issue Warning</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ban Modal -->
    <div class="modal fade" id="banModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ban User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ban">
                        <input type="hidden" name="report_id" id="banReportId">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action will permanently ban the user from the platform.
                        </div>
                        
                        <div class="mb-3">
                            <label for="banReason" class="form-label">Ban Reason</label>
                            <textarea class="form-control" name="admin_notes" id="banReason" rows="3" 
                                      placeholder="Explain the reason for banning this user..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Ban User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/reports.js"></script>
</body>
</html>

<?php
// Helper functions
function getReports($page = 1, $status = 'all', $limit = 20) {
    global $db;
    
    try {
        $reportsRef = $db->getCollection('reports');
        
        if ($status !== 'all') {
            $reports = $reportsRef->where('status', '=', $status)->orderBy('created_at', 'desc')->documents();
        } else {
            $reports = $reportsRef->orderBy('created_at', 'desc')->documents();
        }
        
        $allReports = [];
        foreach ($reports as $report) {
            $reportData = $report->data();
            $allReports[] = [
                'id' => $report->id(),
                'type' => $reportData['type'],
                'status' => $reportData['status'],
                'reporter_name' => $reportData['reporter_name'],
                'target_name' => $reportData['target_name'],
                'description' => $reportData['description'],
                'created_at' => $reportData['created_at']
            ];
        }
        
        // Simple pagination
        $offset = ($page - 1) * $limit;
        return array_slice($allReports, $offset, $limit);
    } catch (Exception $e) {
        error_log("Error getting reports: " . $e->getMessage());
        return [];
    }
}

function getTotalReportPages($status = 'all', $limit = 20) {
    global $db;
    
    try {
        $reportsRef = $db->getCollection('reports');
        
        if ($status !== 'all') {
            $reports = $reportsRef->where('status', '=', $status)->documents();
        } else {
            $reports = $reportsRef->documents();
        }
        
        $total = iterator_count($reports);
        return ceil($total / $limit);
    } catch (Exception $e) {
        error_log("Error getting total pages: " . $e->getMessage());
        return 1;
    }
}

function getReportTypeColor($type) {
    $colors = [
        'inappropriate_content' => 'danger',
        'poor_quality' => 'warning',
        'fake_listing' => 'info',
        'spam' => 'secondary',
        'other' => 'dark'
    ];
    
    return $colors[$type] ?? 'primary';
}

function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'resolved' => 'success',
        'dismissed' => 'secondary'
    ];
    
    return $colors[$status] ?? 'primary';
}

function formatDate($timestamp) {
    if ($timestamp === null) {
        return '—';
    }
    // Firestore PHP SDK may return Google\Cloud\Core\Timestamp objects
    if ($timestamp instanceof \Google\Cloud\Core\Timestamp) {
        $dt = $timestamp->get();
        return $dt->format('M j, Y g:i A');
    }
    // Or an associative array with seconds
    if (is_array($timestamp)) {
        $seconds = $timestamp['seconds'] ?? null;
        if ($seconds !== null) {
            return date('M j, Y g:i A', (int)$seconds);
        }
        return '—';
    }
    // Or a UNIX seconds integer
    if (is_numeric($timestamp)) {
        return date('M j, Y g:i A', (int)$timestamp);
    }
    return '—';
}

function handleReportAction($action, $reportId, $warningType, $adminNotes) {
    global $db;
    
    try {
        $reportDoc = $db->getDocument('reports', $reportId);
        $reportData = $reportDoc->snapshot()->data();
        
        switch ($action) {
            case 'warn':
                issueWarning($reportData['target_user_id'], $warningType, $adminNotes, $reportId);
                updateReportStatus($reportId, 'resolved');
                break;
                
            case 'ban':
                banUser($reportData['target_user_id'], $adminNotes, $reportId);
                updateReportStatus($reportId, 'resolved');
                break;
                
            case 'resolve':
                updateReportStatus($reportId, 'resolved');
                break;
                
            case 'dismiss':
                updateReportStatus($reportId, 'dismissed');
                break;
        }
        
        header('Location: reports.php?success=1');
        exit();
    } catch (Exception $e) {
        error_log("Error handling report action: " . $e->getMessage());
        header('Location: reports.php?error=1');
        exit();
    }
}

function issueWarning($userId, $warningType, $adminNotes, $reportId) {
    global $db;
    
    // Get user's current warnings
    $userDoc = $db->getDocument('users', $userId);
    $userData = $userDoc->snapshot()->data();
    $currentWarnings = $userData['warnings'] ?? [];
    
    // Add new warning
    $newWarning = [
        'type' => $warningType,
        'admin_notes' => $adminNotes,
        'report_id' => $reportId,
        'issued_at' => time(),
        'issued_by' => $_SESSION['admin_id']
    ];
    
    $currentWarnings[] = $newWarning;
    
    // Update user document
    $db->updateDocument('users', $userId, [
        'warnings' => $currentWarnings,
        'warning_count' => count($currentWarnings)
    ]);
    
    // Log the action
    logAdminAction('warning_issued', [
        'user_id' => $userId,
        'warning_type' => $warningType,
        'report_id' => $reportId,
        'admin_notes' => $adminNotes
    ]);
}

function banUser($userId, $adminNotes, $reportId) {
    global $db;
    
    // Update user status to banned
    $db->updateDocument('users', $userId, [
        'status' => 'banned',
        'banned_at' => time(),
        'banned_by' => $_SESSION['admin_id'],
        'ban_reason' => $adminNotes
    ]);
    
    // Log the action
    logAdminAction('user_banned', [
        'user_id' => $userId,
        'report_id' => $reportId,
        'admin_notes' => $adminNotes
    ]);
}

function updateReportStatus($reportId, $status) {
    global $db;
    
    $db->updateDocument('reports', $reportId, [
        'status' => $status,
        'resolved_at' => time(),
        'resolved_by' => $_SESSION['admin_id']
    ]);
}

function logAdminAction($action, $data) {
    global $db;
    
    $db->addDocument('admin_logs', [
        'action' => $action,
        'admin_id' => $_SESSION['admin_id'],
        'admin_name' => $_SESSION['admin_name'],
        'data' => $data,
        'timestamp' => time()
    ]);
}
?>
