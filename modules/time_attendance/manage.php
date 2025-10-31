<?php
/**
 * Manage Attendance (Admin/Manager View)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Attendance.php';

use HR3\Config\Auth;
use HR3\Models\Attendance;

Auth::requireRole(['Admin', 'Manager', 'Finance']);

$attendanceModel = new Attendance();

// Get filters
$filters = [
    'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
    'end_date' => $_GET['end_date'] ?? date('Y-m-t'),
    'status' => $_GET['status'] ?? null,
    'limit' => 100
];

$records = $attendanceModel->getAllWithFilters($filters);
$pageTitle = "Manage Attendance";
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
                    <h1 class="h2"><i class="fas fa-users-cog me-2"></i>Manage Attendance</h1>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?= $filters['start_date'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?= $filters['end_date'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="Present" <?= $filters['status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Late" <?= $filters['status'] === 'Late' ? 'selected' : '' ?>>Late</option>
                                    <option value="Absent" <?= $filters['status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="On Leave" <?= $filters['status'] === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Attendance Records</h5>
                        <input type="text" class="form-control w-25" placeholder="Search..." onkeyup="searchTable(this.value)">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <!--
                                        <?php if (Auth::hasRole('Admin')): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                        -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($records)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?= sanitize_output($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                                <td><?= sanitize_output($record['department_name']) ?></td>
                                                <td><?= date('M d, Y', strtotime($record['clock_in'])) ?></td>
                                                <td><?= date('h:i A', strtotime($record['clock_in'])) ?></td>
                                                <td>
                                                    <?= $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : 
                                                        '<span class="badge bg-warning">Active</span>' ?>
                                                </td>
                                                <td><?= $record['total_hours'] ? number_format((float)$record['total_hours'], 2) : '-' ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'Present' => 'success',
                                                        'Late' => 'warning',
                                                        'Absent' => 'danger',
                                                        'On Leave' => 'info'
                                                    ];
                                                    $class = $statusClass[$record['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $class ?>"><?= $record['status'] ?></span>
                                                </td>
                                                <?php if (Auth::hasRole('Admin')): ?>
                                                <td>
                                                    <!--
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="editRecord(<?= $record['attendance_id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="archiveRecord(<?= $record['attendance_id'] ?>)">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                    -->
                                                </td>
                                                <?php endif; ?>
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Attendance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" name="attendance_id" id="edit_attendance_id">
                        <div class="mb-3">
                            <label class="form-label">Clock In</label>
                            <input type="datetime-local" class="form-control" name="clock_in" id="edit_clock_in" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clock Out</label>
                            <input type="datetime-local" class="form-control" name="clock_out" id="edit_clock_out">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function searchTable(value) {
            const filter = value.toUpperCase();
            const table = document.getElementById('attendanceTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }

        function editRecord(id) {
            // Fetch record details and populate modal
            fetch(`<?= base_url() ?>/api/attendance/?action=history&attendance_id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.records[0]) {
                        const record = data.data.records[0];
                        document.getElementById('edit_attendance_id').value = id;
                        document.getElementById('edit_clock_in').value = record.clock_in.replace(' ', 'T');
                        document.getElementById('edit_clock_out').value = record.clock_out ? record.clock_out.replace(' ', 'T') : '';
                        document.getElementById('edit_status').value = record.status;
                        
                        new bootstrap.Modal(document.getElementById('editModal')).show();
                    }
                });
        }

        function saveEdit() {
            const formData = new FormData(document.getElementById('editForm'));
            
            fetch('<?= base_url() ?>/api/attendance/?action=update', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }

        function archiveRecord(id) {
            if (confirm('Archive this record?')) {
                const formData = new FormData();
                formData.append('attendance_id', id);
                
                fetch('<?= base_url() ?>/api/attendance/?action=archive', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                });
            }
        }
    </script>
</body>
</html>