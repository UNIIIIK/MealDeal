<?php
require_once 'config/database.php';

// Admin authentication functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function loginAdmin($email, $password) {
    global $db;
    
    try {
        // Query admin collection
        $adminsRef = $db->getCollection('admins');
        $query = $adminsRef->where('email', '=', $email);
        $documents = $query->documents();
        
        foreach ($documents as $document) {
            $adminData = $document->data();
            
            if (password_verify($password, $adminData['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $document->id();
                $_SESSION['admin_name'] = $adminData['name'];
                $_SESSION['admin_email'] = $adminData['email'];
                $_SESSION['admin_role'] = $adminData['role'];
                
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
    session_destroy();
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
                'type' => $reportData['type'],
                'status' => $reportData['status'],
                'reporter_name' => $reportData['reporter_name'],
                'created_at' => $reportData['created_at'],
                'description' => $reportData['description']
            ];
        }
        
        return $recentReports;
    } catch (Exception $e) {
        error_log("Error getting recent reports: " . $e->getMessage());
        return [];
    }
}
?>
