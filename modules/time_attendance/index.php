<?php
/**
 * Time & Attendance Module - Main Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Attendance.php';

use HR3\Config\Auth;
use HR3\Models\Attendance;

Auth::requireAuth();

// Set timezone to match your location (Philippines)
date_default_timezone_set('Asia/Manila');

$attendanceModel = new Attendance();
$userId = Auth::getUserId();

// Get active clock-in
$activeClockIn = $attendanceModel->getTodayActiveClockIn($userId);

// Get this month's attendance
$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$monthlyRecords = $attendanceModel->getByUserAndDateRange($userId, $startDate, $endDate);
$stats = $attendanceModel->getStatistics($userId, $startDate, $endDate);

$pageTitle = "Time & Attendance";
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
                    <h1 class="h2"><i class="fas fa-clock me-2"></i>Time & Attendance</h1>
                    <div class="btn-toolbar">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Clock In/Out Card -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body text-center p-5">
                                <div id="current-time" class="display-4 mb-2 text-primary"></div>
                                <div id="current-date" class="h5 text-muted mb-4"></div>
                                
                                <?php if ($activeClockIn): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Clocked in at:</strong>
                                        <div class="mt-1">
                                            <span id="clock-in-display" class="fs-5">
                                                <?= date('h:i:s A', strtotime($activeClockIn['clock_in'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <small class="text-muted">Time Elapsed: </small>
                                        <strong id="elapsed-time" class="text-primary fs-4">00:00:00</strong>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-lg" onclick="clockOut()">
                                        <i class="fas fa-sign-out-alt me-2"></i>Clock Out
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-success btn-lg" onclick="clockIn()">
                                        <i class="fas fa-sign-in-alt me-2"></i>Clock In
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-4">This Month's Summary</h5>
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <h3 class="text-primary"><?= $stats['total_days'] ?? 0 ?></h3>
                                        <small class="text-muted">Days Worked</small>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <h3 class="text-success"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></h3>
                                        <small class="text-muted">Total Hours</small>
                                    </div>
                                    <div class="col-6">
                                        <h3 class="text-warning"><?= $stats['late_days'] ?? 0 ?></h3>
                                        <small class="text-muted">Late Arrivals</small>
                                    </div>
                                    <div class="col-6">
                                        <h3 class="text-danger"><?= $stats['absent_days'] ?? 0 ?></h3>
                                        <small class="text-muted">Absent Days</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Attendance History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Total Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($monthlyRecords)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($monthlyRecords as $record): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($record['clock_in'])) ?></td>
                                                <td><?= date('h:i A', strtotime($record['clock_in'])) ?></td>
                                                <td>
                                                    <?= $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '<span class="badge bg-warning">Active</span>' ?>
                                                </td>
                                                <td><?= $record['total_hours'] ? number_format((float)$record['total_hours'], 2) . ' hrs' : '-' ?></td>
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
    <script>
        // Clock-in time from PHP (will be set after clocking in)
        let clockInTime = <?= $activeClockIn ? "new Date('" . date('Y-m-d H:i:s', strtotime($activeClockIn['clock_in'])) . "')" : 'null' ?>;
        
        // Update current time and elapsed time
        function updateTime() {
            const now = new Date();
            
            // Format time with seconds
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            
            const timeString = String(displayHours).padStart(2, '0') + ':' +
                             String(minutes).padStart(2, '0') + ':' +
                             String(seconds).padStart(2, '0') + ' ' + ampm;
            
            document.getElementById('current-time').textContent = timeString;
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
            });
            
            // Update elapsed time if clocked in
            if (clockInTime) {
                const elapsed = Math.floor((now - clockInTime) / 1000); // seconds
                
                const hrs = Math.floor(elapsed / 3600);
                const mins = Math.floor((elapsed % 3600) / 60);
                const secs = elapsed % 60;
                
                const elapsedElement = document.getElementById('elapsed-time');
                if (elapsedElement) {
                    elapsedElement.textContent = 
                        String(hrs).padStart(2, '0') + ':' +
                        String(mins).padStart(2, '0') + ':' +
                        String(secs).padStart(2, '0');
                }
            }
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        // Clock In
        function clockIn() {
            if (confirm('Clock in now?')) {
                // Get current time
                const now = new Date();
                const localTime = now.toLocaleString('en-US', { 
                    timeZone: 'Asia/Manila',
                    hour12: false,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                const formData = new FormData();
                formData.append('device_type', 'Web');
                formData.append('clock_in_time', localTime);
                
                fetch('<?= base_url() ?>/api/attendance/index.php?action=clock-in', {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error clocking in. Please try again.');
                });
            }
        }

        // Clock Out
        function clockOut() {
            if (confirm('Clock out now?')) {
                fetch('<?= base_url() ?>/api/attendance/index.php?action=clock-out', {
                    method: 'POST'
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error clocking out. Please try again.');
                });
            }
        }
    </script>
</body>
</html>