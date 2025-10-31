<?php
/**
 * Timesheet Model
 * 
 * @package HR3
 * @subpackage Models
 */

declare(strict_types=1);

namespace HR3\Models;

use PDO;

class Timesheet extends BaseModel
{
    protected string $table = 'timesheets';
    protected string $primaryKey = 'timesheet_id';

    /**
     * Get timesheet by user and date range
     */
    public function getByUserAndDateRange(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT t.*, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM timesheet_entries WHERE timesheet_id = t.timesheet_id) as entry_count
                FROM {$this->table} t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.user_id = :user_id 
                AND t.week_start >= :start_date 
                AND t.week_end <= :end_date
                AND t.is_archived = 0
                ORDER BY t.week_start DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending timesheets for approval
     */
    public function getPendingTimesheets(?int $managerId = null): array
    {
        $sql = "SELECT t.*, u.first_name, u.last_name, d.department_name,
                       (SELECT COUNT(*) FROM timesheet_entries WHERE timesheet_id = t.timesheet_id) as entry_count
                FROM {$this->table} t
                JOIN users u ON t.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE t.status = 'Pending'
                AND t.is_archived = 0";
        
        if ($managerId) {
            $sql .= " AND d.manager_id = :manager_id";
        }
        
        $sql .= " ORDER BY t.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        if ($managerId) {
            $stmt->execute([':manager_id' => $managerId]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get timesheet with entries
     */
    public function getWithEntries(int $timesheetId): ?array
    {
        $timesheet = $this->find($timesheetId);
        if (!$timesheet) {
            return null;
        }
        
        $sql = "SELECT * FROM timesheet_entries 
                WHERE timesheet_id = :timesheet_id 
                ORDER BY work_date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':timesheet_id' => $timesheetId]);
        $timesheet['entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $timesheet;
    }

    /**
     * Create timesheet with entries
     */
    public function createWithEntries(array $timesheetData, array $entries): int|false
    {
        try {
            $this->db->beginTransaction();
            
            $timesheetId = $this->create($timesheetData);
            if (!$timesheetId) {
                throw new \Exception('Failed to create timesheet');
            }
            
            foreach ($entries as $entry) {
                $entry['timesheet_id'] = $timesheetId;
                $this->addEntry($entry);
            }
            
            $this->db->commit();
            return $timesheetId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Add entry to timesheet
     */
    public function addEntry(array $entryData): int|false
    {
        $sql = "INSERT INTO timesheet_entries 
                (timesheet_id, work_date, project_name, task_description, hours_worked) 
                VALUES (:timesheet_id, :work_date, :project_name, :task_description, :hours_worked)";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($entryData)) {
            return (int) $this->db->lastInsertId();
        }
        
        return false;
    }

    /**
     * Update entry
     */
    public function updateEntry(int $entryId, array $data): bool
    {
        $updates = [];
        foreach (array_keys($data) as $key) {
            $updates[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE timesheet_entries SET " . implode(', ', $updates) . 
               " WHERE entry_id = :entry_id";
        
        $data['entry_id'] = $entryId;
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($data);
    }

    /**
     * Delete entry
     */
    public function deleteEntry(int $entryId): bool
    {
        $sql = "DELETE FROM timesheet_entries WHERE entry_id = :entry_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':entry_id' => $entryId]);
    }

    /**
     * Approve timesheet
     */
    public function approve(int $timesheetId, int $approverId): bool
    {
        return $this->update($timesheetId, [
            'status' => 'Approved',
            'approved_by' => $approverId
        ]);
    }

    /**
     * Reject timesheet
     */
    public function reject(int $timesheetId, int $approverId): bool
    {
        return $this->update($timesheetId, [
            'status' => 'Rejected',
            'approved_by' => $approverId
        ]);
    }

    /**
     * Archive timesheet
     */
    public function archive(int $timesheetId): bool
    {
        return $this->update($timesheetId, [
            'is_archived' => 1,
            'archived_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Restore timesheet
     */
    public function restore(int $timesheetId): bool
    {
        return $this->update($timesheetId, [
            'is_archived' => 0,
            'archived_at' => null
        ]);
    }

    /**
     * Calculate total hours from entries
     */
    public function calculateTotalHours(int $timesheetId): float
    {
        $sql = "SELECT SUM(hours_worked) as total FROM timesheet_entries 
                WHERE timesheet_id = :timesheet_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':timesheet_id' => $timesheetId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get statistics
     */
    public function getStatistics(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                COUNT(*) as total_timesheets,
                SUM(total_hours) as total_hours,
                AVG(total_hours) as avg_hours,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM {$this->table}
                WHERE user_id = :user_id
                AND week_start >= :start_date 
                AND week_end <= :end_date
                AND is_archived = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}