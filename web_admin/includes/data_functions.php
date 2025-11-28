<?php
require_once __DIR__ . '/../config/database.php';

// Enhanced data fetching functions for real Firestore data

/**
 * Get comprehensive user statistics
 */
function getUserStats() {
    global $db;
    
    try {
        // Use a smaller limit to prevent stack overflow
        $usersRef = $db->collection('users');
        $users = $usersRef->limit(100)->documents();
        
        $stats = [
            'total_users' => 0,
            'providers' => 0,
            'consumers' => 0,
            'verified_users' => 0,
            'recent_signups' => 0
        ];
        
        $oneWeekAgo = new DateTime('-1 week');
        $userCount = 0;
        
        foreach ($users as $user) {
            if ($userCount >= 100) break; // Safety limit
            
            $userData = $user->data();
            $stats['total_users']++;
            $userCount++;
            
            // Count by role
            if (isset($userData['role'])) {
                if ($userData['role'] === 'food_provider') {
                    $stats['providers']++;
                } elseif ($userData['role'] === 'food_consumer') {
                    $stats['consumers']++;
                }
            }
            
            // Count verified users - based on debug data, users have email but no explicit verification
            // For now, consider all users with email as verified
            if (isset($userData['email']) && !empty($userData['email'])) {
                $stats['verified_users']++;
            }
            
            // Count recent signups
            if (isset($userData['created_at'])) {
                $createdAt = $userData['created_at'];
                if ($createdAt instanceof Google\Cloud\Core\Timestamp) {
                    $createdDate = $createdAt->get();
                    if ($createdDate > $oneWeekAgo) {
                        $stats['recent_signups']++;
                    }
                }
            }
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting user stats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'providers' => 0,
            'consumers' => 0,
            'verified_users' => 0,
            'recent_signups' => 0
        ];
    }
}

/**
 * Get comprehensive listing statistics
 */
function getListingStats() {
    global $db;
    
    try {
        $listingsRef = $db->collection('listings');
        $listings = $listingsRef->limit(50)->documents();
        
        $stats = [
            'total_listings' => 0,
            'active_listings' => 0,
            'sold_out_listings' => 0,
            'total_revenue' => 0,
            'average_price' => 0,
            'categories' => []
        ];
        
        $totalPrice = 0;
        $priceCount = 0;
        
        foreach ($listings as $listing) {
            $listingData = $listing->data();
            $stats['total_listings']++;
            
            // Count by status
            if (isset($listingData['status'])) {
                if ($listingData['status'] === 'active') {
                    $stats['active_listings']++;
                } elseif ($listingData['status'] === 'sold_out') {
                    $stats['sold_out_listings']++;
                }
            }
            
            // Calculate revenue and average price
            if (isset($listingData['discounted_price'])) {
                $price = floatval($listingData['discounted_price']);
                $totalPrice += $price;
                $priceCount++;
                
                if (isset($listingData['quantity'])) {
                    $quantity = intval($listingData['quantity']);
                    $stats['total_revenue'] += ($price * $quantity);
                }
            }
            
            // Count categories
            if (isset($listingData['category'])) {
                $category = $listingData['category'];
                if (!isset($stats['categories'][$category])) {
                    $stats['categories'][$category] = 0;
                }
                $stats['categories'][$category]++;
            }
        }
        
        if ($priceCount > 0) {
            $stats['average_price'] = round($totalPrice / $priceCount, 2);
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting listing stats: " . $e->getMessage());
        return [
            'total_listings' => 0,
            'active_listings' => 0,
            'sold_out_listings' => 0,
            'total_revenue' => 0,
            'average_price' => 0,
            'categories' => []
        ];
    }
}

/**
 * Get comprehensive order/cart statistics
 */
function getOrderStats() {
    global $db;
    
    try {
        $cartsRef = $db->collection('cart');
        $carts = $cartsRef->limit(50)->documents();
        
        $stats = [
            'total_orders' => 0,
            'completed_orders' => 0,
            'pending_orders' => 0,
            'total_food_saved' => 0,
            'total_savings' => 0,
            'average_order_value' => 0
        ];
        
        $totalOrderValue = 0;
        $orderCount = 0;
        
        foreach ($carts as $cart) {
            $cartData = $cart->data();
            $stats['total_orders']++;
            
            // Count by status - from debug data, cart has 'status' field
            if (isset($cartData['status'])) {
                $status = strtolower(trim($cartData['status']));
                if (in_array($status, ['completed', 'delivered', 'fulfilled', 'done', 'success', 'finished', 'picked_up'])) {
                    $stats['completed_orders']++;
                } elseif (in_array($status, ['pending', 'awaiting_pickup', 'claimed', 'processing', 'in_progress', 'active', 'open', 'available'])) {
                    $stats['pending_orders']++;
                } else {
                    // If status is unknown, check for completion indicators
                    if (isset($cartData['checkout_date']) || isset($cartData['completed_at']) || isset($cartData['delivery_date'])) {
                        $stats['completed_orders']++;
                    } else {
                        $stats['pending_orders']++;
                    }
                }
            } else {
                // If no status field, check if there's a checkout_date or completion indicator
                if (isset($cartData['checkout_date']) || isset($cartData['completed_at']) || isset($cartData['delivery_date'])) {
                    $stats['completed_orders']++;
                } else {
                    $stats['pending_orders']++;
                }
            }
            
            // Calculate food saved and savings
            if (isset($cartData['total_price'])) {
                $orderValue = floatval($cartData['total_price']);
                $totalOrderValue += $orderValue;
                $orderCount++;
                
                // Estimate savings (assuming 50% discount)
                $stats['total_savings'] += ($orderValue * 0.5);
            }
            
            // Calculate food saved from items
            if (isset($cartData['items']) && is_array($cartData['items'])) {
                foreach ($cartData['items'] as $item) {
                    $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;

                    // Primary source: item-provided weight in kilograms
                    if (isset($item['weight_per_unit'])) {
                        $weightKg = floatval($item['weight_per_unit']);
                        $stats['total_food_saved'] += ($quantity * $weightKg);
                        continue;
                    }

                    // Fallback: avoid extra Firestore lookups to prevent timeouts; estimate conservatively
                    $stats['total_food_saved'] += ($quantity * 0.5); // default ~500g per item
                }
            }
        }
        
        if ($orderCount > 0) {
            $stats['average_order_value'] = round($totalOrderValue / $orderCount, 2);
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting order stats: " . $e->getMessage());
        return [
            'total_orders' => 0,
            'completed_orders' => 0,
            'pending_orders' => 0,
            'total_food_saved' => 0,
            'total_savings' => 0,
            'average_order_value' => 0
        ];
    }
}

/**
 * Get comprehensive report statistics
 */
function getReportStats() {
    global $db;
    
    try {
        $reportsRef = $db->collection('reports');
        $reports = $reportsRef->limit(50)->documents();
        
        $stats = [
            'total_reports' => 0,
            'pending_reports' => 0,
            'resolved_reports' => 0,
            'report_types' => [],
            'recent_reports' => []
        ];
        
        $oneWeekAgo = new DateTime('-1 week');
        
        foreach ($reports as $report) {
            $reportData = $report->data();
            $stats['total_reports']++;
            
            // Count by status
            if (isset($reportData['status'])) {
                if ($reportData['status'] === 'pending') {
                    $stats['pending_reports']++;
                } elseif ($reportData['status'] === 'resolved') {
                    $stats['resolved_reports']++;
                }
            }
            
            // Count by type
            if (isset($reportData['type'])) {
                $type = $reportData['type'];
                if (!isset($stats['report_types'][$type])) {
                    $stats['report_types'][$type] = 0;
                }
                $stats['report_types'][$type]++;
            }
            
            // Get recent reports - from debug data, reports have 'created_at' field
            if (isset($reportData['created_at'])) {
                $createdAt = $reportData['created_at'];
                $createdDate = null;
                
                if ($createdAt instanceof Google\Cloud\Core\Timestamp) {
                    $createdDate = $createdAt->get();
                } elseif (is_numeric($createdAt)) {
                    $createdDate = new DateTime('@' . $createdAt);
                } elseif (is_string($createdAt)) {
                    $createdDate = new DateTime($createdAt);
                }
                
                if ($createdDate && $createdDate > $oneWeekAgo) {
                    $stats['recent_reports'][] = [
                        'id' => $report->id(),
                        'type' => $reportData['reason'] ?? 'Unknown', // From debug data, reports have 'reason' field
                        'status' => $reportData['status'] ?? 'Unknown',
                        'reporter_name' => $reportData['reporter_email'] ?? 'Anonymous', // From debug data, reports have 'reporter_email'
                        'created_at' => $createdDate->format('Y-m-d H:i:s'),
                        'description' => $reportData['description'] ?? 'No description'
                    ];
                }
            }
        }
        
        // Sort recent reports by date
        usort($stats['recent_reports'], function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit to 10 most recent
        $stats['recent_reports'] = array_slice($stats['recent_reports'], 0, 10);
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting report stats: " . $e->getMessage());
        return [
            'total_reports' => 0,
            'pending_reports' => 0,
            'resolved_reports' => 0,
            'report_types' => [],
            'recent_reports' => []
        ];
    }
}

/**
 * Get top providers by performance (lightweight Firestore query)
 */
function getTopProviders($limit = 10) {
    global $db;

    try {
        // Lightweight leaderboard: rely only on aggregate fields stored on the
        // user document to avoid heavy cross‑collection scans.
        $usersRef = $db->collection('users');
        $providers = $usersRef
            ->where('role', '=', 'food_provider')
            ->limit($limit * 3) // fetch a small pool, then sort in PHP
            ->documents();

        $providerStats = [];

        foreach ($providers as $provider) {
            $providerData = $provider->data();
            $providerId   = $provider->id();

            $providerStats[] = [
                'id'              => $providerId,
                'name'            => $providerData['name'] ?? 'Unknown',
                'email'           => $providerData['email'] ?? '',
                // Prefer pre‑calculated fields on the user doc if present
                'total_listings'  => $providerData['total_listings']  ?? 0,
                'active_listings' => $providerData['active_listings'] ?? 0,
                'total_revenue'   => $providerData['total_revenue']   ?? 0,
                'food_saved'      => $providerData['food_saved']      ?? 0,
                'created_at'      => $providerData['created_at']      ?? ($providerData['timestamp'] ?? time()),
            ];
        }

        // Sort primarily by food_saved, then by total_revenue
        usort($providerStats, function ($a, $b) {
            if ($b['food_saved'] == $a['food_saved']) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            }
            return $b['food_saved'] <=> $a['food_saved'];
        });

        return array_slice($providerStats, 0, $limit);
    } catch (Exception $e) {
        error_log("Error getting top providers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top consumers by activity (lightweight Firestore query)
 */
function getTopConsumers($limit = 10) {
    global $db;

    try {
        // Lightweight leaderboard for consumers, using only user‑level aggregates.
        $usersRef = $db->collection('users');
        $consumers = $usersRef
            ->where('role', '=', 'food_consumer')
            ->limit($limit * 3)
            ->documents();

        $consumerStats = [];

        foreach ($consumers as $consumer) {
            $consumerData = $consumer->data();
            $consumerId   = $consumer->id();

            $consumerStats[] = [
                'id'               => $consumerId,
                'name'             => $consumerData['name'] ?? 'Unknown',
                'email'            => $consumerData['email'] ?? '',
                'total_orders'     => $consumerData['total_orders']     ?? 0,
                'completed_orders' => $consumerData['completed_orders'] ?? 0,
                'total_spent'      => $consumerData['total_spent']      ?? 0,
                'total_savings'    => $consumerData['total_savings']    ?? 0,
                'created_at'       => $consumerData['created_at']       ?? ($consumerData['timestamp'] ?? time()),
            ];
        }

        // Sort by total_savings, then by total_orders
        usort($consumerStats, function ($a, $b) {
            if ($b['total_savings'] == $a['total_savings']) {
                return $b['total_orders'] <=> $a['total_orders'];
            }
            return $b['total_savings'] <=> $a['total_savings'];
        });

        return array_slice($consumerStats, 0, $limit);
    } catch (Exception $e) {
        error_log("Error getting top consumers: " . $e->getMessage());
        return [];
    }
}


/**
 * Estimate food weight based on listing data
 */
function estimateFoodWeight($listingData, $quantity) {
    // Default weight estimation based on food type
    $defaultWeightPerUnit = 0.5; // 500g per unit as default
    
    // Get food type from listing
    $type = $listingData['type'] ?? 'other';
    $name = strtolower($listingData['name'] ?? '');
    
    // Estimate based on food type
    switch ($type) {
        case 'main_dish':
            $defaultWeightPerUnit = 0.8; // 800g for main dishes
            break;
        case 'side_dish':
            $defaultWeightPerUnit = 0.3; // 300g for side dishes
            break;
        case 'dessert':
            $defaultWeightPerUnit = 0.2; // 200g for desserts
            break;
        case 'beverage':
            $defaultWeightPerUnit = 0.5; // 500ml for beverages (treating as weight)
            break;
        case 'snack':
            $defaultWeightPerUnit = 0.1; // 100g for snacks
            break;
        case 'pickup':
            // For pickup items, estimate based on name
            if (strpos($name, 'ensaymada') !== false || strpos($name, 'bread') !== false || strpos($name, 'pastry') !== false) {
                $defaultWeightPerUnit = 0.15; // ~150g per piece
            } elseif (strpos($name, 'rice') !== false || strpos($name, 'meal') !== false) {
                $defaultWeightPerUnit = 0.6; // ~600g per serving
            } else {
                $defaultWeightPerUnit = 0.3; // Default for pickup items
            }
            break;
        default:
            // Try to estimate based on name keywords
            if (strpos($name, 'soup') !== false || strpos($name, 'stew') !== false) {
                $defaultWeightPerUnit = 0.6;
            } elseif (strpos($name, 'salad') !== false) {
                $defaultWeightPerUnit = 0.4;
            } elseif (strpos($name, 'pizza') !== false || strpos($name, 'burger') !== false) {
                $defaultWeightPerUnit = 0.7;
            } elseif (strpos($name, 'cake') !== false || strpos($name, 'pie') !== false) {
                $defaultWeightPerUnit = 0.3;
            } elseif (strpos($name, 'ensaymada') !== false || strpos($name, 'bread') !== false) {
                $defaultWeightPerUnit = 0.15;
            }
            break;
    }
    
    return $defaultWeightPerUnit;
}

/**
 * Get comprehensive dashboard statistics
 */
function getComprehensiveDashboardStats() {
    return [
        'users' => getUserStats(),
        'listings' => getListingStats(),
        'orders' => getOrderStats(),
        'reports' => getReportStats(),
        'top_providers' => getTopProviders(5),
        'top_consumers' => getTopConsumers(5)
    ];
}
?>

