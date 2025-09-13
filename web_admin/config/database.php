<?php
// Firebase/Firestore Configuration
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

class Database {
    private static $instance = null;
    private $firestore;

    private function __construct() {
        try {
            // Check if credentials file exists
            $keyPath = __DIR__ . '/firebase-credentials.json';
            if (!file_exists($keyPath)) {
                throw new Exception('Firebase credentials file not found at: ' . $keyPath);
            }
            
            // Validate JSON structure
            $keyData = json_decode(file_get_contents($keyPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in credentials file: ' . json_last_error_msg());
            }
            
            // Initialize Firestore client with error handling
            $this->firestore = new FirestoreClient([
                'projectId' => 'mealdeal-10385',
                'keyFilePath' => $keyPath,
                'retries' => 1, // Limit retries to prevent infinite loops
                'timeout' => 10 // 10 second timeout
            ]);
        } catch (Exception $e) {
            error_log('Firestore initialization failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getFirestore() {
        return $this->firestore;
    }

    // Helper methods for common operations
    public function getCollection($collectionName) {
        return $this->firestore->collection($collectionName);
    }

    public function getDocument($collectionName, $documentId) {
        return $this->firestore->collection($collectionName)->document($documentId);
    }

    public function addDocument($collectionName, $data) {
        return $this->firestore->collection($collectionName)->add($data);
    }

    public function updateDocument($collectionName, $documentId, $data) {
        return $this->firestore->collection($collectionName)->document($documentId)->set($data, ['merge' => true]);
    }

    public function deleteDocument($collectionName, $documentId) {
        return $this->firestore->collection($collectionName)->document($documentId)->delete();
    }

    public function queryCollection($collectionName, $conditions = []) {
        $query = $this->firestore->collection($collectionName);
        
        foreach ($conditions as $condition) {
            $query = $query->where($condition['field'], $condition['operator'], $condition['value']);
        }
        
        return $query;
    }
}

// Global database instance
$db = Database::getInstance();
?>
