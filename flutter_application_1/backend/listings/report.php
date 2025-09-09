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
if (!isset($input['listing_id']) || !isset($input['reporter_id']) || !isset($input['reason'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: listing_id, reporter_id, reason'
    ]);
    exit();
}

$listingId = $input['listing_id'];
$reporterId = $input['reporter_id'];
$reason = trim($input['reason']);

// Validate reason
$validReasons = [
    'Expired food',
    'Misleading description', 
    'Safety concerns',
    'Inappropriate content',
    'Spam',
    'Fraudulent listing',
    'Other'
];

if (!in_array($reason, $validReasons)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reason. Must be one of: ' . implode(', ', $validReasons)
    ]);
    exit();
}

try {
    // Check if user has already reported this listing
    $existingReport = checkExistingReport($listingId, $reporterId);
    
    if ($existingReport) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You have already reported this listing.'
        ]);
        exit();
    }

    // Create report in Firestore
    $reportData = [
        'listing_id' => $listingId,
        'reporter_id' => $reporterId,
        'reason' => $reason,
        'status' => 'pending',
        'created_at' => date('c'), // ISO 8601 format
        'reviewed_at' => null,
        'reviewer_id' => null,
        'action_taken' => null
    ];

    $reportId = createReport($reportData);

    // Check if this listing has multiple reports
    $reportCount = getReportCount($listingId);
    
    // Auto-action based on report count and severity
    $actionTaken = null;
    if ($reportCount >= 3 || in_array($reason, ['Expired food', 'Safety concerns'])) {
        $actionTaken = autoModerateListing($listingId, $reason, $reportCount);
    }

    // Log the report for admin review
    logReportForReview($reportId, $listingId, $reason, $reportCount);

    $response = [
        'success' => true,
        'message' => 'Report submitted successfully',
        'data' => [
            'report_id' => $reportId,
            'listing_id' => $listingId,
            'reason' => $reason,
            'status' => 'pending',
            'report_count' => $reportCount
        ]
    ];

    if ($actionTaken) {
        $response['data']['action_taken'] = $actionTaken;
        $response['message'] .= '. Automatic action taken due to severity.';
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing report: ' . $e->getMessage()
    ]);
}

/**
 * Check if user has already reported this listing
 */
function checkExistingReport($listingId, $reporterId) {
    // In production, query Firestore for existing reports
    // This is a placeholder implementation
    return false;
}

/**
 * Create report in Firestore
 */
function createReport($reportData) {
    // In production, use Firebase Admin SDK to create document
    // Return the generated document ID
    return 'report_' . uniqid();
}

/**
 * Get total report count for a listing
 */
function getReportCount($listingId) {
    // In production, query Firestore for report count
    // This is a placeholder implementation
    return rand(1, 5);
}

/**
 * Auto-moderate listing based on reports
 */
function autoModerateListing($listingId, $reason, $reportCount) {
    try {
        $action = null;
        
        // Determine action based on reason and count
        if ($reason === 'Expired food' || $reason === 'Safety concerns') {
            $action = 'hidden';
            hideListing($listingId, 'safety_concern');
        } elseif ($reportCount >= 5) {
            $action = 'suspended';
            suspendListing($listingId, 'multiple_reports');
        } elseif ($reportCount >= 3) {
            $action = 'flagged';
            flagListingForReview($listingId, 'multiple_reports');
        }

        // Notify listing provider
        if ($action) {
            notifyProvider($listingId, $action, $reason);
        }

        return $action;
        
    } catch (Exception $e) {
        error_log("Auto-moderation failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Hide listing from public view
 */
function hideListing($listingId, $reason) {
    // Update listing status in Firestore
    updateListingStatus($listingId, 'hidden', $reason);
}

/**
 * Suspend listing temporarily
 */
function suspendListing($listingId, $reason) {
    // Update listing status in Firestore
    updateListingStatus($listingId, 'suspended', $reason);
}

/**
 * Flag listing for manual review
 */
function flagListingForReview($listingId, $reason) {
    // Update listing with review flag
    updateListingStatus($listingId, 'flagged', $reason);
}

/**
 * Update listing status in Firestore
 */
function updateListingStatus($listingId, $status, $reason) {
    // In production, use Firebase Admin SDK
    // Placeholder implementation
    error_log("Listing {$listingId} status updated to {$status} due to {$reason}");
}

/**
 * Notify listing provider about action
 */
function notifyProvider($listingId, $action, $reason) {
    // In production, send notification to provider
    // Could be email, push notification, or in-app notification
    error_log("Provider notified: Listing {$listingId} {$action} due to {$reason}");
}

/**
 * Log report for admin review
 */
function logReportForReview($reportId, $listingId, $reason, $reportCount) {
    // Log to admin dashboard or notification system
    $logData = [
        'report_id' => $reportId,
        'listing_id' => $listingId,
        'reason' => $reason,
        'report_count' => $reportCount,
        'priority' => getPriority($reason, $reportCount),
        'logged_at' => date('c')
    ];
    
    // In production, save to admin review queue
    error_log("Report logged for review: " . json_encode($logData));
}

/**
 * Determine priority level for admin review
 */
function getPriority($reason, $reportCount) {
    if (in_array($reason, ['Expired food', 'Safety concerns'])) {
        return 'high';
    } elseif ($reportCount >= 3) {
        return 'medium';
    }
    return 'low';
}
?>
