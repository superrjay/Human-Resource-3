<?php
/**
 * Timesheet Controller
 * 
 * @package HR3
 * @subpackage Controllers
 */

declare(strict_types=1);

namespace HR3\Controllers;

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/Timesheet.php';

use HR3\Models\Timesheet;
use HR3\Config\Auth;

class TimesheetController
{
    private Timesheet $model;

    public function __construct()
    {
        $this->model = new Timesheet();
    }

    /**
     * Create timesheet
     */
    public function create(): array
    {
        Auth::requireAuth();
        
        $userId = Auth::getUserId();
        $weekStart = $_POST['week_start'] ?? '';
        $weekEnd = $_POST['week_end'] ?? '';
        $entries = json_decode($_POST['entries'] ?? '[]', true);
        
        // Validation
        if (empty($weekStart) || empty($weekEnd) || empty($entries)) {
            return ['success' => false, 'message' => 'Missing required fields.'];
        }
        
        // Calculate total hours
        $totalHours = array_reduce($entries, fn($sum, $e) => $sum + ($e['hours_worked'] ?? 0), 0);
        
        $timesheetData = [
            'user_id' => $userId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'total_hours' => $totalHours,
            'status' => 'Pending'
        ];
        
        $timesheetId = $this->model->createWithEntries($timesheetData, $entries);
        
        if ($timesheetId) {
            return [
                'success' => true,
                'message' => 'Timesheet submitted successfully!',
                'timesheet_id' => $timesheetId
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to create timesheet.'];
    }

    /**
     * Get timesheet
     */
    public function get(): array
    {
        Auth::requireAuth();
        
        $timesheetId = (int)($_GET['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $timesheet = $this->model->getWithEntries($timesheetId);
        
        if (!$timesheet) {
            return ['success' => false, 'message' => 'Timesheet not found.'];
        }
        
        // Permission check
        if ($timesheet['user_id'] !== Auth::getUserId() && 
            !Auth::hasAnyRole(['Admin', 'Manager', 'Finance'])) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        return ['success' => true, 'data' => $timesheet];
    }

    /**
     * Get user timesheets
     */
    public function getUserTimesheets(): array
    {
        Auth::requireAuth();
        
        $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
        $startDate = $_GET['start_date'] ?? date('Y-01-01');
        $endDate = $_GET['end_date'] ?? date('Y-12-31');
        
        // Permission check
        if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager', 'Finance'])) {
            return ['success' => false, 'message' => 'Access denied.'];
        }
        
        $timesheets = $this->model->getByUserAndDateRange($userId, $startDate, $endDate);
        $stats = $this->model->getStatistics($userId, $startDate, $endDate);
        
        return [
            'success' => true,
            'data' => [
                'timesheets' => $timesheets,
                'statistics' => $stats
            ]
        ];
    }

    /**
     * Update timesheet
     */
    public function update(): array
    {
        Auth::requireAuth();
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $timesheet = $this->model->find($timesheetId);
        
        if (!$timesheet) {
            return ['success' => false, 'message' => 'Timesheet not found.'];
        }
        
        // Can only edit own pending timesheets
        if ($timesheet['user_id'] !== Auth::getUserId() || $timesheet['status'] !== 'Pending') {
            return ['success' => false, 'message' => 'Cannot edit this timesheet.'];
        }
        
        $entries = json_decode($_POST['entries'] ?? '[]', true);
        
        if (empty($entries)) {
            return ['success' => false, 'message' => 'No entries provided.'];
        }
        
        try {
            $this->model->db->beginTransaction();
            
            // Delete existing entries
            $this->model->db->prepare("DELETE FROM timesheet_entries WHERE timesheet_id = ?")->execute([$timesheetId]);
            
            // Add new entries
            foreach ($entries as $entry) {
                $entry['timesheet_id'] = $timesheetId;
                $this->model->addEntry($entry);
            }
            
            // Update total hours
            $totalHours = $this->model->calculateTotalHours($timesheetId);
            $this->model->update($timesheetId, ['total_hours' => $totalHours]);
            
            $this->model->db->commit();
            
            return ['success' => true, 'message' => 'Timesheet updated successfully!'];
            
        } catch (\Exception $e) {
            $this->model->db->rollBack();
            return ['success' => false, 'message' => 'Failed to update timesheet.'];
        }
    }

    /**
     * Get pending timesheets (Manager/Admin)
     */
    public function getPendingTimesheets(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $managerId = Auth::hasRole('Manager') ? Auth::getUserId() : null;
        $timesheets = $this->model->getPendingTimesheets($managerId);
        
        return ['success' => true, 'data' => $timesheets];
    }

    /**
     * Approve timesheet
     */
    public function approve(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $success = $this->model->approve($timesheetId, Auth::getUserId());
        
        return [
            'success' => $success,
            'message' => $success ? 'Timesheet approved!' : 'Failed to approve.'
        ];
    }

    /**
     * Reject timesheet
     */
    public function reject(): array
    {
        Auth::requireRole(['Admin', 'Manager']);
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $success = $this->model->reject($timesheetId, Auth::getUserId());
        
        return [
            'success' => $success,
            'message' => $success ? 'Timesheet rejected.' : 'Failed to reject.'
        ];
    }

    /**
     * Archive timesheet
     */
    public function archive(): array
    {
        Auth::requireRole(['Admin']);
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $success = $this->model->archive($timesheetId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Timesheet archived!' : 'Failed to archive.'
        ];
    }

    /**
     * Restore timesheet
     */
    public function restore(): array
    {
        Auth::requireRole(['Admin']);
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        $success = $this->model->restore($timesheetId);
        
        return [
            'success' => $success,
            'message' => $success ? 'Timesheet restored!' : 'Failed to restore.'
        ];
    }

    /**
     * Delete timesheet
     */
    public function delete(): array
    {
        Auth::requireRole(['Admin']);
        
        $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
        
        if (!$timesheetId) {
            return ['success' => false, 'message' => 'Invalid timesheet ID.'];
        }
        
        try {
            $this->model->db->beginTransaction();
            
            // Delete entries first
            $this->model->db->prepare("DELETE FROM timesheet_entries WHERE timesheet_id = ?")->execute([$timesheetId]);
            
            // Delete timesheet
            $success = $this->model->delete($timesheetId);
            
            $this->model->db->commit();
            
            return [
                'success' => $success,
                'message' => $success ? 'Timesheet deleted permanently!' : 'Failed to delete.'
            ];
            
        } catch (\Exception $e) {
            $this->model->db->rollBack();
            return ['success' => false, 'message' => 'Failed to delete timesheet.'];
        }
    }
}