<?php
/**
 * Claims Reimbursement Controller
 * 
 * @package HR3
 * @subpackage Controllers
 */

declare(strict_types=1);

namespace HR3\Controllers;

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/ClaimsReimbursement.php';

use HR3\Models\ClaimsReimbursement;
use HR3\Config\Auth;
use PDO;

class ClaimsReimbursementController
{
    private ClaimsReimbursement $model;
    private const UPLOAD_DIR = __DIR__ . '/../uploads/claims/';
    private const MAX_FILE_SIZE = 5242880; // 5MB
    private const ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

    public function __construct()
    {
        $this->model = new ClaimsReimbursement();
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }

    /**
     * Get JSON input from request body
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Create claim
     */
    public function create(): array
    {
        try {
            Auth::requireAuth();
            
            $userId = Auth::getUserId();
            $claimType = $_POST['claim_type'] ?? '';
            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            
            // Validation
            if (!in_array($claimType, ['Travel', 'Meal', 'Office', 'Other'])) {
                return ['success' => false, 'message' => 'Invalid claim type.'];
            }
            
            if ($amount <= 0 || $amount > 50000) {
                return ['success' => false, 'message' => 'Amount must be between 0 and 50,000.'];
            }
            
            if (empty($description) || strlen($description) < 10) {
                return ['success' => false, 'message' => 'Description must be at least 10 characters.'];
            }
            
            $data = [
                'user_id' => $userId,
                'claim_type' => $claimType,
                'amount' => $amount,
                'description' => $description,
                'status' => 'Pending'
            ];
            
            $claimId = $this->model->create($data);
            
            if ($claimId) {
                // Handle file upload
                if (!empty($_FILES['attachments']['name'][0])) {
                    $uploadResult = $this->handleFileUpload($claimId);
                    if (!$uploadResult['success']) {
                        return ['success' => false, 'message' => 'Claim created but file upload failed: ' . $uploadResult['message']];
                    }
                }
                
                return ['success' => true, 'message' => 'Claim submitted successfully!', 'claim_id' => $claimId];
            }
            
            return ['success' => false, 'message' => 'Failed to create claim.'];
            
        } catch (\Exception $e) {
            error_log("Create claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the claim.'];
        }
    }

    /**
     * Get user claims
     */
    public function getUserClaims(): array
    {
        try {
            Auth::requireAuth();
            
            $userId = (int)($_GET['user_id'] ?? Auth::getUserId());
            $status = $_GET['status'] ?? null;
            
            if ($userId !== Auth::getUserId() && !Auth::hasAnyRole(['Admin', 'Manager', 'Finance'])) {
                return ['success' => false, 'message' => 'Access denied.'];
            }
            
            $claims = $this->model->getByUser($userId, $status);
            $stats = $this->model->getStatistics($userId, date('Y-01-01'), date('Y-12-31'));
            
            return ['success' => true, 'data' => ['claims' => $claims, 'statistics' => $stats]];
            
        } catch (\Exception $e) {
            error_log("Get user claims error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve claims.'];
        }
    }

    /**
     * Get pending claims (Manager)
     */
    public function getPendingClaims(): array
    {
        try {
            Auth::requireRole(['Admin', 'Manager']);
            
            $managerId = Auth::hasRole('Manager') ? Auth::getUserId() : null;
            $claims = $this->model->getPendingForApproval($managerId);
            
            return ['success' => true, 'data' => $claims];
            
        } catch (\Exception $e) {
            error_log("Get pending claims error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve pending claims.'];
        }
    }

    /**
     * Get approved claims (Finance)
     */
    public function getApprovedClaims(): array
    {
        try {
            Auth::requireRole(['Admin', 'Finance']);
            
            $claims = $this->model->getApprovedForFinance();
            return ['success' => true, 'data' => $claims];
            
        } catch (\Exception $e) {
            error_log("Get approved claims error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve approved claims.'];
        }
    }

    /**
     * Approve claim
     */
    public function approve(): array
    {
        try {
            Auth::requireRole(['Admin', 'Manager']);
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            // Verify claim exists and is pending
            $claim = $this->model->find($claimId);
            if (!$claim) {
                return ['success' => false, 'message' => 'Claim not found.'];
            }
            
            if ($claim['status'] !== 'Pending') {
                return ['success' => false, 'message' => 'Only pending claims can be approved.'];
            }
            
            $success = $this->model->approve($claimId, Auth::getUserId());
            return [
                'success' => $success, 
                'message' => $success ? 'Claim approved successfully!' : 'Failed to approve claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Approve claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while approving the claim.'];
        }
    }

    /**
     * Reject claim
     */
    public function reject(): array
    {
        try {
            Auth::requireRole(['Admin', 'Manager']);
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            $reason = trim($input['reason'] ?? '');
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            if (empty($reason)) {
                return ['success' => false, 'message' => 'Rejection reason is required.'];
            }
            
            // Verify claim exists and is pending
            $claim = $this->model->find($claimId);
            if (!$claim) {
                return ['success' => false, 'message' => 'Claim not found.'];
            }
            
            if ($claim['status'] !== 'Pending') {
                return ['success' => false, 'message' => 'Only pending claims can be rejected.'];
            }
            
            $success = $this->model->reject($claimId, Auth::getUserId());
            
            // TODO: Store rejection reason in a separate table or notification
            
            return [
                'success' => $success, 
                'message' => $success ? 'Claim rejected.' : 'Failed to reject claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Reject claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while rejecting the claim.'];
        }
    }

    /**
     * Mark as paid (Finance)
     */
    public function markAsPaid(): array
    {
        try {
            Auth::requireRole(['Admin', 'Finance']);
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            // Verify claim is approved
            $claim = $this->model->find($claimId);
            if (!$claim) {
                return ['success' => false, 'message' => 'Claim not found.'];
            }
            
            if ($claim['status'] !== 'Approved') {
                return ['success' => false, 'message' => 'Only approved claims can be marked as paid.'];
            }
            
            $success = $this->model->markAsPaid($claimId);
            return [
                'success' => $success, 
                'message' => $success ? 'Claim marked as paid!' : 'Failed to mark as paid.'
            ];
            
        } catch (\Exception $e) {
            error_log("Mark as paid error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing payment.'];
        }
    }

    /**
     * Archive claim
     */
    public function archive(): array
    {
        try {
            Auth::requireRole(['Admin']);
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            $success = $this->model->archive($claimId);
            return [
                'success' => $success, 
                'message' => $success ? 'Claim archived successfully!' : 'Failed to archive claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Archive claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to archive claim.'];
        }
    }

    /**
     * Restore claim
     */
    public function restore(): array
    {
        try {
            Auth::requireRole(['Admin']);
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            $success = $this->model->restore($claimId);
            return [
                'success' => $success, 
                'message' => $success ? 'Claim restored successfully!' : 'Failed to restore claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Restore claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to restore claim.'];
        }
    }

    /**
     * Get claim details with attachments
     */
    public function getClaimDetails(): array
    {
        try {
            Auth::requireAuth();
            
            $claimId = (int)($_GET['claim_id'] ?? 0);
            
            if (!$claimId) {
                return ['success' => false, 'message' => 'Invalid claim ID.'];
            }
            
            $claim = $this->model->find($claimId);
            
            if (!$claim) {
                return ['success' => false, 'message' => 'Claim not found.'];
            }
            
            // Permission check
            if ($claim['user_id'] !== Auth::getUserId() && 
                !Auth::hasAnyRole(['Admin', 'Manager', 'Finance'])) {
                return ['success' => false, 'message' => 'Access denied.'];
            }
            
            $attachments = $this->model->getAttachments($claimId);
            $claim['attachments'] = $attachments;
            
            return ['success' => true, 'data' => $claim];
            
        } catch (\Exception $e) {
            error_log("Get claim details error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve claim details.'];
        }
    }

    /**
     * Update claim (only pending claims by owner)
     */
    public function update(): array
    {
        try {
            Auth::requireAuth();
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            $claim = $this->model->find($claimId);
            
            if (!$claim || $claim['user_id'] !== Auth::getUserId() || $claim['status'] !== 'Pending') {
                return ['success' => false, 'message' => 'Cannot edit this claim.'];
            }
            
            $data = [];
            
            if (isset($input['claim_type'])) {
                if (!in_array($input['claim_type'], ['Travel', 'Meal', 'Office', 'Other'])) {
                    return ['success' => false, 'message' => 'Invalid claim type.'];
                }
                $data['claim_type'] = $input['claim_type'];
            }
            
            if (isset($input['amount'])) {
                $amount = (float)$input['amount'];
                if ($amount <= 0 || $amount > 50000) {
                    return ['success' => false, 'message' => 'Invalid amount.'];
                }
                $data['amount'] = $amount;
            }
            
            if (isset($input['description'])) {
                $description = trim($input['description']);
                if (strlen($description) < 10) {
                    return ['success' => false, 'message' => 'Description must be at least 10 characters.'];
                }
                $data['description'] = $description;
            }
            
            if (empty($data)) {
                return ['success' => false, 'message' => 'No data to update.'];
            }
            
            $success = $this->model->update($claimId, $data);
            
            return [
                'success' => $success,
                'message' => $success ? 'Claim updated successfully!' : 'Failed to update claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Update claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update claim.'];
        }
    }

    /**
     * Delete claim (only pending, own claims)
     */
    public function delete(): array
    {
        try {
            Auth::requireAuth();
            
            $input = $this->getJsonInput();
            $claimId = (int)($input['claim_id'] ?? 0);
            
            $claim = $this->model->find($claimId);
            
            if (!$claim) {
                return ['success' => false, 'message' => 'Claim not found.'];
            }
            
            // Only owner can delete their own pending claims
            if ($claim['user_id'] !== Auth::getUserId() && !Auth::hasRole('Admin')) {
                return ['success' => false, 'message' => 'Access denied.'];
            }
            
            if ($claim['status'] !== 'Pending' && !Auth::hasRole('Admin')) {
                return ['success' => false, 'message' => 'Cannot delete approved/rejected claims.'];
            }
            
            // Delete attachments first
            $attachments = $this->model->getAttachments($claimId);
            foreach ($attachments as $attachment) {
                $filePath = __DIR__ . '/..' . $attachment['file_url'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            
            // Delete attachment records
            $stmt = $this->model->db->prepare("DELETE FROM claim_attachments WHERE claim_id = ?");
            $stmt->execute([$claimId]);
            
            // Delete claim
            $success = $this->model->delete($claimId);
            
            return [
                'success' => $success,
                'message' => $success ? 'Claim deleted successfully!' : 'Failed to delete claim.'
            ];
            
        } catch (\Exception $e) {
            error_log("Delete claim error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete claim.'];
        }
    }

    /**
     * Get all claims with filters (Admin/Manager/Finance)
     */
    public function getAllClaims(): array
    {
        try {
            Auth::requireRole(['Admin', 'Manager', 'Finance']);
            
            $filters = [
                'user_id' => $_GET['user_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'claim_type' => $_GET['claim_type'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'limit' => min((int)($_GET['limit'] ?? 100), 1000) // Cap at 1000
            ];
            
            $sql = "SELECT c.*, u.first_name, u.last_name, d.department_name,
                           approver.first_name as approver_first, approver.last_name as approver_last,
                           (SELECT COUNT(*) FROM claim_attachments WHERE claim_id = c.claim_id) as attachment_count
                    FROM claims c
                    JOIN users u ON c.user_id = u.user_id
                    LEFT JOIN departments d ON u.department_id = d.department_id
                    LEFT JOIN users approver ON c.approved_by = approver.user_id
                    WHERE c.is_archived = 0";
            
            $params = [];
            
            if ($filters['user_id']) {
                $sql .= " AND c.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if ($filters['status']) {
                $sql .= " AND c.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if ($filters['claim_type']) {
                $sql .= " AND c.claim_type = :claim_type";
                $params[':claim_type'] = $filters['claim_type'];
            }
            
            if ($filters['start_date']) {
                $sql .= " AND DATE(c.created_at) >= :start_date";
                $params[':start_date'] = $filters['start_date'];
            }
            
            if ($filters['end_date']) {
                $sql .= " AND DATE(c.created_at) <= :end_date";
                $params[':end_date'] = $filters['end_date'];
            }
            
            $sql .= " ORDER BY c.created_at DESC LIMIT :limit";
            
            $stmt = $this->model->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
            
            $stmt->execute();
            $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $claims];
            
        } catch (\Exception $e) {
            error_log("Get all claims error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve claims.'];
        }
    }

    /**
     * Handle file upload with validation
     */
    private function handleFileUpload(int $claimId): array
    {
        $uploadedFiles = 0;
        $errors = [];
        
        try {
            if (empty($_FILES['attachments']['tmp_name'])) {
                return ['success' => true, 'message' => 'No files to upload'];
            }
            
            $totalFiles = is_array($_FILES['attachments']['tmp_name']) 
                ? count($_FILES['attachments']['tmp_name']) 
                : 1;
            
            for ($i = 0; $i < $totalFiles; $i++) {
                $tmpName = is_array($_FILES['attachments']['tmp_name']) 
                    ? $_FILES['attachments']['tmp_name'][$i] 
                    : $_FILES['attachments']['tmp_name'];
                    
                if (empty($tmpName) || !is_uploaded_file($tmpName)) {
                    continue;
                }
                
                $fileName = is_array($_FILES['attachments']['name']) 
                    ? $_FILES['attachments']['name'][$i] 
                    : $_FILES['attachments']['name'];
                    
                $fileSize = is_array($_FILES['attachments']['size']) 
                    ? $_FILES['attachments']['size'][$i] 
                    : $_FILES['attachments']['size'];
                    
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Validate file size
                if ($fileSize > self::MAX_FILE_SIZE) {
                    $errors[] = "File {$fileName} exceeds 5MB limit";
                    continue;
                }
                
                // Validate file type
                if (!in_array($fileExt, self::ALLOWED_TYPES)) {
                    $errors[] = "File {$fileName} has invalid type";
                    continue;
                }
                
                // Generate unique filename
                $newFileName = uniqid('claim_') . '.' . $fileExt;
                $targetPath = self::UPLOAD_DIR . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $this->model->addAttachment($claimId, '/uploads/claims/' . $newFileName);
                    $uploadedFiles++;
                } else {
                    $errors[] = "Failed to upload {$fileName}";
                }
            }
            
            if ($uploadedFiles > 0) {
                return [
                    'success' => true, 
                    'message' => "{$uploadedFiles} file(s) uploaded" . (count($errors) > 0 ? " with some errors" : ""),
                    'errors' => $errors
                ];
            }
            
            return [
                'success' => false, 
                'message' => 'No files were uploaded', 
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'File upload failed', 'errors' => [$e->getMessage()]];
        }
    }
}