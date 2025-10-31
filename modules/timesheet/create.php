<?php
/**
 * Create Timesheet Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../models/BaseModel.php';
require_once __DIR__ . '/../../models/Timesheet.php';

use HR3\Config\Auth;

Auth::requireAuth();

$pageTitle = "Create Timesheet";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_output($pageTitle) ?> - HR3</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Create Timesheet</h1>
                    <a href="<?= base_url() ?>/modules/timesheet/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="timesheetForm">
                            <!-- Week Period -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Week Start Date</label>
                                    <input type="date" class="form-control" id="week_start" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Week End Date</label>
                                    <input type="date" class="form-control" id="week_end" required>
                                </div>
                            </div>

                            <!-- Entries Table -->
                            <h5 class="mb-3">Time Entries</h5>
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered" id="entriesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Project</th>
                                            <th>Task Description</th>
                                            <th>Hours</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="entriesBody">
                                        <!-- Entries will be added here -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total Hours:</strong></td>
                                            <td><strong id="totalHours">0.00</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <button type="button" class="btn btn-outline-primary mb-3" onclick="addEntry()">
                                <i class="fas fa-plus me-1"></i>Add Entry
                            </button>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Submit Timesheet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let entryCount = 0;

        function addEntry() {
            const tbody = document.getElementById('entriesBody');
            const row = document.createElement('tr');
            row.id = `entry_${entryCount}`;
            row.innerHTML = `
                <td><input type="date" class="form-control form-control-sm" name="date_${entryCount}" required></td>
                <td><input type="text" class="form-control form-control-sm" name="project_${entryCount}" required></td>
                <td><textarea class="form-control form-control-sm" name="task_${entryCount}" rows="1" required></textarea></td>
                <td><input type="number" class="form-control form-control-sm" name="hours_${entryCount}" step="0.25" min="0" max="24" onchange="calculateTotal()" required></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeEntry(${entryCount})"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
            entryCount++;
        }

        function removeEntry(id) {
            document.getElementById(`entry_${id}`).remove();
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('[name^="hours_"]').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalHours').textContent = total.toFixed(2);
        }

        document.getElementById('timesheetForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const weekStart = document.getElementById('week_start').value;
            const weekEnd = document.getElementById('week_end').value;
            
            const entries = [];
            for (let i = 0; i < entryCount; i++) {
                const dateInput = document.querySelector(`[name="date_${i}"]`);
                if (dateInput) {
                    entries.push({
                        work_date: dateInput.value,
                        project_name: document.querySelector(`[name="project_${i}"]`).value,
                        task_description: document.querySelector(`[name="task_${i}"]`).value,
                        hours_worked: parseFloat(document.querySelector(`[name="hours_${i}"]`).value)
                    });
                }
            }
            
            if (entries.length === 0) {
                alert('Please add at least one entry.');
                return;
            }
            
            const formData = new FormData();
            formData.append('week_start', weekStart);
            formData.append('week_end', weekEnd);
            formData.append('entries', JSON.stringify(entries));
            
            fetch('<?= base_url() ?>/api/timesheet/?action=create', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.href = '<?= base_url() ?>/modules/timesheet/index.php';
                }
            })
            .catch(err => alert('Error: ' + err));
        });

        // Add initial entry
        addEntry();
    </script>
</body>
</html>