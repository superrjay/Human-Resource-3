<?php
declare(strict_types=1);

namespace HR3\Models;

use PDO;

class ShiftSchedule extends BaseModel
{
    protected string $table = 'shifts';
    protected string $primaryKey = 'shift_id';

    public function getAllActive(): array
    {
        $sql = "SELECT s.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as creator_name,
                       u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM shift_assignments 
                        WHERE shift_id = s.shift_id AND is_archived = 0) as assignment_count
                FROM {$this->table} s
                LEFT JOIN users u ON s.created_by = u.user_id
                WHERE s.is_archived = 0
                ORDER BY s.start_time ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserSchedule(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT sa.*, 
                       s.shift_name, s.start_time, s.end_time, s.is_night_shift,
                       sa.assigned_date
                FROM shift_assignments sa
                INNER JOIN shifts s ON sa.shift_id = s.shift_id
                WHERE sa.user_id = :user_id
                AND sa.assigned_date BETWEEN :start_date AND :end_date
                AND sa.is_archived = 0
                AND s.is_archived = 0
                ORDER BY sa.assigned_date ASC, s.start_time ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssignmentsByDateRange(string $startDate, string $endDate, ?int $userId = null): array
    {
        $sql = "SELECT sa.*, 
                       s.shift_name, s.start_time, s.end_time, s.is_night_shift,
                       CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                       u.first_name, u.last_name,
                       d.department_name,
                       CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                FROM shift_assignments sa
                INNER JOIN shifts s ON sa.shift_id = s.shift_id
                INNER JOIN users u ON sa.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                LEFT JOIN users approver ON sa.approved_by = approver.user_id
                WHERE sa.assigned_date BETWEEN :start_date AND :end_date
                AND sa.is_archived = 0";
        
        $params = [':start_date' => $startDate, ':end_date' => $endDate];
        
        if ($userId) {
            $sql .= " AND sa.user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $sql .= " ORDER BY sa.assigned_date ASC, s.start_time ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignShift(int $shiftId, int $userId, string $date, ?int $approvedBy = null): int|false
    {
        if ($this->hasConflict($userId, $date)) {
            return false;
        }
        
        $sql = "INSERT INTO shift_assignments (shift_id, user_id, assigned_date, approved_by)
                VALUES (:shift_id, :user_id, :assigned_date, :approved_by)";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute([
            ':shift_id' => $shiftId,
            ':user_id' => $userId,
            ':assigned_date' => $date,
            ':approved_by' => $approvedBy
        ])) {
            return (int) $this->db->lastInsertId();
        }
        
        return false;
    }

    public function bulkAssign(int $shiftId, array $userIds, array $dates, int $approvedBy): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        try {
            $this->db->beginTransaction();
            
            foreach ($userIds as $userId) {
                foreach ($dates as $date) {
                    $assignmentId = $this->assignShift($shiftId, (int)$userId, $date, $approvedBy);
                    if ($assignmentId) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                }
            }
            
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }

    public function hasConflict(int $userId, string $date): bool
    {
        $sql = "SELECT COUNT(*) as count FROM shift_assignments
                WHERE user_id = :user_id AND assigned_date = :date AND is_archived = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':date' => $date]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    public function updateAssignment(int $assignmentId, array $data): bool
    {
        $updates = [];
        foreach (array_keys($data) as $key) {
            $updates[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE shift_assignments SET " . implode(', ', $updates) . 
               " WHERE assignment_id = :assignment_id";
        
        $data['assignment_id'] = $assignmentId;
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($data);
    }

    public function deleteAssignment(int $assignmentId): bool
    {
        $sql = "DELETE FROM shift_assignments WHERE assignment_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $assignmentId]);
    }

    public function archive(int $shiftId): bool
    {
        return $this->update($shiftId, [
            'is_archived' => 1,
            'archived_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getCoverageReport(string $date): array
    {
        $sql = "SELECT s.shift_name, s.start_time, s.end_time, s.is_night_shift,
                       COUNT(sa.assignment_id) as assigned_count,
                       GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as employees
                FROM shifts s
                LEFT JOIN shift_assignments sa ON s.shift_id = sa.shift_id 
                    AND sa.assigned_date = :date AND sa.is_archived = 0
                LEFT JOIN users u ON sa.user_id = u.user_id
                WHERE s.is_archived = 0
                GROUP BY s.shift_id, s.shift_name, s.start_time, s.end_time, s.is_night_shift
                ORDER BY s.start_time ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}