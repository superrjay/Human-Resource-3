<?php
/**
 * Attendance Controller
 * 
 * @package HR3
 * @subpackage Controllers
 */

declare(strict_types=1);

namespace HR3\Controllers;

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/Attendance.php';

use HR3\Models\Attendance;
use HR3\Config\Auth;

class AttendanceController
{
    private Attendance $model;

    public function __construct()
    {
        // Set timezone
        date_default_timezone_set('Asia/Manila');
        $this->model = new Attendance();
    }

    /**
     * Clock In
     */
    public function clockIn(): array
    {
        Auth::requireAuth();
        
        $userId = Auth::getUserId();
        
        // Check if already clocked in today
        $existing = $this->model->getTodayActiveClockIn($userId);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'You are already clocked in.'
            ];
        }
        
        $location = $_POST['location'] ?? null;
        $deviceType = $_POST['device_type'] ?? 'Web';
        
        // Use current server time (which is now in Asia/Manila timezone)
        $clockInTime = date('Y-m-d H:i:s');
        
        $attendanceId = $this->model->clockIn($userId, $location, $deviceType, $clockInTime);
        
        if ($attendanceId) {
            return [
                'success' => true,
                'message' => 'Clocked in successfully!',
                'attendance_id' => $attendanceId,
                'clock_in_time' => $clockInTime
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to clock in.'
        ];
    }

    /**
     * Clock Out
     */
    public function clockOut(): array
    {
        Auth::requireAuth();
        
        $userId = Auth::getUserId();
        $activeClockIn = $this->model->getTodayActiveClockIn($userId);
        
        if (!$activeClockIn) {
            return [
                'success' => false,
                'message' => 'No active clock-in found.'
            ];
        }
        
        $success = $this->model->clockOut((int)$activeClockIn['attendance_id']);
        
        if ($success) {
            return [
                'success' => true,
                'message' => 'Clocked out successfully!'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to clock out.'
        ];
    }

    /**
     * Get User Attendance History
     */
    public function getHistory(): array
    {
        Auth::requireAuth();
        
        $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        // Permission check
        if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager'])) {
            return [
                'success' => false,
                'message' => 'Access denied.'
            ];
        }
        
        $records = $this->model->getByUserAndDateRange($userId, $startDate, $endDate);
        $stats = $this->model->getStatistics($userId, $startDate, $endDate);
        
        return [
            'success' => true,
            'data' => [
                'records' => $records,
                'statistics' => $stats
            ]
        ];
    }

    /**
     * Get All Attendance (Admin/Manager)
     */
    public function getAllAttendance(): array
    {
        Auth::requireRole(['Admin', 'Manager', 'Finance']);
        
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'status' => $_GET['status'] ?? null,
            'limit' => $_GET['limit'] ?? 100
        ];
        
        $records = $this->model->getAllWithFilters($filters);
        
        return [
            'success' => true,
            'data' => $records
        ];
    }

    /**
     * Update Attendance Record (Admin/Manager)
     */
    public function updateRecord(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        
        if (!$attendanceId) {
            return ['success' => false, 'message' => 'Invalid attendance ID.'];
        }
        
        $data = [];
        
        if (isset($_POST['clock_in'])) {
            $data['clock_in'] = $_POST['clock_in'];
        }
        
        if (isset($_POST['clock_out'])) {
            $data['clock_out'] = $_POST['clock_out'];
        }
        
        if (isset($_POST['status'])) {
            $data['status'] = $_POST['status'];
        }
        
        if (isset($_POST['total_hours'])) {
            $data['total_hours'] = (float)$_POST['total_hours'];
        }
        
        $success = $this->model->update($attendanceId, $data);
        
        return [
            'success' => $success,
            'message' => $success ? 'Record updated successfully!' : 'Failed to update record.'
        ];
    }

    /**
     * Archive Record
     */
    public function archiveRecord(): array
    {
        Auth::requireRole(['Admin']);
        
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        
        if (!$attendanceId) {
            return ['success' => false, 'message' => 'Invalid attendance ID.'];
        }
        
        $success = $this->model->archive($attendanceId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Record archived successfully!' : 'Failed to archive record.'
        ];
    }

    /**
     * Restore Record
     */
    public function restoreRecord(): array
    {
        Auth::requireRole(['Admin']);
        
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        
        if (!$attendanceId) {
            return ['success' => false, 'message' => 'Invalid attendance ID.'];
        }
        
        $success = $this->model->restore($attendanceId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Record restored successfully!' : 'Failed to restore record.'
        ];
    }

    /**
     * Delete Record (Hard delete)
     */
    public function deleteRecord(): array
    {
        Auth::requireRole(['Admin']);
        
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        
        if (!$attendanceId) {
            return ['success' => false, 'message' => 'Invalid attendance ID.'];
        }
        
        $success = $this->model->delete($attendanceId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Record deleted permanently!' : 'Failed to delete record.'
        ];
    }
}