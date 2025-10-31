<?php
declare(strict_types=1);

namespace HR3\Controllers;

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/ShiftSchedule.php';

use HR3\Models\ShiftSchedule;
use HR3\Config\Auth;

class ShiftScheduleController
{
    private ShiftSchedule $model;

    public function __construct()
    {
        $this->model = new ShiftSchedule();
    }

    public function createShift(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $shiftName = trim($_POST['shift_name'] ?? '');
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $isNightShift = (int)($_POST['is_night_shift'] ?? 0);
        
        if (empty($shiftName) || empty($startTime) || empty($endTime)) {
            return ['success' => false, 'message' => 'All fields required.'];
        }
        
        $data = [
            'shift_name' => $shiftName,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_night_shift' => $isNightShift,
            'created_by' => Auth::getUserId()
        ];
        
        $shiftId = $this->model->create($data);
        
        return [
            'success' => (bool)$shiftId,
            'message' => $shiftId ? 'Shift created successfully!' : 'Failed to create shift.',
            'shift_id' => $shiftId
        ];
    }

    public function getAllShifts(): array
    {
        Auth::requireAuth();
        
        $shifts = $this->model->getAllActive();
        return ['success' => true, 'data' => $shifts];
    }

    public function assignShift(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $userIds = json_decode($_POST['user_ids'] ?? '[]', true);
        $dates = json_decode($_POST['dates'] ?? '[]', true);
        
        if (!$shiftId || empty($userIds) || empty($dates)) {
            return ['success' => false, 'message' => 'Missing required data.'];
        }
        
        $results = $this->model->bulkAssign($shiftId, $userIds, $dates, Auth::getUserId());
        
        return [
            'success' => $results['success'] > 0,
            'message' => "Assigned {$results['success']} shifts. Failed: {$results['failed']}",
            'data' => $results
        ];
    }

    public function getSchedule(): array
    {
        Auth::requireAuth();
        
        $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        
        // Permission check
        if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager'])) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        $schedule = $this->model->getUserSchedule($userId, $startDate, $endDate);
        
        return ['success' => true, 'data' => $schedule];
    }

    public function deleteAssignment(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        if (!$assignmentId) return ['success' => false, 'message' => 'Invalid ID.'];
        
        $success = $this->model->deleteAssignment($assignmentId);
        return ['success' => $success, 'message' => $success ? 'Deleted!' : 'Failed.'];
    }

    public function archiveShift(): array
    {
        Auth::requireRole(['Admin']);
        
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $success = $this->model->archive($shiftId);
        return ['success' => $success, 'message' => $success ? 'Archived!' : 'Failed.'];
    }

    public function getCoverageReport(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $date = $_GET['date'] ?? date('Y-m-d');
        $coverage = $this->model->getCoverageReport($date);
        
        return ['success' => true, 'data' => $coverage];
    }
}