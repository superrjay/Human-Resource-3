<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Leave.php';
require_once __DIR__ . '/../../controllers/LeaveController.php';
header('Content-Type: application/json');
use HR3\Controllers\LeaveController;
try {
    $controller = new LeaveController();
    echo json_encode($controller->create());
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}