<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

// Allow slower Firestore responses on Windows/dev setups
$scriptTimeout = getenv('MEALDEAL_API_TIMEOUT');
$scriptTimeout = is_numeric($scriptTimeout) ? max(15, (int)$scriptTimeout) : 45;
set_time_limit($scriptTimeout);

// Simple micro-cache (60s) for alerts
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'md_pricing_alerts_cache.json';
$cacheTtlSeconds = 60;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtlSeconds) {
    $cached = @file_get_contents($cacheFile);
    if ($cached) {
        echo $cached;
        exit;
    }
}

try {
    $db = Database::getInstance()->getFirestore();
    
    // Get listings for pricing analysis - reduced limit for faster response
    $alertFields = [
        'title',
        'original_price',
        'discounted_price',
        'status',
        'created_at'
    ];
    $listingsRef = $db->collection('listings')->select($alertFields);
    $listings = $listingsRef->limit(30)->documents(); // Reduced from 50 to 30
    
    $alerts = [];
    $processedCount = 0;
    
    foreach ($listings as $listing) {
        if ($processedCount >= 30) break; // Safety limit
        $processedCount++;
        $listingData = $listing->data();
        
        // Calculate discount percentage
        $originalPrice = floatval($listingData['original_price'] ?? 0);
        $discountedPrice = floatval($listingData['discounted_price'] ?? 0);
        
        if ($originalPrice > 0) {
            $discountPercentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
            
            // Generate alerts based on pricing violations
            if ($discountPercentage < 50) {
                $alerts[] = [
                    'id' => $listing->id() . '_pricing_violation',
                    'title' => 'Pricing Violation Detected',
                    'description' => "Listing '{$listingData['title']}' has only {$discountPercentage}% discount (minimum 50% required)",
                    'type' => 'pricing_violation',
                    'priority' => 'high',
                    'status' => 'pending',
                    'listing_id' => $listing->id(),
                    'listing_title' => $listingData['title'] ?? 'Unknown',
                    'original_price' => $originalPrice,
                    'discounted_price' => $discountedPrice,
                    'discount_percentage' => round($discountPercentage, 1),
                    'created_at' => date('Y-m-d H:i:s'),
                    'resolved_at' => null,
                    'resolved_by' => null
                ];
            } elseif ($discountPercentage < 60) {
                $alerts[] = [
                    'id' => $listing->id() . '_pricing_warning',
                    'title' => 'Pricing Warning',
                    'description' => "Listing '{$listingData['title']}' has {$discountPercentage}% discount (close to 50% minimum)",
                    'type' => 'pricing_warning',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'listing_id' => $listing->id(),
                    'listing_title' => $listingData['title'] ?? 'Unknown',
                    'original_price' => $originalPrice,
                    'discounted_price' => $discountedPrice,
                    'discount_percentage' => round($discountPercentage, 1),
                    'created_at' => date('Y-m-d H:i:s'),
                    'resolved_at' => null,
                    'resolved_by' => null
                ];
            }
            
            // Check for suspiciously high discounts (potential data entry errors)
            if ($discountPercentage > 90) {
                $alerts[] = [
                    'id' => $listing->id() . '_high_discount',
                    'title' => 'Unusually High Discount',
                    'description' => "Listing '{$listingData['title']}' has {$discountPercentage}% discount (may be data entry error)",
                    'type' => 'high_discount',
                    'priority' => 'medium',
                    'status' => 'pending',
                    'listing_id' => $listing->id(),
                    'listing_title' => $listingData['title'] ?? 'Unknown',
                    'original_price' => $originalPrice,
                    'discounted_price' => $discountedPrice,
                    'discount_percentage' => round($discountPercentage, 1),
                    'created_at' => date('Y-m-d H:i:s'),
                    'resolved_at' => null,
                    'resolved_by' => null
                ];
            }
        }
        
        // Check for missing pricing information
        if ($originalPrice <= 0 || $discountedPrice <= 0) {
            $alerts[] = [
                'id' => $listing->id() . '_missing_pricing',
                'title' => 'Missing Pricing Information',
                'description' => "Listing '{$listingData['title']}' has incomplete pricing data",
                'type' => 'missing_pricing',
                'priority' => 'high',
                'status' => 'pending',
                'listing_id' => $listing->id(),
                'listing_title' => $listingData['title'] ?? 'Unknown',
                'original_price' => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_percentage' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'resolved_at' => null,
                'resolved_by' => null
            ];
        }
    }
    
    // Sort by priority and creation date
    usort($alerts, function($a, $b) {
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        $aPriority = $priorityOrder[$a['priority']] ?? 1;
        $bPriority = $priorityOrder[$b['priority']] ?? 1;
        
        if ($aPriority === $bPriority) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        }
        
        return $bPriority - $aPriority;
    });
    
    // Limit to 10 most recent alerts
    $alerts = array_slice($alerts, 0, 10);
    
    $responseJson = json_encode([
        'success' => true,
        'data' => $alerts,
        'count' => count($alerts),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

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
?>

