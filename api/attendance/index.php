<?php
/**
 * Attendance API Endpoint
 * File: /api/attendance/index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../controllers/AttendanceController.php';

use HR3\Controllers\AttendanceController;

header('Content-Type: application/json');

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$controller = new AttendanceController();
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($action) {
        case 'clock-in':
            if ($method === 'POST') {
                $response = $controller->clockIn();
            }
            break;
            
        case 'clock-out':
            if ($method === 'POST') {
                $response = $controller->clockOut();
            }
            break;
            
        case 'history':
            if ($method === 'GET') {
                $response = $controller->getHistory();
            }
            break;
            
        case 'all':
            if ($method === 'GET') {
                $response = $controller->getAllAttendance();
            }
            break;
            
        case 'update':
            if ($method === 'POST') {
                $response = $controller->updateRecord();
            }
            break;
            
        case 'archive':
            if ($method === 'POST') {
                $response = $controller->archiveRecord();
            }
            break;
            
        case 'restore':
            if ($method === 'POST') {
                $response = $controller->restoreRecord();
            }
            break;
            
        case 'delete':
            if ($method === 'DELETE' || $method === 'POST') {
                $response = $controller->deleteRecord();
            }
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => 'Invalid action'
            ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ];
}

echo json_encode($response);