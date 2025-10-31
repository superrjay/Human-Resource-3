<?php
/**
 * Leave Model - Fixed Version
 * 
 * @package HR3
 * @subpackage Models
 */

declare(strict_types=1);

namespace HR3\Models;

use PDO;

class Leave extends BaseModel
{
    protected string $table = 'leave_requests';
    protected string $primaryKey = 'leave_id';

    /**
     * Get leave requests by user
     */
    public function getByUser(int $userId, ?string $status = null): array
    {
        $sql = "SELECT lr.*, lt.leave_type_name, 
                       CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                FROM {$this->table} lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                LEFT JOIN users approver ON lr.approved_by = approver.user_id
                WHERE lr.user_id = :user_id AND lr.is_archived = 0";
        
        $params = [':user_id' => $userId];
        
        if ($status) {
            $sql .= " AND lr.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY lr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending requests for manager
     */
    public function getPendingForManager(?int $managerId = null): array
    {
        $sql = "SELECT lr.*, lt.leave_type_name, u.first_name, u.last_name, 
                       d.department_name, u.email
                FROM {$this->table} lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                JOIN users u ON lr.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE lr.status = 'Pending' AND lr.is_archived = 0";
        
        $params = [];
        if ($managerId) {
            $sql .= " AND d.manager_id = :manager_id";
            $params[':manager_id'] = $managerId;
        }
        
        $sql .= " ORDER BY lr.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check leave balance
     */
    public function getBalance(int $userId, int $leaveTypeId): ?array
    {
        $sql = "SELECT lb.*, lt.leave_type_name, lt.max_days_per_year
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lb.user_id = :user_id 
                AND lb.leave_type_id = :leave_type_id 
                AND lb.year = YEAR(CURDATE())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId, 
            ':leave_type_id' => $leaveTypeId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Check overlapping leaves
     */
    public function hasOverlap(int $userId, string $start, string $end, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE user_id = :user_id 
                AND status IN ('Pending', 'Approved')
                AND (
                    (start_date <= :end AND end_date >= :start)
                )
                AND is_archived = 0";
        
        $params = [':user_id' => $userId, ':start' => $start, ':end' => $end];
        
        if ($excludeId) {
            $sql .= " AND leave_id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    /**
     * Approve leave
     */
    public function approve(int $leaveId, int $approverId): bool
    {
        $leave = $this->find($leaveId);
        if (!$leave || $leave['status'] !== 'Pending') return false;
        
        try {
            $this->db->beginTransaction();
            
            // Update request status
            $sql = "UPDATE {$this->table} 
                    SET status = 'Approved', approved_by = :approver, updated_at = NOW()
                    WHERE leave_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':approver' => $approverId, ':id' => $leaveId]);
            
            // Deduct from balance
            $balance = $this->getBalance((int)$leave['user_id'], (int)$leave['leave_type_id']);
            if ($balance) {
                $newBalance = $balance['remaining_days'] - $leave['total_days'];
                $sql = "UPDATE leave_balances 
                        SET remaining_days = :balance 
                        WHERE balance_id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':balance' => $newBalance, ':id' => $balance['balance_id']]);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Approve error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject leave
     */
    public function reject(int $leaveId, int $approverId): bool
    {
        $leave = $this->find($leaveId);
        if (!$leave || $leave['status'] !== 'Pending') return false;
        
        $sql = "UPDATE {$this->table} 
                SET status = 'Rejected', approved_by = :approver, updated_at = NOW()
                WHERE leave_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':approver' => $approverId, ':id' => $leaveId]);
    }

    /**
     * Cancel leave
     */
    public function cancel(int $leaveId): bool
    {
        $leave = $this->find($leaveId);
        if (!$leave || !in_array($leave['status'], ['Pending', 'Approved'])) return false;
        
        try {
            $this->db->beginTransaction();
            
            // If approved, restore balance
            if ($leave['status'] === 'Approved') {
                $balance = $this->getBalance((int)$leave['user_id'], (int)$leave['leave_type_id']);
                if ($balance) {
                    $newBalance = $balance['remaining_days'] + $leave['total_days'];
                    $sql = "UPDATE leave_balances 
                            SET remaining_days = :balance 
                            WHERE balance_id = :id";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([':balance' => $newBalance, ':id' => $balance['balance_id']]);
                }
            }
            
            $sql = "UPDATE {$this->table} SET status = 'Cancelled', updated_at = NOW() WHERE leave_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $leaveId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Cancel error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Archive/Restore
     */
    public function archive(int $leaveId): bool
    {
        $sql = "UPDATE {$this->table} SET is_archived = 1, archived_at = NOW() WHERE leave_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $leaveId]);
    }

    public function restore(int $leaveId): bool
    {
        $sql = "UPDATE {$this->table} SET is_archived = 0, archived_at = NULL WHERE leave_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $leaveId]);
    }

    /**
     * Get all leave types
     */
    public function getLeaveTypes(): array
    {
        $stmt = $this->db->query("SELECT * FROM leave_types ORDER BY leave_type_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user balances with leave types
     */
    public function getUserBalances(int $userId): array
    {
        $sql = "SELECT lb.*, lt.leave_type_name, lt.max_days_per_year
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lb.user_id = :user_id AND lb.year = YEAR(CURDATE())
                ORDER BY lt.leave_type_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistics
     */
    public function getStatistics(int $userId, int $year): array
    {
        $sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'Approved' THEN total_days ELSE 0 END) as approved_days,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(start_date) = :year AND is_archived = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}