<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

function formatTimestamp($value) {
    try {
        if ($value instanceof Google\Cloud\Core\Timestamp) {
            $dt = $value->get();
            if ($dt instanceof DateTimeInterface) {
                return $dt->format('Y-m-d H:i:s');
            }
        } elseif (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int)$value);
        } elseif (is_string($value)) {
            $t = strtotime($value);
            if ($t !== false) {
                return date('Y-m-d H:i:s', $t);
            }
        }
    } catch (Exception $e) {}
    return null;
}

try {
    $db = Database::getInstance();
    $usersRef = $db->getCollection('users');
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $role = isset($_GET['role']) ? $_GET['role'] : null;
    
    $query = $usersRef;
    if ($role) {
        $query = $query->where('role', '=', $role);
    }
    
    $users = $query->limit($limit)->documents();
    
    $userList = [];
    foreach ($users as $user) {
        $userData = $user->data();
        $userList[] = [
            'id' => $user->id(),
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? '',
            'role' => $userData['role'] ?? 'Unknown',
            'verified' => $userData['verified'] ?? false,
            'created_at' => isset($userData['created_at']) ? formatTimestamp($userData['created_at']) : null,
            'phone' => $userData['phone'] ?? null,
            'address' => $userData['address'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $userList,
        'count' => count($userList),
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
