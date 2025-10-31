<?php
/**
 * Shift Schedule API Endpoint
 * 
 * @package HR3
 * @subpackage API
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ShiftSchedule.php';
require_once __DIR__ . '/../../controllers/ShiftScheduleController.php';

use HR3\Controllers\ShiftScheduleController;
use HR3\Config\Auth;

$controller = new ShiftScheduleController();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        // Shift Template Management
        case 'create_shift':
            $response = $controller->createShift();
            break;
            
        case 'update_shift':
            $response = $controller->updateShift();
            break;
            
        case 'get_shifts':
            $response = $controller->getAllShifts();
            break;
            
        case 'archive_shift':
            $response = $controller->archiveShift();
            break;
            
        case 'restore_shift':
            $response = $controller->restoreShift();
            break;
        
        // Assignment Management
        case 'assign_shift':
            $response = $controller->assignShift();
            break;
            
        case 'update_assignment':
            $response = $controller->updateAssignment();
            break;
            
        case 'delete_assignment':
            $response = $controller->deleteAssignment();
            break;
        
        // Schedule Views
        case 'get_schedule':
            $response = $controller->getSchedule();
            break;
            
        case 'get_coverage':
            $response = $controller->getCoverageReport();
            break;
            
        case 'get_statistics':
            $response = $controller->getStatistics();
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