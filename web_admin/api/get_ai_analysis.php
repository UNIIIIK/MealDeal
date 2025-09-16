<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Get listings for AI analysis
    $listingsRef = $db->getCollection('listings');
    $listings = $listingsRef->limit(50)->documents();
    
    $analysis = [
        'overall_quality' => 0,
        'image_issues' => 0,
        'safety_issues' => 0,
        'pricing_issues' => 0,
        'recommendations' => '',
        'total_analyzed' => 0,
        'issues_by_category' => []
    ];
    
    $totalQuality = 0;
    $analyzedCount = 0;
    
    foreach ($listings as $listing) {
        $listingData = $listing->data();
        $analysis['total_analyzed']++;
        
        // Simulate AI analysis results
        $qualityScore = rand(70, 95); // Random quality score between 70-95
        $totalQuality += $qualityScore;
        $analyzedCount++;
        
        // Simulate issue detection
        if ($qualityScore < 80) {
            $analysis['image_issues']++;
        }
        
        // Check for pricing compliance (50% minimum discount)
        if (isset($listingData['original_price']) && isset($listingData['discounted_price'])) {
            $originalPrice = floatval($listingData['original_price']);
            $discountedPrice = floatval($listingData['discounted_price']);
            
            if ($originalPrice > 0) {
                $discountPercentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
                if ($discountPercentage < 50) {
                    $analysis['pricing_issues']++;
                }
            }
        }
        
        // Simulate content safety checks
        $title = strtolower($listingData['title'] ?? '');
        $description = strtolower($listingData['description'] ?? '');
        
        // Check for potentially problematic content
        $problematicWords = ['expired', 'spoiled', 'rotten', 'moldy', 'bad', 'terrible'];
        foreach ($problematicWords as $word) {
            if (strpos($title, $word) !== false || strpos($description, $word) !== false) {
                $analysis['safety_issues']++;
                break;
            }
        }
    }
    
    if ($analyzedCount > 0) {
        $analysis['overall_quality'] = round($totalQuality / $analyzedCount);
    }
    
    // Generate recommendations based on analysis
    $recommendations = [];
    
    if ($analysis['image_issues'] > 0) {
        $recommendations[] = "Review {$analysis['image_issues']} listings with poor image quality";
    }
    
    if ($analysis['safety_issues'] > 0) {
        $recommendations[] = "Investigate {$analysis['safety_issues']} listings flagged for content safety";
    }
    
    if ($analysis['pricing_issues'] > 0) {
        $recommendations[] = "Check {$analysis['pricing_issues']} listings for pricing compliance (minimum 50% discount)";
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "All listings appear to meet quality standards";
    }
    
    $analysis['recommendations'] = implode('. ', $recommendations);
    
    // Add category breakdown
    $analysis['issues_by_category'] = [
        'image_quality' => $analysis['image_issues'],
        'content_safety' => $analysis['safety_issues'],
        'pricing_compliance' => $analysis['pricing_issues']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $analysis,
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
