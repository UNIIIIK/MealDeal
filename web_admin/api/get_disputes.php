<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Get disputes from reports collection (using reports as disputes)
    $reportsRef = $db->getCollection('reports');
    $reports = $reportsRef->limit(50)->documents();
    
    $disputes = [];
    
    foreach ($reports as $report) {
        $reportData = $report->data();
        
        // Only include reports that could be considered disputes
        if (isset($reportData['type']) && in_array($reportData['type'], ['dispute', 'complaint', 'issue', 'problem'])) {
            $disputes[] = [
                'id' => $report->id(),
                'title' => $reportData['reason'] ?? 'Dispute Report',
                'description' => $reportData['description'] ?? 'No description provided',
                'reporter_name' => $reportData['reporter_email'] ?? 'Anonymous',
                'reporter_id' => $reportData['reporter_id'] ?? null,
                'status' => $reportData['status'] ?? 'pending',
                'priority' => $reportData['priority'] ?? 'medium',
                'created_at' => isset($reportData['created_at']) ? 
                    $reportData['created_at']->toDateTime()->format('Y-m-d H:i:s') : 
                    date('Y-m-d H:i:s'),
                'resolved_at' => isset($reportData['resolved_at']) ? 
                    $reportData['resolved_at']->toDateTime()->format('Y-m-d H:i:s') : 
                    null,
                'resolved_by' => $reportData['resolved_by'] ?? null,
                'category' => $reportData['type'] ?? 'general',
                'related_listing_id' => $reportData['listing_id'] ?? null,
                'related_user_id' => $reportData['reported_user_id'] ?? null
            ];
        }
    }
    
    // Sort by creation date (newest first)
    usort($disputes, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode([
        'success' => true,
        'data' => $disputes,
        'count' => count($disputes),
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
