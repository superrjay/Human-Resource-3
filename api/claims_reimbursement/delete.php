<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ClaimsReimbursement.php';
require_once __DIR__ . '/../../controllers/ClaimsReimbursementController.php';

use HR3\Controllers\ClaimsReimbursementController;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$controller = new ClaimsReimbursementController();
$result = $controller->delete();

echo json_encode($result);