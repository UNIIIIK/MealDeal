<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET or POST.'
    ]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Manual safety check for specific listing
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['listing_id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required field: listing_id'
            ]);
            exit();
        }
        
        $result = checkSingleListing($input['listing_id']);
        echo json_encode($result);
        
    } else {
        // Automated safety check for all active listings
        $result = performAutomatedSafetyCheck();
        echo json_encode($result);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Safety check error: ' . $e->getMessage()
    ]);
}

/**
 * Check safety of a single listing
 */
function checkSingleListing($listingId) {
    try {
        $listing = getListingFromFirestore($listingId);
        
        if (!$listing) {
            return [
                'success' => false,
                'message' => 'Listing not found'
            ];
        }
        
        $safetyIssues = performSafetyChecks($listing);
        
        if (!empty($safetyIssues)) {
            // Take action based on severity
            $action = determineSafetyAction($safetyIssues);
            applySafetyAction($listingId, $action, $safetyIssues);
            
            return [
                'success' => true,
                'message' => 'Safety issues found and action taken',
                'data' => [
                    'listing_id' => $listingId,
                    'issues' => $safetyIssues,
                    'action_taken' => $action
                ]
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Listing passed safety check',
            'data' => [
                'listing_id' => $listingId,
                'status' => 'safe'
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error checking listing: ' . $e->getMessage()
        ];
    }
}

/**
 * Perform automated safety check on all active listings
 */
function performAutomatedSafetyCheck() {
    try {
        $activeListings = getActiveListingsFromFirestore();
        $checkedCount = 0;
        $issuesFound = 0;
        $actionsPerformed = [];
        
        foreach ($activeListings as $listingId => $listing) {
            $checkedCount++;
            
            $safetyIssues = performSafetyChecks($listing);
            
            if (!empty($safetyIssues)) {
                $issuesFound++;
                $action = determineSafetyAction($safetyIssues);
                applySafetyAction($listingId, $action, $safetyIssues);
                
                $actionsPerformed[] = [
                    'listing_id' => $listingId,
                    'issues' => $safetyIssues,
                    'action' => $action
                ];
            }
        }
        
        return [
            'success' => true,
            'message' => 'Automated safety check completed',
            'data' => [
                'checked_count' => $checkedCount,
                'issues_found' => $issuesFound,
                'actions_performed' => $actionsPerformed,
                'checked_at' => date('c')
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Automated check failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Perform various safety checks on a listing
 */
function performSafetyChecks($listing) {
    $issues = [];
    
    // Check 1: Expiry date
    if (isset($listing['expiry_datetime'])) {
        $expiryDate = new DateTime($listing['expiry_datetime']);
        $currentDate = new DateTime();
        
        if ($expiryDate <= $currentDate) {
            $issues[] = [
                'type' => 'expired',
                'severity' => 'high',
                'message' => 'Food item has expired'
            ];
        } elseif ($expiryDate <= $currentDate->add(new DateInterval('PT2H'))) {
            $issues[] = [
                'type' => 'expiring_soon',
                'severity' => 'medium',
                'message' => 'Food item expires within 2 hours'
            ];
        }
    }
    
    // Check 2: Age of listing
    if (isset($listing['created_at'])) {
        $createdDate = new DateTime($listing['created_at']);
        $currentDate = new DateTime();
        $daysSinceCreated = $currentDate->diff($createdDate)->days;
        
        if ($daysSinceCreated > 7) {
            $issues[] = [
                'type' => 'stale_listing',
                'severity' => 'low',
                'message' => 'Listing is over 7 days old'
            ];
        }
    }
    
    // Check 3: Price anomalies
    if (isset($listing['original_price']) && isset($listing['discounted_price'])) {
        $originalPrice = $listing['original_price'];
        $discountedPrice = $listing['discounted_price'];
        
        if ($discountedPrice > $originalPrice) {
            $issues[] = [
                'type' => 'price_anomaly',
                'severity' => 'medium',
                'message' => 'Discounted price is higher than original price'
            ];
        }
        
        $discountPercent = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
        if ($discountPercent > 90) {
            $issues[] = [
                'type' => 'suspicious_discount',
                'severity' => 'medium',
                'message' => 'Discount percentage is suspiciously high (>90%)'
            ];
        }
    }
    
    // Check 4: Missing allergen information
    if (!isset($listing['allergens']) || empty(trim($listing['allergens']))) {
        $issues[] = [
            'type' => 'missing_allergens',
            'severity' => 'high',
            'message' => 'Missing allergen information'
        ];
    }
    
    // Check 5: Quantity anomalies
    if (isset($listing['quantity']) && $listing['quantity'] <= 0) {
        $issues[] = [
            'type' => 'zero_quantity',
            'severity' => 'high',
            'message' => 'Listing has zero or negative quantity'
        ];
    }
    
    // Check 6: Report count
    $reportCount = getReportCount($listing['listing_id'] ?? '');
    if ($reportCount >= 3) {
        $issues[] = [
            'type' => 'multiple_reports',
            'severity' => 'high',
            'message' => "Listing has {$reportCount} reports"
        ];
    }
    
    return $issues;
}

/**
 * Determine what action to take based on safety issues
 */
function determineSafetyAction($issues) {
    $highSeverityCount = 0;
    $mediumSeverityCount = 0;
    
    foreach ($issues as $issue) {
        if ($issue['severity'] === 'high') {
            $highSeverityCount++;
        } elseif ($issue['severity'] === 'medium') {
            $mediumSeverityCount++;
        }
    }
    
    // Determine action based on severity
    if ($highSeverityCount >= 2) {
        return 'remove';
    } elseif ($highSeverityCount >= 1) {
        return 'hide';
    } elseif ($mediumSeverityCount >= 3) {
        return 'flag';
    } elseif ($mediumSeverityCount >= 1) {
        return 'warn';
    }
    
    return 'monitor';
}

/**
 * Apply safety action to listing
 */
function applySafetyAction($listingId, $action, $issues) {
    try {
        switch ($action) {
            case 'remove':
                updateListingStatus($listingId, 'removed', 'safety_violation');
                notifyProvider($listingId, 'removed', $issues);
                break;
                
            case 'hide':
                updateListingStatus($listingId, 'hidden', 'safety_concern');
                notifyProvider($listingId, 'hidden', $issues);
                break;
                
            case 'flag':
                updateListingStatus($listingId, 'flagged', 'safety_review');
                notifyAdmins($listingId, 'flagged', $issues);
                break;
                
            case 'warn':
                notifyProvider($listingId, 'warning', $issues);
                break;
                
            case 'monitor':
                logSafetyEvent($listingId, 'monitoring', $issues);
                break;
        }
        
        // Log all safety actions
        logSafetyAction($listingId, $action, $issues);
        
    } catch (Exception $e) {
        error_log("Failed to apply safety action: " . $e->getMessage());
    }
}

/**
 * Placeholder functions for Firestore operations
 * In production, implement with Firebase Admin SDK
 */
function getListingFromFirestore($listingId) {
    // Placeholder - implement with Firebase Admin SDK
    return [
        'listing_id' => $listingId,
        'expiry_datetime' => date('c', strtotime('+1 day')),
        'created_at' => date('c', strtotime('-1 day')),
        'original_price' => 20.00,
        'discounted_price' => 16.00,
        'quantity' => 1,
        'allergens' => 'nuts, dairy'
    ];
}

function getActiveListingsFromFirestore() {
    // Placeholder - implement with Firebase Admin SDK
    return [];
}

function updateListingStatus($listingId, $status, $reason) {
    // Placeholder - implement with Firebase Admin SDK
    error_log("Listing {$listingId} status updated to {$status} due to {$reason}");
}

function getReportCount($listingId) {
    // Placeholder - implement with Firebase Admin SDK
    return 0;
}

function notifyProvider($listingId, $action, $issues) {
    // Placeholder - implement notification system
    error_log("Provider notified: Listing {$listingId} {$action}");
}

function notifyAdmins($listingId, $action, $issues) {
    // Placeholder - implement admin notification system
    error_log("Admins notified: Listing {$listingId} {$action}");
}

function logSafetyEvent($listingId, $event, $issues) {
    // Placeholder - implement logging system
    error_log("Safety event logged: {$listingId} - {$event}");
}

function logSafetyAction($listingId, $action, $issues) {
    // Placeholder - implement action logging
    error_log("Safety action logged: {$listingId} - {$action}");
}
?>
