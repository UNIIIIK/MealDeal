<?php
// Test script to verify Firestore connection and fetch real data
require_once 'config/database.php';

echo "<h2>MealDeal Firestore Connection Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .data-table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .data-table th { background-color: #f2f2f2; }
    .stats { display: flex; gap: 20px; margin: 20px 0; }
    .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; }
</style>";

try {
    $db = Database::getInstance();
    echo "<p class='success'>✅ Database connection established successfully!</p>";
    
    // Test basic connection
    $firestore = $db->getFirestore();
    echo "<p class='info'>📊 Fetching real data from Firestore...</p>";
    
    // Get collection statistics
    $collections = ['users', 'listings', 'cart', 'reports', 'admins'];
    $stats = [];
    
    echo "<div class='stats'>";
    foreach ($collections as $collection) {
        try {
            $collectionRef = $db->getCollection($collection);
            $documents = $collectionRef->documents();
            $count = iterator_count($documents);
            $stats[$collection] = $count;
            
            echo "<div class='stat-card'>";
            echo "<h4>" . ucfirst($collection) . "</h4>";
            echo "<p><strong>$count</strong> documents</p>";
            echo "</div>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error accessing $collection: " . $e->getMessage() . "</p>";
            $stats[$collection] = 0;
        }
    }
    echo "</div>";
    
    // Fetch sample data from each collection
    foreach ($collections as $collection) {
        if ($stats[$collection] > 0) {
            echo "<h3>📋 Sample data from '$collection' collection:</h3>";
            
            try {
                $collectionRef = $db->getCollection($collection);
                $documents = $collectionRef->limit(5)->documents();
                
                $sampleData = [];
                foreach ($documents as $document) {
                    $data = $document->data();
                    $data['_id'] = $document->id();
                    $sampleData[] = $data;
                }
                
                if (!empty($sampleData)) {
                    echo "<table class='data-table'>";
                    echo "<tr>";
                    foreach (array_keys($sampleData[0]) as $key) {
                        echo "<th>" . htmlspecialchars($key) . "</th>";
                    }
                    echo "</tr>";
                    
                    foreach ($sampleData as $row) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            if (is_array($value)) {
                                echo "<td>" . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . "</td>";
                            } else {
                                echo "<td>" . htmlspecialchars((string)$value) . "</td>";
                            }
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p class='info'>No documents found in $collection</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error fetching data from $collection: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Test dashboard stats function
    echo "<h3>📊 Dashboard Statistics Test:</h3>";
    require_once 'includes/auth.php';
    $dashboardStats = getDashboardStats();
    
    echo "<div class='stats'>";
    foreach ($dashboardStats as $key => $value) {
        echo "<div class='stat-card'>";
        echo "<h4>" . ucfirst(str_replace('_', ' ', $key)) . "</h4>";
        echo "<p><strong>$value</strong></p>";
        echo "</div>";
    }
    echo "</div>";
    
    // Test recent reports
    echo "<h3>🚨 Recent Reports Test:</h3>";
    $recentReports = getRecentReports(3);
    
    if (!empty($recentReports)) {
        echo "<table class='data-table'>";
        echo "<tr><th>ID</th><th>Type</th><th>Status</th><th>Reporter</th><th>Created At</th><th>Description</th></tr>";
        foreach ($recentReports as $report) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($report['id']) . "</td>";
            echo "<td>" . htmlspecialchars($report['type']) . "</td>";
            echo "<td>" . htmlspecialchars($report['status']) . "</td>";
            echo "<td>" . htmlspecialchars($report['reporter_name']) . "</td>";
            echo "<td>" . htmlspecialchars($report['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($report['description']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No recent reports found</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Connection failed: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Admin Dashboard</a></p>";
?>
