<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $reportId = $_GET['id'] ?? '';
    
    if (empty($reportId)) {
        throw new Exception('Report ID is required');
    }
    
    $db = Database::getInstance();
    $reportDoc = $db->getDocument('reports', $reportId);
    $reportData = $reportDoc->snapshot()->data();
    
    if (!$reportDoc->snapshot()->exists()) {
        throw new Exception('Report not found');
    }
    
    // Resolve names if not denormalized
    $reporterName = $reportData['reporter_name'] ?? ($reportData['reporter_email'] ?? null);
    $targetName = $reportData['target_name'] ?? ($reportData['listing_title'] ?? ($reportData['provider_name'] ?? null));
    
    try {
        if (!$reporterName && isset($reportData['reporter_id'])) {
            $uSnap = $db->getDocument('users', $reportData['reporter_id'])->snapshot();
            if ($uSnap->exists()) {
                $uData = $uSnap->data();
                $reporterName = $uData['name'] ?? ($uData['email'] ?? 'Anonymous');
            }
        }
        
        if (!$targetName && isset($reportData['target_user_id'])) {
            $tSnap = $db->getDocument('users', $reportData['target_user_id'])->snapshot();
            if ($tSnap->exists()) {
                $tData = $tSnap->data();
                $targetName = $tData['name'] ?? ($tData['email'] ?? '—');
            }
        }
        
        // If this report targets a listing, use listing title/provider
        if (!$targetName && isset($reportData['target_listing_id'])) {
            $lSnap = $db->getDocument('listings', $reportData['target_listing_id'])->snapshot();
            if ($lSnap->exists()) {
                $lData = $lSnap->data();
                $targetName = $lData['title'] ?? ($lData['name'] ?? 'Listing');
                // Optional: append provider name if available
                if (isset($lData['provider_id'])) {
                    $pSnap = $db->getDocument('users', $lData['provider_id'])->snapshot();
                    if ($pSnap->exists()) {
                        $pData = $pSnap->data();
                        $providerName = $pData['name'] ?? ($pData['email'] ?? null);
                        if ($providerName) {
                            $targetName .= ' • ' . $providerName;
                        }
                    }
                }
            }
        }
        // Alternate schema: provider_id directly on report
        if (!$targetName && isset($reportData['provider_id'])) {
            $pSnap = $db->getDocument('users', $reportData['provider_id'])->snapshot();
            if ($pSnap->exists()) {
                $pData = $pSnap->data();
                $targetName = ($reportData['listing_title'] ?? 'Listing') . ' • ' . ($pData['name'] ?? ($pData['email'] ?? 'Provider'));
            }
        }
    } catch (Exception $e) {
        error_log('Report name resolution failed: ' . $e->getMessage());
    }
    
    // Format created_at timestamp
    $createdAt = null;
    if (isset($reportData['created_at'])) {
        $timestamp = $reportData['created_at'];
        if ($timestamp instanceof \Google\Cloud\Core\Timestamp) {
            $createdAt = $timestamp->get()->format('Y-m-d H:i:s');
        } elseif (is_array($timestamp) && isset($timestamp['seconds'])) {
            $createdAt = date('Y-m-d H:i:s', (int)$timestamp['seconds']);
        } elseif (is_numeric($timestamp)) {
            $createdAt = date('Y-m-d H:i:s', (int)$timestamp);
        }
    }
    
    $report = [
        'id' => $reportId,
        'type' => $reportData['type'] ?? 'other',
        'status' => $reportData['status'] ?? 'pending',
        'reporter_name' => $reporterName ?? 'Anonymous',
        'target_name' => $targetName ?? 'Unknown',
        'description' => $reportData['description'] ?? '',
        'admin_notes' => $reportData['admin_notes'] ?? null,
        'created_at' => $createdAt
    ];
    
    echo json_encode([
        'success' => true,
        'report' => $report,
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
?>
