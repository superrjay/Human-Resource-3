<?php
/**
 * Timesheet API Endpoint
 * 
 * @package HR3
 * @subpackage API
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Timesheet.php';
require_once __DIR__ . '/../../controllers/TimesheetController.php';

use HR3\Controllers\TimesheetController;
use HR3\Config\Auth;

// Handle CORS if needed
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

$controller = new TimesheetController();
$action = $_GET['action'] ?? '';

try {
    $response = match($action) {
        'create' => $controller->create(),
        'get' => $controller->get(),
        'list' => $controller->getUserTimesheets(),
        'update' => $controller->update(),
        'pending' => $controller->getPendingTimesheets(),
        'approve' => $controller->approve(),
        'reject' => $controller->reject(),
        'archive' => $controller->archive(),
        'restore' => $controller->restore(),
        'delete' => $controller->delete(),
        default => ['success' => false, 'message' => 'Invalid action']
    };
    
    echo json_encode($response);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred.'
    ]);
}