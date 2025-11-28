<?php
require_once __DIR__ . '/../config/database.php';

function getDashboardCache() {
    $cacheFile = __DIR__ . '/dashboard_cache.json';
    $cacheTTL = 300; // 5 minutes

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) return $data;
    }

    global $db;

    $dashboard = [
        'users' => [
            'total_users'    => 0,
            'providers'      => 0,
            'consumers'      => 0,
            'verified_users' => 0,
            'recent_signups' => 0,
        ],
        'listings' => [
            'active_listings' => 0,
            'total_revenue'   => 0,
        ],
        'reports' => [
            'pending_reports' => 0,
            'total_reports'   => 0,
            'recent_reports'  => []
        ],
        'orders' => [
            'total_orders'       => 0,
            'completed_orders'   => 0,
            'total_food_saved'   => 0,
            'total_savings'      => 0,
            'average_order_value'=> 0,
        ],
        'top_providers' => []
    ];

    try {
        // ---------------- USERS ----------------
        $users = $db->collection('users')->documents();
        $oneWeekAgo = new DateTime('-1 week');
        foreach ($users as $user) {
            $dashboard['users']['total_users']++;
            $data = $user->data();
            $role = $data['role'] ?? '';
            if ($role === 'food_provider') $dashboard['users']['providers']++;
            if ($role === 'food_consumer') $dashboard['users']['consumers']++;
            if (!empty($data['verified'])) $dashboard['users']['verified_users']++;
            if (!empty($data['created_at']) && $data['created_at'] instanceof Google\Cloud\Core\Timestamp) {
                if ($data['created_at']->get() > $oneWeekAgo) $dashboard['users']['recent_signups']++;
            }
        }

        // ---------------- LISTINGS ----------------
        $listings = $db->collection('listings')->documents();
        foreach ($listings as $listing) {
            $data = $listing->data();
            if (($data['status'] ?? '') === 'active') $dashboard['listings']['active_listings']++;
            if (isset($data['discounted_price'], $data['quantity'])) {
                $dashboard['listings']['total_revenue'] += floatval($data['discounted_price']) * intval($data['quantity']);
            }
        }

        // ---------------- REPORTS ----------------
        $reports = $db->collection('reports')->documents();
        foreach ($reports as $report) {
            $data = $report->data();
            if (($data['status'] ?? '') === 'pending') $dashboard['reports']['pending_reports']++;
            $dashboard['reports']['total_reports']++;
        }

        $recentReports = $db->collection('reports')->orderBy('created_at', 'desc')->limit(5)->documents();
        foreach ($recentReports as $report) {
            $data = $report->data();
            $dashboard['reports']['recent_reports'][] = [
                'id' => $report->id(),
                'type' => $data['type'] ?? 'unknown',
                'status' => $data['status'] ?? 'pending',
                'reporter_name' => $data['reporter_name'] ?? 'Anonymous',
                'created_at' => $data['created_at'] ?? null,
                'description' => $data['description'] ?? ''
            ];
        }

        // ---------------- ORDERS ----------------
        $carts = $db->collection('cart')->documents();
        foreach ($carts as $cart) {
            $data = $cart->data();
            $dashboard['orders']['total_orders']++;
            $status = strtolower(trim($data['status'] ?? ''));
            if (in_array($status, ['completed','delivered','fulfilled','done','success','finished','picked_up'])) {
                $dashboard['orders']['completed_orders']++;
            }
            $orderValue = floatval($data['total_price'] ?? 0);
            $dashboard['orders']['total_savings'] += $orderValue * 0.5; // assume 50% savings
            $dashboard['orders']['average_order_value'] += $orderValue;

            // Food saved
            $items = $data['items'] ?? [];
            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?? $item['qty'] ?? 0);
                $weight = floatval($item['weight_per_unit'] ?? $item['weightPerUnit'] ?? 0.5);
                $dashboard['orders']['total_food_saved'] += $qty * $weight;
            }
        }

        if ($dashboard['orders']['total_orders'] > 0) {
            $dashboard['orders']['average_order_value'] = round(
                $dashboard['orders']['average_order_value'] / $dashboard['orders']['total_orders'], 2
            );
        }

        // ---------------- TOP PROVIDERS ----------------
        $providers = $db->collection('users')->where('role','=','food_provider')->documents();
        foreach ($providers as $provider) {
            $pData = $provider->data();
            $providerId = $provider->id();
            $pStats = [
                'name' => $pData['name'] ?? 'Unknown',
                'email'=> $pData['email'] ?? '',
                'active_listings' => 0,
                'total_listings'  => 0,
                'total_revenue'   => 0
            ];

            $pListings = $db->collection('listings')->where('provider_id','=',$providerId)->documents();
            foreach ($pListings as $l) {
                $lData = $l->data();
                $pStats['total_listings']++;
                if (($lData['status'] ?? '') === 'active') $pStats['active_listings']++;
                if (isset($lData['discounted_price'], $lData['quantity'])) {
                    $pStats['total_revenue'] += floatval($lData['discounted_price']) * intval($lData['quantity']);
                }
            }

            $dashboard['top_providers'][] = $pStats;
        }

        usort($dashboard['top_providers'], fn($a,$b) => $b['total_revenue'] <=> $a['total_revenue']);
        $dashboard['top_providers'] = array_slice($dashboard['top_providers'],0,5);

        // Save cache
        file_put_contents($cacheFile, json_encode($dashboard));

    } catch (Exception $e) {
        error_log("Error generating dashboard cache: " . $e->getMessage());
    }

    return $dashboard;
}
