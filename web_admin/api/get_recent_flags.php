<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Get recent reports that could be considered flags
    $reportsRef = $db->getCollection('reports');
    $reports = $reportsRef->limit(20)->documents();
    
    $flags = [];
    
    foreach ($reports as $report) {
        $reportData = $report->data();
        
        // Only include reports that could be considered flags for listings
        if (isset($reportData['type']) && in_array($reportData['type'], ['inappropriate', 'spam', 'misleading', 'quality', 'safety', 'pricing'])) {
            $flags[] = [
                'id' => $report->id(),
                'title' => $reportData['reason'] ?? 'Content Flag',
                'description' => $reportData['description'] ?? 'No description provided',
                'type' => $reportData['type'] ?? 'general',
                'priority' => $reportData['priority'] ?? 'medium',
                'status' => $reportData['status'] ?? 'pending',
                'reporter_name' => $reportData['reporter_email'] ?? 'Anonymous',
                'reporter_id' => $reportData['reporter_id'] ?? null,
                'created_at' => isset($reportData['created_at']) ? 
                    $reportData['created_at']->toDateTime()->format('Y-m-d H:i:s') : 
                    date('Y-m-d H:i:s'),
                'resolved_at' => isset($reportData['resolved_at']) ? 
                    $reportData['resolved_at']->toDateTime()->format('Y-m-d H:i:s') : 
                    null,
                'resolved_by' => $reportData['resolved_by'] ?? null,
                'related_listing_id' => $reportData['listing_id'] ?? null,
                'related_user_id' => $reportData['reported_user_id'] ?? null,
                'evidence' => $reportData['evidence'] ?? null
            ];
        }
    }
    
    // Sort by creation date (newest first)
    usort($flags, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to 10 most recent
    $flags = array_slice($flags, 0, 10);
    
    echo json_encode([
        'success' => true,
        'data' => $flags,
        'count' => count($flags),
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
