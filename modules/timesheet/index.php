<?php
/**
 * Timesheet Module - Main Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Timesheet.php';

use HR3\Config\Auth;
use HR3\Models\Timesheet;

Auth::requireAuth();

$timesheetModel = new Timesheet();
$userId = Auth::getUserId();

// Get current year timesheets
$startDate = date('Y-01-01');
$endDate = date('Y-12-31');
$timesheets = $timesheetModel->getByUserAndDateRange($userId, $startDate, $endDate);
$stats = $timesheetModel->getStatistics($userId, $startDate, $endDate);

$pageTitle = "My Timesheets";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Timesheets</h1>
                    <div class="btn-toolbar">
                        <a href="<?= base_url() ?>/modules/timesheet/create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>New Timesheet
                        </a>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h3 class="text-primary"><?= $stats['total_timesheets'] ?? 0 ?></h3>
                                <small class="text-muted">Total Submitted</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h3 class="text-success"><?= $stats['approved_count'] ?? 0 ?></h3>
                                <small class="text-muted">Approved</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h3 class="text-warning"><?= $stats['pending_count'] ?? 0 ?></h3>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h3 class="text-info"><?= number_format((float)$stats['total_hours'] ?? 0, 1) ?></h3>
                                <small class="text-muted">Total Hours</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timesheets Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Timesheet History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Week Period</th>
                                        <th>Total Hours</th>
                                        <th>Entries</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <!--<th>Actions</th>-->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($timesheets)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No timesheets found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($timesheets as $ts): ?>
                                            <tr>
                                                <td>
                                                    <?= date('M d', strtotime($ts['week_start'])) ?> - 
                                                    <?= date('M d, Y', strtotime($ts['week_end'])) ?>
                                                </td>
                                                <td><?= number_format((float)$ts['total_hours'], 2) ?> hrs</td>
                                                <td><?= $ts['entry_count'] ?> entries</td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'Pending' => 'warning',
                                                        'Approved' => 'success',
                                                        'Rejected' => 'danger'
                                                    ];
                                                    $class = $statusClass[$ts['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $class ?>"><?= $ts['status'] ?></span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($ts['created_at'])) ?></td>
                                                <!--
                                                <td>
                                                    <a href="<?= base_url() ?>/modules/timesheet/view.php?id=<?= $ts['timesheet_id'] ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (Auth::hasAnyRole(['Admin', 'Manager'])): ?>
                                                        <?php if ($ts['status'] === 'Pending'): ?>
                                                            <a href="<?= base_url() ?>/modules/timesheet/edit.php?id=<?= $ts['timesheet_id'] ?>" 
                                                            class="btn btn-sm btn-outline-secondary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                -->
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>