<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ClaimsReimbursement.php';

use HR3\Config\Auth;
use HR3\Models\ClaimsReimbursement;

Auth::requireRole(['Admin', 'Manager']);

$model = new ClaimsReimbursement();
$managerId = Auth::hasRole('Manager') ? Auth::getUserId() : null;
$pendingClaims = $model->getPendingForApproval($managerId);

$totalAmount = array_sum(array_column($pendingClaims, 'amount'));

$pageTitle = "Manage Claims";
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
        .claim-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .claim-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .action-buttons .btn {
            min-width: 100px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tasks me-2"></i>Manage Claims</h1>
                    <div>
                        <span class="badge bg-warning fs-6 me-2">
                            <i class="fas fa-clock me-1"></i><?= count($pendingClaims) ?> Pending
                        </span>
                        <span class="badge bg-info fs-6">
                            <i class="fas fa-money-bill-wave me-1"></i>₱<?= number_format($totalAmount, 2) ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($pendingClaims)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>All Caught Up!</h4>
                        <p class="text-muted">No pending claims to review at the moment.</p>
                    </div>
                </div>
                <?php else: ?>

                <div class="row">
                    <?php foreach ($pendingClaims as $claim): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card claim-card h-100" onclick="showClaimModal(<?= htmlspecialchars(json_encode($claim)) ?>)">
                            <div class="card-header bg-warning bg-opacity-10">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-warning">Pending</span>
                                    <small class="text-muted">#<?= $claim['claim_id'] ?></small>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user-circle fa-2x text-primary me-2"></i>
                                        <div>
                                            <strong><?= sanitize_output($claim['first_name'] . ' ' . $claim['last_name']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= sanitize_output($claim['department_name'] ?? 'N/A') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <span class="badge bg-info">
                                        <i class="fas fa-tag me-1"></i><?= sanitize_output($claim['claim_type']) ?>
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <h4 class="text-success mb-0">
                                        ₱<?= number_format((float)$claim['amount'], 2) ?>
                                    </h4>
                                </div>

                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= format_date($claim['created_at'], 'M d, Y') ?>
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <p class="text-muted small mb-0">
                                        <?= sanitize_output(substr($claim['description'], 0, 80)) ?>
                                        <?= strlen($claim['description']) > 80 ? '...' : '' ?>
                                    </p>
                                </div>

                                <?php if (($claim['attachment_count'] ?? 0) > 0): ?>
                                <div class="mb-2">
                                    <small class="text-primary">
                                        <i class="fas fa-paperclip me-1"></i>
                                        <?= $claim['attachment_count'] ?> attachment(s)
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success btn-sm" 
                                            onclick="event.stopPropagation(); approveClaim(<?= $claim['claim_id'] ?>)">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" 
                                            onclick="event.stopPropagation(); rejectClaim(<?= $claim['claim_id'] ?>)">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Alternative Table View -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>List View</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Attachments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingClaims as $claim): ?>
                                    <tr>
                                        <td><?= format_date($claim['created_at'], 'M d, Y') ?></td>
                                        <td>
                                            <strong><?= sanitize_output($claim['first_name'] . ' ' . $claim['last_name']) ?></strong>
                                        </td>
                                        <td><?= sanitize_output($claim['department_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= sanitize_output($claim['claim_type']) ?></span>
                                        </td>
                                        <td><strong>₱<?= number_format((float)$claim['amount'], 2) ?></strong></td>
                                        <td>
                                            <button class="btn btn-sm btn-link p-0" 
                                                    onclick="showDescription('<?= htmlspecialchars($claim['description'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($claim['attachment_count'] ?? 0) > 0): ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="viewAttachments(<?= $claim['claim_id'] ?>)">
                                                    <i class="fas fa-paperclip"></i> <?= $claim['attachment_count'] ?>
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted">None</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success" 
                                                        onclick="approveClaim(<?= $claim['claim_id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger" 
                                                        onclick="rejectClaim(<?= $claim['claim_id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Claim Details Modal -->
    <div class="modal fade" id="claimModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="claimDetails">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="modalApproveBtn">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="modalRejectBtn">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentClaimId = null;
        const claimModal = new bootstrap.Modal(document.getElementById('claimModal'));

        function showClaimModal(claim) {
            currentClaimId = claim.claim_id;
            
            document.getElementById('claimDetails').innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Claim ID</label>
                        <div><strong>#${claim.claim_id}</strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Date Submitted</label>
                        <div>${new Date(claim.created_at).toLocaleDateString()}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Employee</label>
                        <div><strong>${claim.first_name} ${claim.last_name}</strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Department</label>
                        <div>${claim.department_name || 'N/A'}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Claim Type</label>
                        <div><span class="badge bg-info">${claim.claim_type}</span></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Amount</label>
                        <div><h4 class="text-success mb-0">₱${parseFloat(claim.amount).toFixed(2)}</h4></div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Description</label>
                        <div class="border rounded p-3 bg-light">${claim.description}</div>
                    </div>
                    ${claim.attachment_count > 0 ? `
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Attachments</label>
                        <div class="alert alert-info">
                            <i class="fas fa-paperclip me-2"></i>${claim.attachment_count} file(s) attached
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('modalApproveBtn').onclick = () => {
                claimModal.hide();
                approveClaim(claim.claim_id);
            };
            
            document.getElementById('modalRejectBtn').onclick = () => {
                claimModal.hide();
                rejectClaim(claim.claim_id);
            };
            
            claimModal.show();
        }

        function showDescription(description) {
            Swal.fire({
                title: 'Claim Description',
                html: `<div class="text-start">${description}</div>`,
                icon: 'info',
                width: '600px'
            });
        }

        async function approveClaim(claimId) {
            const result = await Swal.fire({
                title: 'Approve Claim?',
                html: `
                    <div class="text-start">
                        <p>Are you sure you want to approve this claim?</p>
                        <ul>
                            <li>This action will forward the claim to Finance for payment processing</li>
                            <li>The employee will be notified of the approval</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Yes, Approve',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'text-start'
                }
            });

            if (result.isConfirmed) {
                try {
                    const response = await axios.post('../../api/claims_reimbursement/approve.php', {
                        claim_id: claimId
                    });
                    
                    if (response.data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Approved!',
                            text: response.data.message,
                            confirmButtonColor: '#28a745'
                        });
                        location.reload();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to approve claim. Please try again.', 'error');
                }
            }
        }

        async function rejectClaim(claimId) {
            const result = await Swal.fire({
                title: 'Reject Claim?',
                html: `
                    <div class="text-start mb-3">
                        <p>Please provide a detailed reason for rejection:</p>
                    </div>
                `,
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason (minimum 10 characters)...',
                inputAttributes: {
                    rows: 4,
                    style: 'width: 100%'
                },
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fas fa-times me-1"></i>Yes, Reject',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value || value.trim().length < 10) {
                        return 'Please provide a detailed reason (minimum 10 characters)';
                    }
                }
            });

            if (result.isConfirmed) {
                try {
                    const response = await axios.post('../../api/claims_reimbursement/reject.php', {
                        claim_id: claimId,
                        reason: result.value
                    });
                    
                    if (response.data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Rejected',
                            text: response.data.message,
                            confirmButtonColor: '#28a745'
                        });
                        location.reload();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to reject claim. Please try again.', 'error');
                }
            }
        }

        function viewAttachments(claimId) {
            Swal.fire({
                title: 'Attachments',
                text: 'Attachment viewing functionality would be implemented here',
                icon: 'info'
            });
        }
    </script>
</body>
</html>