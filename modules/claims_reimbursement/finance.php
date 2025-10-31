<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/ClaimsReimbursement.php';

use HR3\Config\Auth;
use HR3\Models\ClaimsReimbursement;

Auth::requireRole(['Admin', 'Finance']);

$model = new ClaimsReimbursement();
$approvedClaims = $model->getApprovedForFinance();

$totalAmount = array_sum(array_column($approvedClaims, 'amount'));

$pageTitle = "Finance - Claims Payment";
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
        .payment-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .claim-row {
            transition: all 0.2s ease;
        }
        .claim-row:hover {
            background-color: #f8f9fa;
        }
        .claim-row.selected {
            background-color: #e7f3ff;
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
                    <h1 class="h2"><i class="fas fa-money-check-alt me-2"></i>Claims Payment Processing</h1>
                    <div>
                        <button class="btn btn-outline-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i>Export
                        </button>
                    </div>
                </div>

                <?php if (empty($approvedClaims)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-double fa-4x text-success mb-3"></i>
                        <h4>All Payments Processed!</h4>
                        <p class="text-muted">There are no approved claims waiting for payment at this time.</p>
                    </div>
                </div>
                <?php else: ?>

                <!-- Payment Summary -->
                <div class="payment-summary">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-2">
                                <i class="fas fa-wallet me-2"></i>Total Payment Required
                            </h3>
                            <h1 class="display-4 mb-0">₱<?= number_format($totalAmount, 2) ?></h1>
                            <p class="mb-0 mt-2 opacity-75">
                                <?= count($approvedClaims) ?> claim(s) ready for payment
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light btn-lg" onclick="processAllSelected()">
                                <i class="fas fa-check-double me-2"></i>Process Selected
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Claims Table -->
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Approved Claims
                            </h5>
                            <div class="form-check">
                                <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll()">
                                <label class="form-check-label" for="selectAll">Select All</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="financeTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <div class="text-center">#</div>
                                        </th>
                                        <th>Claim ID</th>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvedClaims as $claim): ?>
                                    <tr class="claim-row">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input claim-checkbox" 
                                                   value="<?= $claim['claim_id'] ?>"
                                                   data-amount="<?= $claim['amount'] ?>"
                                                   onchange="updateSelectedSummary()">
                                        </td>
                                        <td><strong class="text-primary">#<?= $claim['claim_id'] ?></strong></td>
                                        <td>
                                            <small><?= format_date($claim['updated_at'], 'M d, Y') ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= sanitize_output($claim['first_name'] . ' ' . $claim['last_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= sanitize_output($claim['department_name'] ?? 'N/A') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= sanitize_output($claim['claim_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">₱<?= number_format((float)$claim['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-link text-decoration-none p-0" 
                                                    onclick="showFullDescription('<?= htmlspecialchars($claim['description'], ENT_QUOTES) ?>')">
                                                <?php 
                                                $desc = sanitize_output($claim['description']);
                                                echo strlen($desc) > 40 ? substr($desc, 0, 40) . '...' : $desc;
                                                ?>
                                                <i class="fas fa-eye ms-1"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success w-100" 
                                                    onclick="markAsPaid(<?= $claim['claim_id'] ?>)">
                                                <i class="fas fa-check-circle me-1"></i>Pay
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div id="selectedSummary" class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>No claims selected
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-primary" onclick="batchMarkAsPaid()" id="batchPayBtn" disabled>
                                    <i class="fas fa-check-double me-1"></i>Process Selected Payments
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Toggle select all
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.claim-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                if (cb.checked) {
                    cb.closest('tr').classList.add('selected');
                } else {
                    cb.closest('tr').classList.remove('selected');
                }
            });
            updateSelectedSummary();
        }

        // Update selected summary
        function updateSelectedSummary() {
            const selected = Array.from(document.querySelectorAll('.claim-checkbox:checked'));
            const count = selected.length;
            const total = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            const summaryDiv = document.getElementById('selectedSummary');
            const batchBtn = document.getElementById('batchPayBtn');
            
            // Update row highlighting
            document.querySelectorAll('.claim-checkbox').forEach(cb => {
                if (cb.checked) {
                    cb.closest('tr').classList.add('selected');
                } else {
                    cb.closest('tr').classList.remove('selected');
                }
            });
            
            if (count > 0) {
                summaryDiv.innerHTML = `
                    <i class="fas fa-check-circle text-success me-1"></i>
                    <strong>${count}</strong> claim(s) selected | 
                    Total: <strong class="text-success">₱${total.toFixed(2)}</strong>
                `;
                batchBtn.disabled = false;
            } else {
                summaryDiv.innerHTML = '<i class="fas fa-info-circle me-1"></i>No claims selected';
                batchBtn.disabled = true;
            }
        }

        function showFullDescription(description) {
            Swal.fire({
                title: 'Claim Description',
                html: `<div class="text-start p-3 bg-light rounded">${description}</div>`,
                icon: 'info',
                width: '600px',
                confirmButtonText: 'Close'
            });
        }

        async function markAsPaid(claimId) {
            const result = await Swal.fire({
                title: 'Mark as Paid?',
                html: `
                    <div class="text-start">
                        <p>Confirm that payment has been processed for this claim:</p>
                        <ul>
                            <li>Payment has been transferred to employee account</li>
                            <li>This action cannot be undone</li>
                            <li>Employee will be notified</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check me-1"></i>Yes, Mark as Paid',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                try {
                    // Show loading
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait while we process the payment',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const response = await axios.post('../../api/claims_reimbursement/mark_paid.php', {
                        claim_id: claimId
                    });
                    
                    if (response.data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Payment Processed!',
                            text: response.data.message,
                            confirmButtonColor: '#28a745',
                            timer: 2000
                        });
                        location.reload();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to process payment. Please try again.', 'error');
                }
            }
        }

        async function batchMarkAsPaid() {
            const selected = Array.from(document.querySelectorAll('.claim-checkbox:checked'))
                .map(cb => cb.value);

            if (selected.length === 0) {
                Swal.fire('Error', 'Please select at least one claim', 'warning');
                return;
            }

            const total = Array.from(document.querySelectorAll('.claim-checkbox:checked'))
                .reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);

            const result = await Swal.fire({
                title: 'Process Multiple Payments?',
                html: `
                    <div class="text-start">
                        <p>You are about to process the following payments:</p>
                        <div class="alert alert-info">
                            <strong>${selected.length}</strong> claim(s)<br>
                            <strong>Total Amount: ₱${total.toFixed(2)}</strong>
                        </div>
                        <p>Please confirm:</p>
                        <ul>
                            <li>All payments have been transferred</li>
                            <li>This action cannot be undone</li>
                            <li>Employees will be notified</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check-double me-1"></i>Yes, Process All',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                // Show progress
                let processed = 0;
                let failed = 0;

                Swal.fire({
                    title: 'Processing Payments...',
                    html: `
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="progressBar">0%</div>
                        </div>
                        <div id="progressText">Processing 0 of ${selected.length} claims...</div>
                    `,
                    allowOutsideClick: false,
                    showConfirmButton: false
                });

                for (let i = 0; i < selected.length; i++) {
                    const claimId = selected[i];
                    
                    try {
                        const response = await axios.post('../../api/claims_reimbursement/mark_paid.php', {
                            claim_id: claimId
                        });
                        
                        if (response.data.success) {
                            processed++;
                        } else {
                            failed++;
                        }
                    } catch (error) {
                        console.error('Failed to process claim:', claimId);
                        failed++;
                    }

                    // Update progress
                    const progress = ((i + 1) / selected.length) * 100;
                    document.getElementById('progressBar').style.width = progress + '%';
                    document.getElementById('progressBar').textContent = Math.round(progress) + '%';
                    document.getElementById('progressText').textContent = 
                        `Processing ${i + 1} of ${selected.length} claims...`;
                }

                // Show final result
                await Swal.fire({
                    icon: failed === 0 ? 'success' : 'warning',
                    title: 'Processing Complete',
                    html: `
                        <div class="text-start">
                            <p><strong>Summary:</strong></p>
                            <ul>
                                <li class="text-success"><i class="fas fa-check-circle me-1"></i>Successfully processed: ${processed}</li>
                                ${failed > 0 ? `<li class="text-danger"><i class="fas fa-times-circle me-1"></i>Failed: ${failed}</li>` : ''}
                            </ul>
                        </div>
                    `,
                    confirmButtonColor: '#28a745'
                });

                location.reload();
            }
        }

        function processAllSelected() {
            batchMarkAsPaid();
        }

        function exportToExcel() {
            Swal.fire({
                title: 'Export Options',
                html: `
                    <div class="text-start">
                        <p>Choose export format:</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="generateExport('excel')">
                                <i class="fas fa-file-excel me-2"></i>Export to Excel
                            </button>
                            <button class="btn btn-danger" onclick="generateExport('pdf')">
                                <i class="fas fa-file-pdf me-2"></i>Export to PDF
                            </button>
                            <button class="btn btn-primary" onclick="generateExport('csv')">
                                <i class="fas fa-file-csv me-2"></i>Export to CSV
                            </button>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Close'
            });
        }

        function generateExport(format) {
            Swal.fire({
                icon: 'info',
                title: 'Export Feature',
                text: `${format.toUpperCase()} export functionality would be implemented here. This would generate a downloadable file with all approved claims data.`,
                confirmButtonText: 'OK'
            });
        }

        // Initialize tooltips if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to rows
            document.querySelectorAll('.claim-row').forEach(row => {
                const checkbox = row.querySelector('.claim-checkbox');
                row.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                        checkbox.checked = !checkbox.checked;
                        updateSelectedSummary();
                    }
                });
            });
        });
    </script>
</body>
</html>