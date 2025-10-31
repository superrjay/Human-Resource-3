<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ClaimsReimbursement.php';

use HR3\Config\Auth;
use HR3\Models\ClaimsReimbursement;

Auth::requireAuth();

$model = new ClaimsReimbursement();
$userId = Auth::getUserId();
$claims = $model->getByUser($userId);
$stats = $model->getStatistics($userId, date('Y-01-01'), date('Y-12-31'));

$pageTitle = "My Claims";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card.total { border-left-color: #0d6efd; }
        .stat-card.amount { border-left-color: #198754; }
        .stat-card.approved { border-left-color: #20c997; }
        .stat-card.pending { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-receipt me-2"></i>My Claims</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClaimModal">
                        <i class="fas fa-plus me-1"></i>New Claim
                    </button>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card total">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Claims</h6>
                                        <h3 class="mb-0"><?= $stats['total_claims'] ?? 0 ?></h3>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card amount">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Amount</h6>
                                        <h3 class="mb-0">₱<?= number_format((float)($stats['total_amount'] ?? 0), 2) ?></h3>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card approved">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Approved</h6>
                                        <h3 class="mb-0">₱<?= number_format((float)($stats['approved_amount'] ?? 0), 2) ?></h3>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card pending">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending</h6>
                                        <h3 class="mb-0"><?= $stats['pending_count'] ?? 0 ?></h3>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select id="statusFilter" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type Filter</label>
                                <select id="typeFilter" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="Travel">Travel</option>
                                    <option value="Meal">Meal</option>
                                    <option value="Office">Office</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-secondary" onclick="resetFilters()">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Claims Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($claims)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No claims found. Submit your first claim!</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="claimsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Attachments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($claims as $claim): ?>
                                    <tr data-status="<?= $claim['status'] ?>" data-type="<?= $claim['claim_type'] ?>">
                                        <td><strong>#<?= $claim['claim_id'] ?></strong></td>
                                        <td><?= format_date($claim['created_at'], 'M d, Y') ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= sanitize_output($claim['claim_type']) ?>
                                            </span>
                                        </td>
                                        <td><strong>₱<?= number_format((float)$claim['amount'], 2) ?></strong></td>
                                        <td>
                                            <?php 
                                            $desc = sanitize_output($claim['description']);
                                            echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'Pending' => 'warning',
                                                'Approved' => 'success',
                                                'Rejected' => 'danger',
                                                'Paid' => 'primary'
                                            ];
                                            $color = $statusColors[$claim['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>">
                                                <?= $claim['status'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($claim['attachment_count'] ?? 0) > 0): ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="viewAttachments(<?= $claim['claim_id'] ?>)">
                                                    <i class="fas fa-paperclip"></i> <?= $claim['attachment_count'] ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewDetails(<?= $claim['claim_id'] ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($claim['status'] === 'Pending'): ?>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="deleteClaim(<?= $claim['claim_id'] ?>)"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Claim Modal -->
    <div class="modal fade" id="createClaimModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createClaimForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle me-2"></i>New Claim Request
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Claim Type <span class="text-danger">*</span></label>
                            <select name="claim_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Travel">Travel</option>
                                <option value="Meal">Meal</option>
                                <option value="Office">Office Supplies</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" 
                                   step="0.01" min="0.01" max="50000" 
                                   placeholder="0.00" required>
                            <small class="text-muted">Maximum: ₱50,000.00</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" 
                                      minlength="10" maxlength="500"
                                      placeholder="Provide detailed description of your claim..." 
                                      required></textarea>
                            <small class="text-muted">Minimum 10 characters</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attachments</label>
                            <input type="file" name="attachments[]" class="form-control" 
                                   multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                                   id="fileInput">
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle"></i> Max 5MB per file. 
                                Allowed: JPG, PNG, PDF, DOC, DOCX
                            </small>
                            <div id="fileList" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-1"></i>Submit Claim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // File input preview
        document.getElementById('fileInput')?.addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                const ul = document.createElement('ul');
                ul.className = 'list-group list-group-flush';
                
                Array.from(this.files).forEach((file, index) => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item py-1 px-2 small';
                    li.innerHTML = `
                        <i class="fas fa-file me-2"></i>${file.name} 
                        <span class="text-muted">(${(file.size / 1024).toFixed(1)} KB)</span>
                    `;
                    ul.appendChild(li);
                });
                
                fileList.appendChild(ul);
            }
        });

        // Submit claim form
        document.getElementById('createClaimForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const submitBtn = document.getElementById('submitBtn');
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
            try {
                const response = await axios.post('../../api/claims_reimbursement/create.php', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                
                if (response.data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.data.message,
                        confirmButtonColor: '#28a745'
                    });
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.data.message
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Claim';
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to submit claim. Please try again.'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit Claim';
            }
        });

        // Filter functionality
        document.getElementById('statusFilter')?.addEventListener('change', filterTable);
        document.getElementById('typeFilter')?.addEventListener('change', filterTable);

        function filterTable() {
            const statusFilter = document.getElementById('statusFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const rows = document.querySelectorAll('#claimsTable tbody tr');
            
            rows.forEach(row => {
                const status = row.dataset.status;
                const type = row.dataset.type;
                
                const statusMatch = !statusFilter || status === statusFilter;
                const typeMatch = !typeFilter || type === typeFilter;
                
                row.style.display = (statusMatch && typeMatch) ? '' : 'none';
            });
        }

        function resetFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('typeFilter').value = '';
            filterTable();
        }

        async function viewDetails(claimId) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            const content = document.getElementById('detailsContent');
            
            modal.show();
            content.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            try {
                const response = await axios.get(`../../api/claims_reimbursement/details.php?claim_id=${claimId}`);
                
                if (response.data.success) {
                    const claim = response.data.data;
                    content.innerHTML = `
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Claim ID:</strong><br>#${claim.claim_id}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Status:</strong><br>
                                <span class="badge bg-${getStatusColor(claim.status)}">${claim.status}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Type:</strong><br>${claim.claim_type}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Amount:</strong><br>₱${parseFloat(claim.amount).toFixed(2)}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Created:</strong><br>${new Date(claim.created_at).toLocaleDateString()}
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Updated:</strong><br>${new Date(claim.updated_at).toLocaleDateString()}
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Description:</strong><br>
                                <p class="border p-3 rounded bg-light">${claim.description}</p>
                            </div>
                            ${claim.attachments && claim.attachments.length > 0 ? `
                            <div class="col-12 mb-3">
                                <strong>Attachments:</strong><br>
                                <ul class="list-group mt-2">
                                    ${claim.attachments.map(att => `
                                        <li class="list-group-item">
                                            <i class="fas fa-paperclip me-2"></i>
                                            <a href="${att.file_url}" target="_blank">View Attachment</a>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    content.innerHTML = `<div class="alert alert-danger">${response.data.message}</div>`;
                }
            } catch (error) {
                content.innerHTML = `<div class="alert alert-danger">Failed to load claim details</div>`;
            }
        }

        function getStatusColor(status) {
            const colors = {
                'Pending': 'warning',
                'Approved': 'success',
                'Rejected': 'danger',
                'Paid': 'primary'
            };
            return colors[status] || 'secondary';
        }

        async function deleteClaim(claimId) {
            const result = await Swal.fire({
                title: 'Delete Claim?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                try {
                    const response = await axios.post('../../api/claims_reimbursement/delete.php', {
                        claim_id: claimId
                    });
                    
                    if (response.data.success) {
                        await Swal.fire('Deleted!', response.data.message, 'success');
                        location.reload();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Failed to delete claim', 'error');
                }
            }
        }

        function viewAttachments(claimId) {
            viewDetails(claimId);
        }
    </script>
</body>
</html>