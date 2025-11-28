<?php
// includes/stats.php
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\FieldValue;

class Stats {
    private $db;
    private $statsDoc;

    public function __construct() {
        $this->db = new FirestoreClient([
            'projectId' => 'mealdeal-10385', // ✅ use your real projectId
        ]);
        $this->statsDoc = $this->db->collection('stats')->document('global');
    }

    /**
     * Increment a counter field safely.
     * Creates the document if it does not exist.
     */
    public function increment($field, $value = 1) {
        $this->statsDoc->set([
            $field => FieldValue::increment($value)
        ], ['merge' => true]);
    }

    /**
     * Add to revenue_total safely.
     */
    public function addRevenue($amount) {
        $this->statsDoc->set([
            'revenue_total' => FieldValue::increment(floatval($amount))
        ], ['merge' => true]);
    }

    /**
     * Reset stats document (⚠️ usually for debugging only).
     */
    public function reset() {
        $this->statsDoc->set([
            'users_count' => 0,
            'posts_count' => 0,
            'orders_count' => 0,
            'revenue_total' => 0
        ]);
    }
}
