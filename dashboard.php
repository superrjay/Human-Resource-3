<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/helpers.php';

use HR3\Config\Auth;
use HR3\Config\Database;

Auth::requireAuth();
Auth::checkTimeout();

$pageTitle = "Dashboard";
$userRole = Auth::getUserRole();
$userId = Auth::getUserId();
$db = Database::getConnection();

// Get user's hours this week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$hoursStmt = $db->prepare("
    SELECT COALESCE(SUM(total_hours), 0) as total_hours
    FROM attendance_logs
    WHERE user_id = :user_id 
    AND DATE(clock_in) BETWEEN :week_start AND :week_end
    AND is_archived = 0
");
$hoursStmt->execute([':user_id' => $userId, ':week_start' => $weekStart, ':week_end' => $weekEnd]);
$hoursData = $hoursStmt->fetch(PDO::FETCH_ASSOC);
$hoursThisWeek = number_format((float)$hoursData['total_hours'], 1);

// Get previous week for comparison
$prevWeekStart = date('Y-m-d', strtotime('monday last week'));
$prevWeekEnd = date('Y-m-d', strtotime('sunday last week'));

$prevHoursStmt = $db->prepare("
    SELECT COALESCE(SUM(total_hours), 0) as total_hours
    FROM attendance_logs
    WHERE user_id = :user_id 
    AND DATE(clock_in) BETWEEN :week_start AND :week_end
    AND is_archived = 0
");
$prevHoursStmt->execute([':user_id' => $userId, ':week_start' => $prevWeekStart, ':week_end' => $prevWeekEnd]);
$prevHoursData = $prevHoursStmt->fetch(PDO::FETCH_ASSOC);
$prevWeekHours = (float)$prevHoursData['total_hours'];
$hoursDiff = (float)$hoursData['total_hours'] - $prevWeekHours;

// Get leave balance (Annual Leave)
$leaveStmt = $db->prepare("
    SELECT COALESCE(SUM(remaining_days), 0) as total_leave
    FROM leave_balances
    WHERE user_id = :user_id AND year = YEAR(CURDATE())
");
$leaveStmt->execute([':user_id' => $userId]);
$leaveData = $leaveStmt->fetch(PDO::FETCH_ASSOC);
$leaveBalance = (int)$leaveData['total_leave'];

// Get pending requests count - Fixed duplicate parameters
$pendingStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'Pending' AND is_archived = 0) +
        (SELECT COUNT(*) FROM claims WHERE user_id = ? AND status = 'Pending' AND is_archived = 0) as pending_count
");
$pendingStmt->execute([$userId, $userId]);
$pendingData = $pendingStmt->fetch(PDO::FETCH_ASSOC);
$pendingCount = (int)$pendingData['pending_count'];

// Get upcoming shifts (next 7 days)
$today = date('Y-m-d');
$next7Days = date('Y-m-d', strtotime('+7 days'));

$shiftsStmt = $db->prepare("
    SELECT COUNT(*) as shift_count
    FROM shift_assignments
    WHERE user_id = :user_id 
    AND assigned_date BETWEEN :today AND :next7days
    AND is_archived = 0
");
$shiftsStmt->execute([':user_id' => $userId, ':today' => $today, ':next7days' => $next7Days]);
$shiftsData = $shiftsStmt->fetch(PDO::FETCH_ASSOC);
$upcomingShifts = (int)$shiftsData['shift_count'];

// Get recent activity - Separate queries approach
$recentActivity = [];

// Get attendance activity
$attendanceStmt = $db->prepare("
    SELECT 'attendance' as type, 'Clocked in' as action, clock_in as activity_date 
    FROM attendance_logs 
    WHERE user_id = :user_id AND clock_in IS NOT NULL AND is_archived = 0
    ORDER BY clock_in DESC LIMIT 5
");
$attendanceStmt->execute([':user_id' => $userId]);
$recentActivity = array_merge($recentActivity, $attendanceStmt->fetchAll(PDO::FETCH_ASSOC));

// Get leave activity
$leaveActivityStmt = $db->prepare("
    SELECT 'leave' as type, 'Leave request submitted' as action, created_at as activity_date
    FROM leave_requests
    WHERE user_id = :user_id AND is_archived = 0
    ORDER BY created_at DESC LIMIT 5
");
$leaveActivityStmt->execute([':user_id' => $userId]);
$recentActivity = array_merge($recentActivity, $leaveActivityStmt->fetchAll(PDO::FETCH_ASSOC));

// Get claim activity
$claimActivityStmt = $db->prepare("
    SELECT 'claim' as type, 'Claim submitted' as action, created_at as activity_date
    FROM claims
    WHERE user_id = :user_id AND is_archived = 0
    ORDER BY created_at DESC LIMIT 5
");
$claimActivityStmt->execute([':user_id' => $userId]);
$recentActivity = array_merge($recentActivity, $claimActivityStmt->fetchAll(PDO::FETCH_ASSOC));

// Sort by date and limit to 10
usort($recentActivity, function($a, $b) {
    return strtotime($b['activity_date']) - strtotime($a['activity_date']);
});
$recentActivity = array_slice($recentActivity, 0, 10);

// Get today's shift
$todayShiftStmt = $db->prepare("
    SELECT s.shift_name, s.start_time, s.end_time, s.is_night_shift
    FROM shift_assignments sa
    JOIN shifts s ON sa.shift_id = s.shift_id
    WHERE sa.user_id = :user_id 
    AND sa.assigned_date = :today
    AND sa.is_archived = 0
    LIMIT 1
");
$todayShiftStmt->execute([':user_id' => $userId, ':today' => $today]);
$todayShift = $todayShiftStmt->fetch(PDO::FETCH_ASSOC);

// Check if user is clocked in today
$clockedInStmt = $db->prepare("
    SELECT attendance_id, clock_in, clock_out
    FROM attendance_logs
    WHERE user_id = :user_id 
    AND DATE(clock_in) = :today
    AND is_archived = 0
    ORDER BY clock_in DESC
    LIMIT 1
");
$clockedInStmt->execute([':user_id' => $userId, ':today' => $today]);
$todayAttendance = $clockedInStmt->fetch(PDO::FETCH_ASSOC);
$isClockedIn = $todayAttendance && !$todayAttendance['clock_out'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3 Workforce</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-left: 4px solid #0d6efd;
            cursor: pointer;
        }
        .stat-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-card.primary { border-left-color: #0d6efd; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .activity-item {
            transition: background-color 0.2s;
        }
        .activity-item:hover {
            background-color: #f8f9fa;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        <?php if ($isClockedIn): ?>
                            <span class="badge bg-success pulse ms-2">Clocked In</span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                        <?php if (Auth::hasAnyRole(['Employee'])): ?>
                        <a href="<?= get_module_path('time_attendance') ?>/index.php" 
                           class="btn btn-sm btn-<?= $isClockedIn ? 'danger' : 'primary' ?>">
                            <i class="fas fa-fingerprint me-1"></i>
                            <?= $isClockedIn ? 'Clock Out' : 'Clock In' ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($message = flash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= sanitize_output($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">Hours This Week</h6>
                                        <h3 class="mb-0"><?= $hoursThisWeek ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <small class="<?= $hoursDiff >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <i class="fas fa-arrow-<?= $hoursDiff >= 0 ? 'up' : 'down' ?> me-1"></i>
                                    <?= abs($hoursDiff) ?>h from last week
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">Leave Balance</h6>
                                        <h3 class="mb-0"><?= $leaveBalance ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-umbrella-beach fa-2x text-success"></i>
                                    </div>
                                </div>
                                <small class="text-muted">Days remaining</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">Pending Requests</h6>
                                        <h3 class="mb-0"><?= $pendingCount ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                                <small class="text-muted">Awaiting approval</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted mb-2">Upcoming Shifts</h6>
                                        <h3 class="mb-0"><?= $upcomingShifts ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-alt fa-2x text-info"></i>
                                    </div>
                                </div>
                                <small class="text-muted">Next 7 days</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if (Auth::hasAnyRole(['Admin', 'Manager', 'Employee'])): ?>
                    <!-- Recent Activity -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentActivity)): ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>No recent activity</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentActivity as $activity): 
                                            $timeAgo = '';
                                            $activityDate = strtotime($activity['activity_date']);
                                            $diff = time() - $activityDate;
                                            
                                            if ($diff < 3600) {
                                                $timeAgo = floor($diff / 60) . ' minutes ago';
                                            } elseif ($diff < 86400) {
                                                $timeAgo = floor($diff / 3600) . ' hours ago';
                                            } elseif ($diff < 604800) {
                                                $timeAgo = floor($diff / 86400) . ' days ago';
                                            } else {
                                                $timeAgo = date('M d, Y', $activityDate);
                                            }
                                            
                                            $iconClass = match($activity['type']) {
                                                'attendance' => 'fa-clock text-success',
                                                'leave' => 'fa-umbrella-beach text-info',
                                                'claim' => 'fa-receipt text-warning',
                                                default => 'fa-info-circle text-secondary'
                                            };
                                        ?>
                                            <div class="list-group-item activity-item d-flex align-items-center py-3">
                                                <div class="activity-icon bg-light me-3">
                                                    <i class="fas <?= $iconClass ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span><?= sanitize_output($activity['action']) ?></span>
                                                </div>
                                                <small class="text-muted"><?= $timeAgo ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (Auth::hasAnyRole(['Employee'])): ?>
                    <!-- Quick Actions & Today's Schedule -->
                    <div class="col-lg-4 mb-4">
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="<?= get_module_path('time_attendance') ?>/index.php" 
                                       class="btn btn-outline-primary btn-sm text-start">
                                        <i class="fas fa-fingerprint me-2"></i>Clock In/Out
                                    </a>
                                    <a href="<?= get_module_path('leave') ?>/index.php" 
                                       class="btn btn-outline-success btn-sm text-start">
                                        <i class="fas fa-umbrella-beach me-2"></i>Request Leave
                                    </a>
                                    <a href="<?= get_module_path('timesheet') ?>/create.php" 
                                       class="btn btn-outline-info btn-sm text-start">
                                        <i class="fas fa-file-alt me-2"></i>Submit Timesheet
                                    </a>
                                    <a href="<?= get_module_path('claims_reimbursement') ?>/index.php" 
                                       class="btn btn-outline-warning btn-sm text-start">
                                        <i class="fas fa-receipt me-2"></i>File Claim
                                    </a>
                                    <a href="<?= get_module_path('shift_schedule') ?>/index.php" 
                                       class="btn btn-outline-secondary btn-sm text-start">
                                        <i class="fas fa-calendar-alt me-2"></i>View Schedule
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-day me-2"></i>Today's Schedule
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($todayShift): ?>
                                    <div class="alert alert-<?= $todayShift['is_night_shift'] ? 'dark' : 'info' ?> mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?= sanitize_output($todayShift['shift_name']) ?></strong>
                                        <?php if ($todayShift['is_night_shift']): ?>
                                            <span class="badge bg-dark ms-2">Night Shift</span>
                                        <?php endif; ?>
                                        <br>
                                        <small>
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('g:i A', strtotime($todayShift['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($todayShift['end_time'])) ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">
                                        <i class="fas fa-calendar-times me-2"></i>
                                        No shift scheduled for today
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($todayAttendance): ?>
                                    <div class="mt-3 p-2 bg-light rounded">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>Clocked in at: 
                                            <strong><?= date('g:i A', strtotime($todayAttendance['clock_in'])) ?></strong>
                                        </small>
                                        <?php if ($todayAttendance['clock_out']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>Clocked out at: 
                                                <strong><?= date('g:i A', strtotime($todayAttendance['clock_out'])) ?></strong>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>