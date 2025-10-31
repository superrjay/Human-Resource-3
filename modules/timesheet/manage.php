<?php
/**
 * Manage Timesheets (Admin/Manager View)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Timesheet.php';

use HR3\Config\Auth;
use HR3\Models\Timesheet;

Auth::requireRole(['Admin', 'Manager']);

$timesheetModel = new Timesheet();
$managerId = Auth::hasRole('Manager') ? Auth::getUserId() : null;
$pendingTimesheets = $timesheetModel->getPendingTimesheets($managerId);

$pageTitle = "Manage Timesheets";
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks me-2"></i>Manage Timesheets</h1>
                    <div class="btn-toolbar">
                        <span class="badge bg-warning fs-6"><?= count($pendingTimesheets) ?> Pending</span>
                    </div>
                </div>

                <?php if (empty($pendingTimesheets)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No pending timesheets for approval.
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Pending Approvals</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Week Period</th>
                                            <th>Total Hours</th>
                                            <th>Entries</th>
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingTimesheets as $ts): ?>
                                            <tr>
                                                <td><?= sanitize_output($ts['first_name'] . ' ' . $ts['last_name']) ?></td>
                                                <td><?= sanitize_output($ts['department_name']) ?></td>
                                                <td>
                                                    <?= date('M d', strtotime($ts['week_start'])) ?> - 
                                                    <?= date('M d, Y', strtotime($ts['week_end'])) ?>
                                                </td>
                                                <td><?= number_format($ts['total_hours'], 2) ?> hrs</td>
                                                <td><?= $ts['entry_count'] ?> entries</td>
                                                <td><?= date('M d, Y', strtotime($ts['created_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewDetails(<?= $ts['timesheet_id'] ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="approveTimesheet(<?= $ts['timesheet_id'] ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="rejectTimesheet(<?= $ts['timesheet_id'] ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Timesheet Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(timesheetId) {
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            const modalBody = document.getElementById('modalBody');
            
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
            modal.show();
            
            fetch(`<?= base_url() ?>/api/timesheet/?action=get&timesheet_id=${timesheetId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const ts = data.data;
                        let html = `
                            <div class="mb-3">
                                <strong>Week:</strong> ${new Date(ts.week_start).toLocaleDateString()} - ${new Date(ts.week_end).toLocaleDateString()}<br>
                                <strong>Total Hours:</strong> ${parseFloat(ts.total_hours).toFixed(2)} hrs<br>
                                <strong>Status:</strong> <span class="badge bg-warning">${ts.status}</span>
                            </div>
                            <h6>Time Entries:</h6>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        
                        ts.entries.forEach(entry => {
                            html += `
                                <tr>
                                    <td>${new Date(entry.work_date).toLocaleDateString()}</td>
                                    <td>${entry.project_name}</td>
                                    <td>${entry.task_description}</td>
                                    <td>${parseFloat(entry.hours_worked).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                        
                        html += '</tbody></table>';
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                    }
                })
                .catch(err => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
                });
        }

        function approveTimesheet(timesheetId) {
            if (!confirm('Approve this timesheet?')) return;
            
            const formData = new FormData();
            formData.append('timesheet_id', timesheetId);
            
            fetch('<?= base_url() ?>/api/timesheet/?action=approve', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => alert('Error: ' + err));
        }

        function rejectTimesheet(timesheetId) {
            if (!confirm('Reject this timesheet?')) return;
            
            const formData = new FormData();
            formData.append('timesheet_id', timesheetId);
            
            fetch('<?= base_url() ?>/api/timesheet/?action=reject', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>