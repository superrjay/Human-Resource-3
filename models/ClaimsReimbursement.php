<?php
/**
 * Claims Reimbursement Model
 * 
 * @package HR3
 * @subpackage Models
 */

declare(strict_types=1);

namespace HR3\Models;

use PDO;

class ClaimsReimbursement extends BaseModel
{
    protected string $table = 'claims';
    protected string $primaryKey = 'claim_id';

    /**
     * Get claims by user
     */
    public function getByUser(int $userId, ?string $status = null): array
    {
        $sql = "SELECT c.*, u.first_name, u.last_name,
                       approver.first_name as approver_first, approver.last_name as approver_last,
                       (SELECT COUNT(*) FROM claim_attachments WHERE claim_id = c.claim_id) as attachment_count
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN users approver ON c.approved_by = approver.user_id
                WHERE c.user_id = :user_id AND c.is_archived = 0";
        
        $params = [':user_id' => $userId];
        
        if ($status) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending claims for approval
     */
    public function getPendingForApproval(?int $managerId = null): array
    {
        $sql = "SELECT c.*, u.first_name, u.last_name, d.department_name,
                       (SELECT COUNT(*) FROM claim_attachments WHERE claim_id = c.claim_id) as attachment_count
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE c.status = 'Pending' AND c.is_archived = 0";
        
        if ($managerId) {
            $sql .= " AND d.manager_id = :manager_id";
        }
        
        $sql .= " ORDER BY c.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $managerId ? $stmt->execute([':manager_id' => $managerId]) : $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get approved claims for finance
     */
    public function getApprovedForFinance(): array
    {
        $sql = "SELECT c.*, u.first_name, u.last_name, d.department_name
                FROM {$this->table} c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE c.status = 'Approved' AND c.finance_reviewed = 0 AND c.is_archived = 0
                ORDER BY c.updated_at ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve claim
     */
    public function approve(int $claimId, int $approverId): bool
    {
        return $this->update($claimId, [
            'status' => 'Approved',
            'approved_by' => $approverId
        ]);
    }

    /**
     * Reject claim
     */
    public function reject(int $claimId, int $approverId): bool
    {
        return $this->update($claimId, [
            'status' => 'Rejected',
            'approved_by' => $approverId
        ]);
    }

    /**
     * Mark as paid (Finance)
     */
    public function markAsPaid(int $claimId): bool
    {
        return $this->update($claimId, [
            'status' => 'Paid',
            'finance_reviewed' => 1
        ]);
    }

    /**
     * Add attachment
     */
    public function addAttachment(int $claimId, string $fileUrl): int|false
    {
        $sql = "INSERT INTO claim_attachments (claim_id, file_url) VALUES (:claim_id, :file_url)";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute([':claim_id' => $claimId, ':file_url' => $fileUrl])) {
            return (int) $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Get attachments
     */
    public function getAttachments(int $claimId): array
    {
        $sql = "SELECT * FROM claim_attachments WHERE claim_id = :claim_id ORDER BY uploaded_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':claim_id' => $claimId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Archive/Restore
     */
    public function archive(int $claimId): bool
    {
        return $this->update($claimId, ['is_archived' => 1, 'archived_at' => date('Y-m-d H:i:s')]);
    }

    public function restore(int $claimId): bool
    {
        return $this->update($claimId, ['is_archived' => 0, 'archived_at' => null]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                COUNT(*) as total_claims,
                SUM(amount) as total_amount,
                SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count
                FROM {$this->table}
                WHERE user_id = :user_id
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                AND is_archived = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}