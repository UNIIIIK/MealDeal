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
$requiredFields = ['allergens', 'expiry_datetime', 'original_price'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
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

$allergens = trim($input['allergens']);
$expiryDatetime = $input['expiry_datetime'];
$originalPrice = $input['original_price'];

try {
    // Validate allergens (required field)
    if (empty($allergens)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Allergens information is required and cannot be empty.'
        ]);
        exit();
    }

    // Validate expiry datetime
    $expiryDate = new DateTime($expiryDatetime);
    $currentDate = new DateTime();
    
    if ($expiryDate <= $currentDate) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Expiry date must be in the future.'
        ]);
        exit();
    }

    // Check if expiry is too far in the future (more than 30 days)
    $maxExpiryDate = clone $currentDate;
    $maxExpiryDate->add(new DateInterval('P30D'));
    
    if ($expiryDate > $maxExpiryDate) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Expiry date cannot be more than 30 days in the future.'
        ]);
        exit();
    }

    // Validate original price
    if (!is_numeric($originalPrice) || $originalPrice <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Original price must be a positive number.'
        ]);
        exit();
    }

    if ($originalPrice > 1000) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Original price cannot exceed $1000.'
        ]);
        exit();
    }

    // Auto-calculate discounted price (20% discount)
    $discountedPrice = round($originalPrice * 0.8, 2);

    // Validate allergens content (basic check for common allergens)
    $commonAllergens = [
        'nuts', 'peanuts', 'tree nuts', 'dairy', 'milk', 'eggs', 'soy', 'wheat', 
        'gluten', 'fish', 'shellfish', 'sesame', 'mustard', 'celery', 'lupin',
        'sulphites', 'none', 'n/a'
    ];
    
    $allergensLower = strtolower($allergens);
    $hasValidAllergen = false;
    
    foreach ($commonAllergens as $allergen) {
        if (strpos($allergensLower, $allergen) !== false) {
            $hasValidAllergen = true;
            break;
        }
    }
    
    if (!$hasValidAllergen) {
        // Warning but not blocking
        $warning = 'Please ensure allergen information is complete and accurate.';
    }

    // Calculate time until expiry for urgency scoring
    $timeUntilExpiry = $expiryDate->getTimestamp() - $currentDate->getTimestamp();
    $hoursUntilExpiry = $timeUntilExpiry / 3600;
    
    $urgencyLevel = 'low';
    if ($hoursUntilExpiry < 24) {
        $urgencyLevel = 'high';
    } elseif ($hoursUntilExpiry < 72) {
        $urgencyLevel = 'medium';
    }

    // Validation successful
    $response = [
        'success' => true,
        'message' => 'Listing validation successful',
        'data' => [
            'original_price' => $originalPrice,
            'discounted_price' => $discountedPrice,
            'discount_percentage' => 20,
            'expiry_datetime' => $expiryDatetime,
            'hours_until_expiry' => round($hoursUntilExpiry, 1),
            'urgency_level' => $urgencyLevel,
            'allergens' => $allergens,
            'validated_at' => date('Y-m-d H:i:s')
        ]
    ];

    if (isset($warning)) {
        $response['warning'] = $warning;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Validation error: ' . $e->getMessage()
    ]);
}

/**
 * Additional validation functions
 */

function validateImageUpload($imageData) {
    // Validate image file if provided
    if (!$imageData) return true;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($imageData['type'], $allowedTypes)) {
        return 'Invalid image type. Only JPEG, PNG, and WebP are allowed.';
    }
    
    if ($imageData['size'] > $maxSize) {
        return 'Image size too large. Maximum 5MB allowed.';
    }
    
    return true;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function checkProfanity($text) {
    // Basic profanity filter - expand as needed
    $profanityWords = ['spam', 'scam', 'fake', 'expired', 'rotten'];
    $textLower = strtolower($text);
    
    foreach ($profanityWords as $word) {
        if (strpos($textLower, $word) !== false) {
            return "Content contains inappropriate language: {$word}";
        }
    }
    
    return false;
}
?>
