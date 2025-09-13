<?php
require_once __DIR__ . '/../config/database.php';

// Admin authentication functions
function isAdminLoggedIn() {
    // Require active session with sliding expiration
    $isLogged = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    if (!$isLogged) { return false; }
    
    $now = time();
    $ttlSeconds = 20 * 60; // 20 minutes inactivity timeout
    $last = isset($_SESSION['last_activity']) ? intval($_SESSION['last_activity']) : 0;
    if ($last > 0 && ($now - $last) > $ttlSeconds) {
        logoutAdmin();
        return false;
    }
    // Update activity timestamp
    $_SESSION['last_activity'] = $now;
    return true;
}

function loginAdmin($email, $password) {
    global $db;
    
    try {
        // Add timeout and limit to prevent infinite loops
        $adminsRef = $db->getCollection('admins');
        $query = $adminsRef->where('email', '=', $email)->limit(1);
        $documents = $query->documents();
        
        foreach ($documents as $document) {
            $adminData = $document->data();
            
            if (password_verify($password, $adminData['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $document->id();
                $_SESSION['admin_name'] = $adminData['name'];
                $_SESSION['admin_email'] = $adminData['email'];
                $_SESSION['admin_role'] = $adminData['role'];
                $_SESSION['last_activity'] = time();
                session_regenerate_id(true);
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        return false;
    }
}

function logoutAdmin() {
    // Clear session safely
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
    return true;
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    global $db;
    
    try {
        $adminDoc = $db->getDocument('admins', $_SESSION['admin_id']);
        $adminData = $adminDoc->snapshot()->data();
        
        return [
            'id' => $_SESSION['admin_id'],
            'name' => $adminData['name'],
            'email' => $adminData['email'],
            'role' => $adminData['role']
        ];
    } catch (Exception $e) {
        error_log("Error getting admin data: " . $e->getMessage());
        return null;
    }
}

// Dashboard statistics
function getDashboardStats() {
    global $db;
    
    try {
        $stats = [
            'total_users' => 0,
            'active_listings' => 0,
            'pending_reports' => 0,
            'food_saved' => 0
        ];
        
        // Get total users
        $usersRef = $db->getCollection('users');
        $users = $usersRef->documents();
        $stats['total_users'] = iterator_count($users);
        
        // Get active listings
        $listingsRef = $db->getCollection('listings');
        $activeListings = $listingsRef->where('status', '=', 'active')->documents();
        $stats['active_listings'] = iterator_count($activeListings);
        
        // Get pending reports
        $reportsRef = $db->getCollection('reports');
        $pendingReports = $reportsRef->where('status', '=', 'pending')->documents();
        $stats['pending_reports'] = iterator_count($pendingReports);
        
        // Calculate food saved (from completed orders)
        $cartsRef = $db->getCollection('cart');
        $completedCarts = $cartsRef->where('status', '=', 'completed')->documents();
        
        $totalFoodSaved = 0;
        foreach ($completedCarts as $cart) {
            $cartData = $cart->data();
            if (isset($cartData['items'])) {
                foreach ($cartData['items'] as $item) {
                    if (isset($item['quantity']) && isset($item['weight_per_unit'])) {
                        $totalFoodSaved += ($item['quantity'] * $item['weight_per_unit']);
                    }
                }
            }
        }
        $stats['food_saved'] = $totalFoodSaved;
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'active_listings' => 0,
            'pending_reports' => 0,
            'food_saved' => 0
        ];
    }
}

// Get recent reports for dashboard
function getRecentReports($limit = 5) {
    global $db;
    
    try {
        $reportsRef = $db->getCollection('reports');
        $reports = $reportsRef->orderBy('created_at', 'desc')->limit($limit)->documents();
        
        $recentReports = [];
        foreach ($reports as $report) {
            $reportData = $report->data();
            $recentReports[] = [
                'id' => $report->id(),
                'type' => $reportData['type'] ?? 'unknown',
                'status' => $reportData['status'] ?? 'pending',
                'reporter_name' => $reportData['reporter_name'] ?? 'Anonymous',
                'created_at' => $reportData['created_at'],
                'description' => $reportData['description'] ?? 'No description'
            ];
        }
        
        return $recentReports;
    } catch (Exception $e) {
        error_log("Error getting recent reports: " . $e->getMessage());
        return [];
    }
}
?>
