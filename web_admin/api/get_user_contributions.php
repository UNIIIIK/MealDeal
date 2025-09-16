<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Get all users
    $usersRef = $db->getCollection('users');
    $users = $usersRef->limit(200)->documents();
    
    $userContributions = [];
    
    foreach ($users as $user) {
        $userData = $user->data();
        $userId = $user->id();
        
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
        
        if ($userData['role'] === 'food_consumer') {
            // Calculate consumer contributions
            $cartsRef = $db->getCollection('cart');
            $consumerOrders = $cartsRef->where('consumer_id', '=', $userId)->limit(100)->documents();
            
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
                
                // Calculate food saved from items
                if (isset($orderData['items']) && is_array($orderData['items'])) {
                    foreach ($orderData['items'] as $item) {
                        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                        
                        // Try to get weight from item first
                        if (isset($item['weight_per_unit'])) {
                            $weightKg = floatval($item['weight_per_unit']);
                            $contribution['food_saved'] += ($quantity * $weightKg);
                            continue;
                        }
                        
                        // Fallback: look up the listing's weight
                        try {
                            if (isset($item['listing_id'])) {
                                $listingDoc = $db->getDocument('listings', $item['listing_id'])->snapshot();
                                if ($listingDoc->exists()) {
                                    $listingData = $listingDoc->data();
                                    $weightKg = 0;
                                    
                                    if (isset($listingData['weight_per_unit'])) {
                                        $weightKg = floatval($listingData['weight_per_unit']);
                                    } elseif (isset($listingData['unit_weight_kg'])) {
                                        $weightKg = floatval($listingData['unit_weight_kg']);
                                    } elseif (isset($listingData['weight'])) {
                                        $weightKg = floatval($listingData['weight']);
                                    } else {
                                        // Estimate weight based on food type
                                        $weightKg = estimateFoodWeight($listingData, $quantity);
                                    }
                                    
                                    if ($weightKg > 0 && $quantity > 0) {
                                        $contribution['food_saved'] += ($quantity * $weightKg);
                                    }
                                }
                            }
                        } catch (Exception $lookupEx) {
                            // Best-effort only
                            error_log('User contribution listing lookup failed: ' . $lookupEx->getMessage());
                        }
                    }
                }
            }
            
        } elseif ($userData['role'] === 'food_provider') {
            // Calculate provider contributions
            $listingsRef = $db->getCollection('listings');
            $providerListings = $listingsRef->where('provider_id', '=', $userId)->limit(100)->documents();
            
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
        
        // Only include users with some activity
        if ($contribution['food_saved'] > 0 || $contribution['total_orders'] > 0 || $contribution['total_listings'] > 0) {
            $userContributions[] = $contribution;
        }
    }
    
    // Sort by food saved (descending)
    usort($userContributions, function($a, $b) {
        return $b['food_saved'] <=> $a['food_saved'];
    });
    
    echo json_encode([
        'success' => true,
        'data' => $userContributions,
        'count' => count($userContributions),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
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
