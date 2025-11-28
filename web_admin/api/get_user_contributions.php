<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

// Allow slower Firestore responses on Windows/dev setups - this is a heavy operation
$scriptTimeout = getenv('MEALDEAL_API_TIMEOUT');
$scriptTimeout = is_numeric($scriptTimeout) ? max(30, (int)$scriptTimeout) : 90;
set_time_limit($scriptTimeout);

// Simple file-based cache to avoid repeating heavy Firestore scans
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'md_user_contributions_cache.json';
$cacheTtlSeconds = 60; // 1 minute cache is fine for admin analytics

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtlSeconds) {
    $cached = @file_get_contents($cacheFile);
    if ($cached) {
        echo $cached;
        exit;
    }
}

try {
    $db = Database::getInstance()->getFirestore();
    
    // Reduce limit to prevent timeouts - only get active users
    $usersRef = $db->collection('users');
    $users = $usersRef->limit(30)->documents(); // Reduced from 50 to 30
    
    $userContributions = [];
    $listingCache = []; // Cache listing lookups to avoid repeated queries
    
    $userCount = 0;
    foreach ($users as $user) {
        if ($userCount >= 30) break; // Safety limit
        
        $userData = $user->data();
        $userId = $user->id();
        $userCount++;
        
        // Initialize user contribution data
        $contribution = [
            'id' => $userId,
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? '',
            'role' => $userData['role'] ?? 'unknown',
            'food_saved' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'total_spent' => 0,
            'total_savings' => 0,
            'total_listings' => 0,
            'active_listings' => 0
        ];
        
        try {
            if ($userData['role'] === 'food_consumer') {
                // Calculate consumer contributions - limit queries
                $cartsRef = $db->collection('cart');
                $consumerOrders = $cartsRef->where('consumer_id', '=', $userId)->limit(15)->documents(); // Reduced from 100 to 15
                
                foreach ($consumerOrders as $order) {
                    $orderData = $order->data();
                    $contribution['total_orders']++;
                    
                    // Check if order is completed
                    if (isset($orderData['status'])) {
                        $status = strtolower(trim($orderData['status']));
                        if (in_array($status, ['completed', 'delivered', 'fulfilled', 'done', 'success', 'finished', 'picked_up'])) {
                            $contribution['completed_orders']++;
                        }
                    } elseif (isset($orderData['checkout_date']) || isset($orderData['completed_at'])) {
                        $contribution['completed_orders']++;
                    }
                    
                    // Calculate spending and savings
                    if (isset($orderData['total_price'])) {
                        $orderValue = floatval($orderData['total_price']);
                        $contribution['total_spent'] += $orderValue;
                        $contribution['total_savings'] += ($orderValue * 0.5); // Assuming 50% savings
                    }
                    
                    // Calculate food saved from items - use cache to avoid repeated lookups
                    if (isset($orderData['items']) && is_array($orderData['items'])) {
                        foreach ($orderData['items'] as $item) {
                            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                            
                            // Try to get weight from item first
                            if (isset($item['weight_per_unit'])) {
                                $weightKg = floatval($item['weight_per_unit']);
                                $contribution['food_saved'] += ($quantity * $weightKg);
                                continue;
                            }
                            
                            // Use cache for listing lookups
                            if (isset($item['listing_id'])) {
                                $listingId = $item['listing_id'];
                                if (!isset($listingCache[$listingId])) {
                                    try {
                                        $listingDoc = $db->collection('listings')->document($listingId)->snapshot();
                                        if ($listingDoc->exists()) {
                                            $listingCache[$listingId] = $listingDoc->data();
                                        } else {
                                            $listingCache[$listingId] = null;
                                        }
                                    } catch (Exception $e) {
                                        $listingCache[$listingId] = null;
                                    }
                                }
                                
                                $listingData = $listingCache[$listingId];
                                if ($listingData) {
                                    $weightKg = 0;
                                    
                                    if (isset($listingData['weight_per_unit'])) {
                                        $weightKg = floatval($listingData['weight_per_unit']);
                                    } elseif (isset($listingData['unit_weight_kg'])) {
                                        $weightKg = floatval($listingData['unit_weight_kg']);
                                    } elseif (isset($listingData['weight'])) {
                                        $weightKg = floatval($listingData['weight']);
                                    } else {
                                        $weightKg = estimateFoodWeight($listingData, $quantity);
                                    }
                                    
                                    if ($weightKg > 0 && $quantity > 0) {
                                        $contribution['food_saved'] += ($quantity * $weightKg);
                                    }
                                } else {
                                    // Fallback estimate if listing not found
                                    $contribution['food_saved'] += ($quantity * 0.5);
                                }
                            } else {
                                // No listing ID, use default estimate
                                $contribution['food_saved'] += ($quantity * 0.5);
                            }
                        }
                    }
                }
                
            } elseif ($userData['role'] === 'food_provider') {
                // Calculate provider contributions - limit queries
                $listingsRef = $db->collection('listings');
                $providerListings = $listingsRef->where('provider_id', '=', $userId)->limit(15)->documents(); // Reduced from 100 to 15
                
                foreach ($providerListings as $listing) {
                    $listingData = $listing->data();
                    $contribution['total_listings']++;
                    
                    if (isset($listingData['status']) && $listingData['status'] === 'active') {
                        $contribution['active_listings']++;
                    }
                    
                    // Calculate food saved from listings
                    if (isset($listingData['quantity'])) {
                        $quantity = intval($listingData['quantity']);
                        if ($quantity > 0) {
                            $weightKg = 0;
                            
                            if (isset($listingData['weight_per_unit'])) {
                                $weightKg = floatval($listingData['weight_per_unit']);
                            } elseif (isset($listingData['unit_weight_kg'])) {
                                $weightKg = floatval($listingData['unit_weight_kg']);
                            } elseif (isset($listingData['weight'])) {
                                $weightKg = floatval($listingData['weight']);
                            } else {
                                $weightKg = estimateFoodWeight($listingData, $quantity);
                            }
                            
                            $contribution['food_saved'] += ($weightKg * $quantity);
                        }
                    }
                    
                    // Calculate revenue and savings
                    if (isset($listingData['discounted_price']) && isset($listingData['quantity'])) {
                        $price = floatval($listingData['discounted_price']);
                        $quantity = intval($listingData['quantity']);
                        $revenue = $price * $quantity;
                        $contribution['total_spent'] += $revenue; // For providers, this represents revenue
                        $contribution['total_savings'] += ($revenue * 0.5); // Estimated savings for consumers
                    }
                }
            }
        } catch (Exception $userEx) {
            // Skip this user if there's an error, but continue with others
            error_log('Error processing user ' . $userId . ': ' . $userEx->getMessage());
            continue;
        }
        
        // Only include users with some activity
        if ($contribution['food_saved'] > 0 || $contribution['total_orders'] > 0 || $contribution['total_listings'] > 0) {
            $userContributions[] = $contribution;
        }
    }
    
    // Sort by food saved (descending)
    usort($userContributions, function($a, $b) {
        return $b['food_saved'] <=> $a['food_saved'];
    });
    
    $responseJson = json_encode([
        'success' => true,
        'data' => $userContributions,
        'count' => count($userContributions),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Best-effort write to cache
    @file_put_contents($cacheFile, $responseJson);

    echo $responseJson;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Estimate food weight based on listing data
 */
function estimateFoodWeight($listingData, $quantity) {
    $defaultWeightPerUnit = 0.5; // 500g per unit as default
    
    $type = $listingData['type'] ?? 'other';
    $name = strtolower($listingData['name'] ?? '');
    
    switch ($type) {
        case 'main_dish':
            $defaultWeightPerUnit = 0.8;
            break;
        case 'side_dish':
            $defaultWeightPerUnit = 0.3;
            break;
        case 'dessert':
            $defaultWeightPerUnit = 0.2;
            break;
        case 'beverage':
            $defaultWeightPerUnit = 0.5;
            break;
        case 'snack':
            $defaultWeightPerUnit = 0.1;
            break;
        case 'pickup':
            if (strpos($name, 'ensaymada') !== false || strpos($name, 'bread') !== false || strpos($name, 'pastry') !== false) {
                $defaultWeightPerUnit = 0.15;
            } elseif (strpos($name, 'rice') !== false || strpos($name, 'meal') !== false) {
                $defaultWeightPerUnit = 0.6;
            } else {
                $defaultWeightPerUnit = 0.3;
            }
            break;
        default:
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
?>

