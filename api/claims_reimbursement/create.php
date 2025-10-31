<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ClaimsReimbursement.php';
require_once __DIR__ . '/../../controllers/ClaimsReimbursementController.php';

use HR3\Controllers\ClaimsReimbursementController;

header('Content-Type: application/json');

$controller = new ClaimsReimbursementController();
$result = $controller->create();

echo json_encode($result);