<?php
/**
 * Users API Endpoint
 * 
 * @package HR3
 * @subpackage API
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

use HR3\Config\Auth;
use HR3\Config\Database;

Auth::requireAuth();

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $db = Database::getConnection();
    
    switch ($action) {
        case 'get_all':
            Auth::requireRole(['Admin', 'Manager']);
            
            $sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, 
                           r.role_name, d.department_name, u.status
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.role_id
                    LEFT JOIN departments d ON u.department_id = d.department_id
                    WHERE u.status = 'active'
                    ORDER BY u.first_name, u.last_name";
            
            $stmt = $db->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'data' => $users];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
    
} catch (\Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ];
    http_response_code(500);
}

echo json_encode($response);