<?php
// Firebase/Firestore Configuration
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

class Database {
    private static $instance = null;
    private $firestore;

    private function __construct() {
        // Initialize Firestore client
        $this->firestore = new FirestoreClient([
            'projectId' => 'mealdeal-10385', // Replace with your Firebase project ID
            'keyFilePath' => __DIR__ . '/firebase-credentials.json' // Path to your Firebase service account key
        ]);
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
