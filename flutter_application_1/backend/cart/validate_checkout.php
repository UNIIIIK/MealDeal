<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['user_id', 'cart_id', 'items', 'total_price'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
    ]);
    exit();
}

$userId = $input['user_id'];
$cartId = $input['cart_id'];
$items = $input['items'];
$totalPrice = $input['total_price'];

try {
    // Validate user exists and has consumer role
    $userValidation = validateUser($userId);
    if (!$userValidation['success']) {
        http_response_code(403);
        echo json_encode($userValidation);
        exit();
    }

    // Validate cart belongs to user
    $cartValidation = validateCart($cartId, $userId);
    if (!$cartValidation['success']) {
        http_response_code(400);
        echo json_encode($cartValidation);
        exit();
    }

    // Validate items availability and pricing
    $itemsValidation = validateItems($items);
    if (!$itemsValidation['success']) {
        http_response_code(400);
        echo json_encode($itemsValidation);
        exit();
    }

    // Validate total price calculation
    $priceValidation = validateTotalPrice($items, $totalPrice);
    if (!$priceValidation['success']) {
        http_response_code(400);
        echo json_encode($priceValidation);
        exit();
    }

    // Check for any expired items
    $expiryValidation = validateItemExpiry($items);
    if (!$expiryValidation['success']) {
        http_response_code(400);
        echo json_encode($expiryValidation);
        exit();
    }

    // All validations passed
    echo json_encode([
        'success' => true,
        'message' => 'Checkout validation successful',
        'data' => [
            'user_id' => $userId,
            'cart_id' => $cartId,
            'item_count' => count($items),
            'total_price' => $totalPrice,
            'validated_at' => date('c'),
            'order_id' => generateOrderId()
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Checkout validation error: ' . $e->getMessage()
    ]);
}

/**
 * Validate user exists and has food_consumer role
 */
function validateUser($userId) {
    try {
        $userData = getUserFromFirestore($userId);
        
        if (!$userData) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        if ($userData['role'] !== 'food_consumer') {
            return [
                'success' => false,
                'message' => 'Only food consumers can checkout'
            ];
        }
        
        if (!($userData['verified'] ?? false)) {
            return [
                'success' => false,
                'message' => 'User email not verified'
            ];
        }
        
        return [
            'success' => true,
            'user' => $userData
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'User validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Validate cart belongs to user and is in pending status
 */
function validateCart($cartId, $userId) {
    try {
        $cartData = getCartFromFirestore($cartId);
        
        if (!$cartData) {
            return [
                'success' => false,
                'message' => 'Cart not found'
            ];
        }
        
        if ($cartData['consumer_id'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Cart does not belong to user'
            ];
        }
        
        if ($cartData['status'] !== 'pending') {
            return [
                'success' => false,
                'message' => 'Cart is not in pending status'
            ];
        }
        
        return [
            'success' => true,
            'cart' => $cartData
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Cart validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Validate all items in cart are available and active
 */
function validateItems($items) {
    try {
        if (empty($items)) {
            return [
                'success' => false,
                'message' => 'Cart is empty'
            ];
        }
        
        $unavailableItems = [];
        $insufficientQuantityItems = [];
        
        foreach ($items as $item) {
            if (!isset($item['listing_id']) || !isset($item['quantity'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid item format'
                ];
            }
            
            $listing = getListingFromFirestore($item['listing_id']);
            
            if (!$listing) {
                $unavailableItems[] = $item['title'] ?? $item['listing_id'];
                continue;
            }
            
            if ($listing['status'] !== 'active') {
                $unavailableItems[] = $item['title'] ?? $item['listing_id'];
                continue;
            }
            
            if ($listing['quantity'] < $item['quantity']) {
                $insufficientQuantityItems[] = [
                    'title' => $item['title'] ?? $item['listing_id'],
                    'requested' => $item['quantity'],
                    'available' => $listing['quantity']
                ];
            }
        }
        
        if (!empty($unavailableItems)) {
            return [
                'success' => false,
                'message' => 'Some items are no longer available: ' . implode(', ', $unavailableItems)
            ];
        }
        
        if (!empty($insufficientQuantityItems)) {
            return [
                'success' => false,
                'message' => 'Insufficient quantity for some items',
                'details' => $insufficientQuantityItems
            ];
        }
        
        return [
            'success' => true,
            'message' => 'All items are available'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Items validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Validate total price calculation
 */
function validateTotalPrice($items, $expectedTotal) {
    try {
        $calculatedTotal = 0;
        
        foreach ($items as $item) {
            $listing = getListingFromFirestore($item['listing_id']);
            
            if ($listing) {
                $itemTotal = $listing['discounted_price'] * $item['quantity'];
                $calculatedTotal += $itemTotal;
            }
        }
        
        // Allow small floating point differences
        $difference = abs($calculatedTotal - $expectedTotal);
        if ($difference > 0.01) {
            return [
                'success' => false,
                'message' => 'Total price mismatch',
                'calculated_total' => $calculatedTotal,
                'expected_total' => $expectedTotal
            ];
        }
        
        return [
            'success' => true,
            'calculated_total' => $calculatedTotal
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Price validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Validate items haven't expired
 */
function validateItemExpiry($items) {
    try {
        $expiredItems = [];
        $currentTime = new DateTime();
        
        foreach ($items as $item) {
            $listing = getListingFromFirestore($item['listing_id']);
            
            if ($listing && isset($listing['expiry_datetime'])) {
                $expiryDate = new DateTime($listing['expiry_datetime']);
                
                if ($expiryDate <= $currentTime) {
                    $expiredItems[] = $item['title'] ?? $item['listing_id'];
                }
            }
        }
        
        if (!empty($expiredItems)) {
            return [
                'success' => false,
                'message' => 'Some items have expired: ' . implode(', ', $expiredItems)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'All items are within expiry date'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Expiry validation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate unique order ID
 */
function generateOrderId() {
    return 'ORD_' . date('Ymd') . '_' . strtoupper(substr(uniqid(), -8));
}

/**
 * Placeholder functions for Firestore operations
 * In production, implement with Firebase Admin SDK
 */
function getUserFromFirestore($userId) {
    // Placeholder implementation
    return [
        'uid' => $userId,
        'role' => 'food_consumer',
        'verified' => true,
        'name' => 'Test Consumer'
    ];
}

function getCartFromFirestore($cartId) {
    // Placeholder implementation
    return [
        'cart_id' => $cartId,
        'consumer_id' => 'test_user_id',
        'status' => 'pending',
        'items' => [],
        'total_price' => 0
    ];
}

function getListingFromFirestore($listingId) {
    // Placeholder implementation
    return [
        'listing_id' => $listingId,
        'status' => 'active',
        'quantity' => 10,
        'discounted_price' => 15.99,
        'expiry_datetime' => date('c', strtotime('+1 day'))
    ];
}

/**
 * Additional security functions
 */
function sanitizeInput($input) {
    if (is_string($input)) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

function logCheckoutAttempt($userId, $cartId, $success, $message = '') {
    // Log checkout attempt for security monitoring
    $logData = [
        'user_id' => $userId,
        'cart_id' => $cartId,
        'success' => $success,
        'message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('c')
    ];
    
    error_log('Checkout attempt: ' . json_encode($logData));
}
?>
