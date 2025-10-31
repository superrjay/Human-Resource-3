<?php
/**
 * Leave Controller - Fixed Version
 * 
 * @package HR3
 * @subpackage Controllers
 */

declare(strict_types=1);

namespace HR3\Controllers;

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/Leave.php';

use HR3\Models\Leave;
use HR3\Config\Auth;

class LeaveController
{
    private Leave $model;

    public function __construct()
    {
        $this->model = new Leave();
    }

    /**
     * Create leave request
     */
    public function create(): array
    {
        Auth::requireAuth();
        
        $userId = Auth::getUserId();
        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        
        // Validation
        if (!$leaveTypeId || !$startDate || !$endDate) {
            return ['success' => false, 'message' => 'All required fields must be filled.'];
        }
        
        if (strtotime($startDate) > strtotime($endDate)) {
            return ['success' => false, 'message' => 'End date cannot be before start date.'];
        }
        
        if (strtotime($startDate) < strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'Cannot request leave for past dates.'];
        }
        
        // Check overlap
        if ($this->model->hasOverlap($userId, $startDate, $endDate)) {
            return ['success' => false, 'message' => 'You have overlapping leave request.'];
        }
        
        // Calculate days
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $totalDays = $start->diff($end)->days + 1;
        
        // Check balance
        $balance = $this->model->getBalance($userId, $leaveTypeId);
        if ($balance && $balance['remaining_days'] < $totalDays) {
            return ['success' => false, 'message' => 'Insufficient leave balance. Available: ' . $balance['remaining_days'] . ' days'];
        }
        
        $data = [
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => $reason,
            'status' => 'Pending'
        ];
        
        $leaveId = $this->model->create($data);
        
        return [
            'success' => (bool)$leaveId,
            'message' => $leaveId ? 'Leave request submitted successfully!' : 'Failed to submit leave request.',
            'leave_id' => $leaveId
        ];
    }

    /**
     * Get user's leave requests
     */
    public function getUserLeaves(): array
    {
        Auth::requireAuth();
        
        $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
        $status = $_GET['status'] ?? null;
        
        if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager'])) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        $leaves = $this->model->getByUser($userId, $status);
        $stats = $this->model->getStatistics($userId, (int)date('Y'));
        $balances = $this->model->getUserBalances($userId);
        
        return [
            'success' => true, 
            'data' => [
                'leaves' => $leaves, 
                'statistics' => $stats,
                'balances' => $balances
            ]
        ];
    }

    /**
     * Get pending leaves for approval
     */
    public function getPendingLeaves(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $managerId = Auth::hasRole('Manager') ? Auth::getUserId() : null;
        $leaves = $this->model->getPendingForManager($managerId);
        
        return ['success' => true, 'data' => $leaves];
    }

    /**
     * Approve leave
     */
    public function approve(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $leaveId = (int)($input['leave_id'] ?? $_POST['leave_id'] ?? 0);
        
        if (!$leaveId) return ['success' => false, 'message' => 'Invalid leave ID.'];
        
        $success = $this->model->approve($leaveId, Auth::getUserId());
        return ['success' => $success, 'message' => $success ? 'Leave approved successfully!' : 'Failed to approve leave.'];
    }

    /**
     * Reject leave
     */
    public function reject(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $leaveId = (int)($input['leave_id'] ?? $_POST['leave_id'] ?? 0);
        
        if (!$leaveId) return ['success' => false, 'message' => 'Invalid leave ID.'];
        
        $success = $this->model->reject($leaveId, Auth::getUserId());
        return ['success' => $success, 'message' => $success ? 'Leave rejected.' : 'Failed to reject leave.'];
    }

    /**
     * Cancel leave
     */
    public function cancel(): array
    {
        Auth::requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $leaveId = (int)($input['leave_id'] ?? $_POST['leave_id'] ?? 0);
        
        $leave = $this->model->find($leaveId);
        
        if (!$leave) {
            return ['success' => false, 'message' => 'Leave request not found.'];
        }
        
        if ($leave['user_id'] != Auth::getUserId() && !Auth::hasRole('Admin')) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        if (!in_array($leave['status'], ['Pending', 'Approved'])) {
            return ['success' => false, 'message' => 'Cannot cancel this leave request.'];
        }
        
        $success = $this->model->cancel($leaveId);
        return ['success' => $success, 'message' => $success ? 'Leave cancelled successfully.' : 'Failed to cancel leave.'];
    }

    /**
     * Get leave types
     */
    public function getLeaveTypes(): array
    {
        Auth::requireAuth();
        return ['success' => true, 'data' => $this->model->getLeaveTypes()];
    }

    /**
     * Get balance
     */
    public function getBalance(): array
    {
        Auth::requireAuth();
        
        $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
        $leaveTypeId = (int)($_GET['leave_type_id'] ?? 0);
        
        if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager'])) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        $balance = $this->model->getBalance($userId, $leaveTypeId);
        return ['success' => true, 'data' => $balance];
    }

    /**
     * Archive
     */
    public function archive(): array
    {
        Auth::requireRole(['Admin']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $leaveId = (int)($input['leave_id'] ?? $_POST['leave_id'] ?? 0);
        
        $success = $this->model->archive($leaveId);
        return ['success' => $success, 'message' => $success ? 'Leave archived!' : 'Failed to archive.'];
    }

    /**
     * Restore
     */
    public function restore(): array
    {
        Auth::requireRole(['Admin']);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $leaveId = (int)($input['leave_id'] ?? $_POST['leave_id'] ?? 0);
        
        $success = $this->model->restore($leaveId);
        return ['success' => $success, 'message' => $success ? 'Leave restored!' : 'Failed to restore.'];
    }
}