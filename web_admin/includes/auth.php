<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/cache.php';

// =============================
// Admin authentication functions
// =============================

function isAdminLoggedIn() {
    $isLogged = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    if (!$isLogged) { return false; }

    $now = time();
    $ttlSeconds = 20 * 60; // 20 minutes inactivity timeout
    $last = isset($_SESSION['last_activity']) ? intval($_SESSION['last_activity']) : 0;

    if ($last > 0 && ($now - $last) > $ttlSeconds) {
        logoutAdmin();
        return false;
    }

    // Update sliding expiration
    $_SESSION['last_activity'] = $now;
    return true;
}

function loginAdmin($email, $password) {
    global $db;

    try {
        // Fetch only needed fields to reduce payload and speed up request
        $adminsRef = $db->collection('admins')
            ->select(['password', 'name', 'email', 'role'])
            ->where('email', '=', $email)
            ->limit(1);

        $documents = $adminsRef->documents();

        foreach ($documents as $document) {
            if (!method_exists($document, 'exists') || !$document->exists()) {
                continue;
            }

            $adminData = $document->data();

            // Defensive: ensure password field exists
            if (!isset($adminData['password'])) {
                continue;
            }

            if (password_verify($password, $adminData['password'])) {
                $_SESSION['admin_id']    = $document->id();
                $_SESSION['admin_name']  = $adminData['name'] ?? '';
                $_SESSION['admin_email'] = $adminData['email'] ?? '';
                $_SESSION['admin_role']  = $adminData['role'] ?? '';
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
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
        $adminDoc = $db->collection('admins')->document($_SESSION['admin_id'])->snapshot();

        if ($adminDoc->exists()) {
            $adminData = $adminDoc->data();
            return [
                'id'    => $_SESSION['admin_id'],
                'name'  => $adminData['name'],
                'email' => $adminData['email'],
                'role'  => $adminData['role']
            ];
        }
        return null;
    } catch (Exception $e) {
        error_log("Error getting admin data: " . $e->getMessage());
        return null;
    }
}

// =============================
// Dashboard statistics functions
// =============================

function getDashboardStats() {
    global $db;

    try {
        $stats = [
            'total_users'     => 0,
            'active_listings' => 0,
            'pending_reports' => 0,
            'food_saved'      => 0
        ];

        // Count users using select(__name__) to minimize payload
        $users = $db->collection('users')->select(['__name__'])->documents();
        $stats['total_users'] = iterator_count($users);

        // Count active listings (select __name__)
        $activeListings = $db->collection('listings')
            ->select(['__name__'])
            ->where('status', '=', 'active')
            ->documents();
        $stats['active_listings'] = iterator_count($activeListings);

        // Count pending reports
        $pendingReports = $db->collection('reports')
            ->select(['__name__'])
            ->where('status', '=', 'pending')
            ->documents();
        $stats['pending_reports'] = iterator_count($pendingReports);

        // Food saved: stream completed carts but only decode items field (defensive)
        $completedCarts = $db->collection('cart')
            ->select(['items'])
            ->where('status', '=', 'completed')
            ->documents();

        $totalFoodSaved = 0;
        foreach ($completedCarts as $cart) {
            if (!method_exists($cart, 'exists') || !$cart->exists()) {
                continue;
            }
            $cartData = $cart->data();
            if (isset($cartData['items']) && is_array($cartData['items'])) {
                foreach ($cartData['items'] as $item) {
                    $qty = isset($item['quantity']) ? (int)$item['quantity'] : (isset($item['qty']) ? (int)$item['qty'] : 0);
                    $w = isset($item['weight_per_unit']) ? (float)$item['weight_per_unit'] : (isset($item['weightPerUnit']) ? (float)$item['weightPerUnit'] : 0.5);
                    if ($qty > 0) {
                        $totalFoodSaved += $qty * $w;
                    }
                }
            }
        }
        $stats['food_saved'] = $totalFoodSaved;

        return $stats;
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_users'     => 0,
            'active_listings' => 0,
            'pending_reports' => 0,
            'food_saved'      => 0
        ];
    }
}

// =============================
// Recent reports for dashboard
// =============================

function getRecentReports($limit = 5) {
    global $db;

    try {
        $reports = $db->collection('reports')
            ->orderBy('created_at', 'desc')
            ->limit((int)$limit)
            ->documents();

        $recentReports = [];
        foreach ($reports as $report) {
            if (!method_exists($report, 'exists') || !$report->exists()) {
                continue;
            }
            $data = $report->data();
            $recentReports[] = [
                'id'            => $report->id(),
                'type'          => $data['type'] ?? 'unknown',
                'status'        => $data['status'] ?? 'pending',
                'reporter_name' => $data['reporter_name'] ?? 'Anonymous',
                'created_at'    => $data['created_at'] ?? null,
                'description'   => $data['description'] ?? 'No description'
            ];
        }
        return $recentReports;
    } catch (Exception $e) {
        error_log("Error getting recent reports: " . $e->getMessage());
        return [];
    }
}
