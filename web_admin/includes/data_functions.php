<?php
require_once 'config/database.php';

// Enhanced data fetching functions for real Firestore data

/**
 * Get comprehensive user statistics
 */
function getUserStats() {
    global $db;
    
    try {
        $usersRef = $db->getCollection('users');
        $users = $usersRef->documents();
        
        $stats = [
            'total_users' => 0,
            'providers' => 0,
            'consumers' => 0,
            'verified_users' => 0,
            'recent_signups' => 0
        ];
        
        $oneWeekAgo = new DateTime('-1 week');
        
        foreach ($users as $user) {
            $userData = $user->data();
            $stats['total_users']++;
            
            // Count by role
            if (isset($userData['role'])) {
                if ($userData['role'] === 'food_provider') {
                    $stats['providers']++;
                } elseif ($userData['role'] === 'food_consumer') {
                    $stats['consumers']++;
                }
            }
            
            // Count verified users
            if (isset($userData['verified']) && $userData['verified'] === true) {
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
        $listingsRef = $db->getCollection('listings');
        $listings = $listingsRef->documents();
        
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
        $cartsRef = $db->getCollection('cart');
        $carts = $cartsRef->documents();
        
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
            
            // Count by status
            if (isset($cartData['status'])) {
                if ($cartData['status'] === 'completed') {
                    $stats['completed_orders']++;
                } elseif (in_array($cartData['status'], ['pending', 'awaiting_pickup', 'claimed'])) {
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
                    if (isset($item['quantity']) && isset($item['weight_per_unit'])) {
                        $quantity = intval($item['quantity']);
                        $weight = floatval($item['weight_per_unit']);
                        $stats['total_food_saved'] += ($quantity * $weight);
                    }
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
        $reportsRef = $db->getCollection('reports');
        $reports = $reportsRef->documents();
        
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
            
            // Get recent reports
            if (isset($reportData['created_at'])) {
                $createdAt = $reportData['created_at'];
                if ($createdAt instanceof Google\Cloud\Core\Timestamp) {
                    $createdDate = $createdAt->get();
                    if ($createdDate > $oneWeekAgo) {
                        $stats['recent_reports'][] = [
                            'id' => $report->id(),
                            'type' => $reportData['type'] ?? 'Unknown',
                            'status' => $reportData['status'] ?? 'Unknown',
                            'reporter_name' => $reportData['reporter_name'] ?? 'Anonymous',
                            'created_at' => $createdDate->format('Y-m-d H:i:s'),
                            'description' => $reportData['description'] ?? 'No description'
                        ];
                    }
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
 * Get top providers by performance
 */
function getTopProviders($limit = 10) {
    global $db;
    
    try {
        $usersRef = $db->getCollection('users');
        $providers = $usersRef->where('role', '=', 'food_provider')->documents();
        
        $providerStats = [];
        
        foreach ($providers as $provider) {
            $providerData = $provider->data();
            $providerId = $provider->id();
            
            // Get provider's listings
            $listingsRef = $db->getCollection('listings');
            $providerListings = $listingsRef->where('provider_id', '=', $providerId)->documents();
            
            $stats = [
                'id' => $providerId,
                'name' => $providerData['name'] ?? 'Unknown',
                'email' => $providerData['email'] ?? '',
                'total_listings' => 0,
                'active_listings' => 0,
                'total_revenue' => 0,
                'food_saved' => 0
            ];
            
            foreach ($providerListings as $listing) {
                $listingData = $listing->data();
                $stats['total_listings']++;
                
                if (isset($listingData['status']) && $listingData['status'] === 'active') {
                    $stats['active_listings']++;
                }
                
                if (isset($listingData['discounted_price']) && isset($listingData['quantity'])) {
                    $price = floatval($listingData['discounted_price']);
                    $quantity = intval($listingData['quantity']);
                    $stats['total_revenue'] += ($price * $quantity);
                }
                
                if (isset($listingData['weight_per_unit']) && isset($listingData['quantity'])) {
                    $weight = floatval($listingData['weight_per_unit']);
                    $quantity = intval($listingData['quantity']);
                    $stats['food_saved'] += ($weight * $quantity);
                }
            }
            
            $providerStats[] = $stats;
        }
        
        // Sort by total revenue
        usort($providerStats, function($a, $b) {
            return $b['total_revenue'] - $a['total_revenue'];
        });
        
        return array_slice($providerStats, 0, $limit);
    } catch (Exception $e) {
        error_log("Error getting top providers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top consumers by activity
 */
function getTopConsumers($limit = 10) {
    global $db;
    
    try {
        $usersRef = $db->getCollection('users');
        $consumers = $usersRef->where('role', '=', 'food_consumer')->documents();
        
        $consumerStats = [];
        
        foreach ($consumers as $consumer) {
            $consumerData = $consumer->data();
            $consumerId = $consumer->id();
            
            // Get consumer's orders
            $cartsRef = $db->getCollection('cart');
            $consumerOrders = $cartsRef->where('consumer_id', '=', $consumerId)->documents();
            
            $stats = [
                'id' => $consumerId,
                'name' => $consumerData['name'] ?? 'Unknown',
                'email' => $consumerData['email'] ?? '',
                'total_orders' => 0,
                'completed_orders' => 0,
                'total_spent' => 0,
                'total_savings' => 0
            ];
            
            foreach ($consumerOrders as $order) {
                $orderData = $order->data();
                $stats['total_orders']++;
                
                if (isset($orderData['status']) && $orderData['status'] === 'completed') {
                    $stats['completed_orders']++;
                }
                
                if (isset($orderData['total_price'])) {
                    $price = floatval($orderData['total_price']);
                    $stats['total_spent'] += $price;
                    $stats['total_savings'] += ($price * 0.5); // Assuming 50% savings
                }
            }
            
            $consumerStats[] = $stats;
        }
        
        // Sort by total spent
        usort($consumerStats, function($a, $b) {
            return $b['total_spent'] - $a['total_spent'];
        });
        
        return array_slice($consumerStats, 0, $limit);
    } catch (Exception $e) {
        error_log("Error getting top consumers: " . $e->getMessage());
        return [];
    }
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
