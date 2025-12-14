<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$wo_id = $_GET['id'] ?? '';

if (!$wo_id) {
    die("Invalid Work Order ID");
}

$woPath = 'departments/' . $deptId . '/data/work_orders.json';
$contractorsPath = 'departments/' . $deptId . '/data/contractors.json';

$workOrders = readJSON($woPath) ?? [];
$contractors = readJSON($contractorsPath) ?? [];

if (!isset($workOrders[$wo_id])) {
    die("Work Order not found.");
}

$wo = $workOrders[$wo_id];
$contractorName = "Unknown Contractor";
if (!empty($wo['contractor_id']) && isset($contractors[$wo['contractor_id']])) {
    $contractorName = $contractors[$wo['contractor_id']]['name'];
}

// Fetch Inspection Officers (JE/AE/Consultant)
$deptRoles = getRoles($deptId);
$deptUsers = getUsers($deptId);
$inspectionOfficers = [];
foreach ($deptUsers as $uid => $u) {
    if (isset($u['status']) && $u['status'] !== 'active') continue;
    $rid = $u['role'] ?? '';
    if (isset($deptRoles[$rid])) {
        $rName = $deptRoles[$rid]['name'];
        if (preg_match('/(Engineer|Junior|Assistant|JE|AE|Consultant)/i', $rName)) {
            $inspectionOfficers[$uid] = $u['full_name'] . " (" . $rName . ")";
        }
    }
}
// Add a dummy External Consultant if list is empty for testing or fallback
if (empty($inspectionOfficers)) {
    $inspectionOfficers['external'] = "External Consultant";
}

$message = '';
$error = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_agreement') {
        $status = isset($_POST['agreement_status']) ? 'Signed' : 'Pending';
        $date = $_POST['agreement_date'] ?? '';

        $wo['agreement']['status'] = $status;
        // If toggled to Pending, maybe clear date? Or keep it.
        if ($status === 'Signed') {
            $wo['agreement']['signed_date'] = $date;
        }

        $workOrders[$wo_id] = $wo;
        writeJSON($woPath, $workOrders);
        $message = "Agreement updated successfully.";

    } elseif ($action === 'add_bg') {
        $ref = $_POST['bg_ref'] ?? '';
        $amount = $_POST['bg_amount'] ?? '';
        $expiry = $_POST['bg_expiry'] ?? '';

        if ($ref && $amount && $expiry) {
            $wo['bank_guarantees'][] = [
                'ref_no' => $ref,
                'amount' => $amount,
                'expiry_date' => $expiry,
                'added_at' => date('Y-m-d H:i:s'),
                'status' => 'Active' // Manual add by admin is Active immediately
            ];
            $workOrders[$wo_id] = $wo;
            writeJSON($woPath, $workOrders);
            $message = "Bank Guarantee added successfully.";
        } else {
            $error = "All fields are required for Bank Guarantee.";
        }

    } elseif ($action === 'approve_bg') {
        $bgIndex = $_POST['bg_index'] ?? -1;
        if (isset($wo['bank_guarantees'][$bgIndex])) {
            $wo['bank_guarantees'][$bgIndex]['status'] = 'Active';
            $workOrders[$wo_id] = $wo;
            writeJSON($woPath, $workOrders);
            $message = "Bank Guarantee approved.";
        }
    } elseif ($action === 'complete_milestone') {
        $stage = $_POST['stage'] ?? '';
        if ($stage && isset($wo['milestone_status'][$stage])) {
            $wo['milestone_status'][$stage] = 'Completed';
            $workOrders[$wo_id] = $wo;
            writeJSON($woPath, $workOrders);
            $message = "Milestone marked as completed.";
        }
    }
}

// Calculations for Delay
$agreementDate = $wo['agreement']['signed_date'] ?? '';
$timeAllowed = $wo['agreement']['time_allowed'] ?? '';
$targetDateStr = '-';
$delayDays = 0;
$progressPercent = 0;
$isDelayed = false;
$daysLeft = 0;

if ($agreementDate && $wo['agreement']['status'] === 'Signed') {
    $durationStr = strtolower($timeAllowed);
    try {
        $startDate = new DateTime($agreementDate);
        $endDate = clone $startDate;

        // Simple Parser
        if (strpos($durationStr, 'month') !== false) {
            $months = (int)filter_var($durationStr, FILTER_SANITIZE_NUMBER_INT);
            $endDate->modify("+$months months");
        } elseif (strpos($durationStr, 'day') !== false) {
            $days = (int)filter_var($durationStr, FILTER_SANITIZE_NUMBER_INT);
            $endDate->modify("+$days days");
        } elseif (strpos($durationStr, 'year') !== false) {
            $years = (int)filter_var($durationStr, FILTER_SANITIZE_NUMBER_INT);
            $endDate->modify("+$years years");
        } else {
            if (is_numeric(trim($durationStr))) {
                $months = (int)trim($durationStr);
                $endDate->modify("+$months months");
            }
        }

        $targetDateStr = $endDate->format('d M Y');
        $today = new DateTime();
        $today->setTime(0,0,0);
        $startDate->setTime(0,0,0);
        $endDate->setTime(0,0,0);

        $totalInterval = $startDate->diff($endDate)->days;
        if ($totalInterval == 0) $totalInterval = 1;

        if ($today > $endDate) {
            $isDelayed = true;
            $delayDays = $endDate->diff($today)->days;
            $progressPercent = 100;
        } else {
            if ($today < $startDate) {
                $progressPercent = 0;
                $daysLeft = $startDate->diff($endDate)->days;
            } else {
                $elapsed = $startDate->diff($today)->days;
                $progressPercent = min(100, ($elapsed / $totalInterval) * 100);
                $daysLeft = $today->diff($endDate)->days;
            }
        }
    } catch (Exception $e) {
        $targetDateStr = "Invalid Duration";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Cockpit - <?php echo htmlspecialchars($wo['wo_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .cockpit-container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .cockpit-header { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header-info h1 { margin: 0; font-size: 1.5rem; color: #2c3e50; }
        .header-info p { margin: 0.5rem 0 0; color: #7f8c8d; }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; text-transform: uppercase; font-size: 0.9rem; }
        .status-Issued { background: #e3f2fd; color: #1976d2; }
        .status-Active { background: #e8f5e9; color: #2e7d32; }
        .cockpit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .cockpit-grid { grid-template-columns: 1fr; } }
        .panel-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1.5rem; height: 100%; }
        .panel-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; border-bottom: 2px solid #f0f2f5; padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .agreement-box { background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 2rem; border-left: 4px solid #3498db; }
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 24px; vertical-align: middle; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #2196F3; }
        input:checked + .slider:before { transform: translateX(26px); }
        .bg-item { background: #fff; border: 1px solid #e0e0e0; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; position: relative; }
        .bg-warning { position: absolute; top: -10px; right: -10px; background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .bg-pending { border: 2px solid #ff9800; background: #fff3e0; }
        .delay-calc-box { text-align: center; margin-bottom: 2rem; background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #eee; }
        .progress-container { width: 100%; background-color: #e0e0e0; border-radius: 10px; height: 20px; margin: 1rem 0; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 10px; text-align: center; line-height: 20px; color: white; font-size: 0.8rem; transition: width 0.5s ease; }
        .progress-normal { background-color: #4caf50; }
        .progress-delayed { background-color: #f44336; }
        .delay-text { color: #d32f2f; font-weight: bold; margin-top: 0.5rem; font-size: 1.1rem; }
        .milestone-item { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem; border-bottom: 1px solid #f0f0f0; }
        .milestone-item:last-child { border-bottom: none; }
        .ms-status { font-size: 0.85rem; padding: 2px 8px; border-radius: 4px; }
        .ms-Pending { background: #fff3e0; color: #ef6c00; }
        .ms-Completed { background: #e8f5e9; color: #2e7d32; }
        .ms-Inspection_Requested { background: #e3f2fd; color: #1976d2; border: 1px solid #2196F3; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px; }
        .close { float: right; font-size: 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="cockpit-container">
        <?php if ($message): ?>
            <div class="success-message" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="cockpit-header">
            <div class="header-info">
                <h1>Work Order #<?php echo htmlspecialchars($wo['wo_number']); ?></h1>
                <p><strong>Contractor:</strong> <?php echo htmlspecialchars($contractorName); ?></p>
                <p style="font-size: 0.9rem; margin-top: 0.2rem;">
                    <?php echo htmlspecialchars($wo['work_name']); ?> | ₹<?php echo number_format((float)$wo['agreed_amount'], 2); ?>
                </p>
            </div>
            <div class="status-badge status-<?php echo htmlspecialchars($wo['status']); ?>">
                <?php echo htmlspecialchars($wo['status']); ?>
            </div>
        </div>

        <div class="cockpit-grid">
            <!-- Left Panel: Legal Wallet -->
            <div class="panel-card">
                <div class="panel-title">Legal Wallet</div>

                <!-- Agreement Section -->
                <div class="agreement-box">
                    <h3 style="margin-top:0;">Agreement</h3>

                    <?php if (($wo['agreement']['status'] ?? '') === 'Submitted_Pending_Approval'): ?>
                         <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-bottom: 10px; border: 1px solid #ffeeba;">
                            <strong>Action Required:</strong> Contractor has submitted signed agreement.
                            <br>
                            <?php if (!empty($wo['agreement']['file_path'])): ?>
                                <a href="view_attachment.php?path=<?php echo urlencode($wo['agreement']['file_path']); ?>" target="_blank" class="btn-small" style="margin-top:5px; display:inline-block;">View PDF</a>
                            <?php endif; ?>
                         </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_agreement">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                            <label>Status: Signed?</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="agreement_status" value="Signed" id="statusToggle"
                                    <?php echo ($wo['agreement']['status'] === 'Signed') ? 'checked' : ''; ?>
                                    onchange="toggleDateInput()">
                                <span class="slider"></span>
                            </label>
                        </div>

                         <?php if (($wo['agreement']['status'] ?? '') === 'Submitted_Pending_Approval'): ?>
                             <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #555;">
                                 (Approving this will lock the status as Signed)
                             </div>
                         <?php endif; ?>

                        <div id="dateInputContainer" style="margin-bottom: 1rem; display: <?php echo ($wo['agreement']['status'] === 'Signed') ? 'block' : 'none'; ?>;">
                            <label>Date of Agreement</label>
                            <input type="date" name="agreement_date" value="<?php echo htmlspecialchars($wo['agreement']['signed_date'] ?: date('Y-m-d')); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <button type="submit" class="btn-small btn-primary">
                            <?php echo ($wo['agreement']['status'] === 'Submitted_Pending_Approval') ? 'Approve & Lock' : 'Save Agreement'; ?>
                        </button>
                    </form>
                </div>

                <!-- Instrument Wallet (Bank Guarantees) -->
                <div class="panel-title" style="font-size: 1rem; margin-top: 2rem;">
                    Instrument Wallet (BG/FDR)
                    <button class="btn-small" onclick="openModal()">+ Add Manual</button>
                </div>

                <div class="bg-list">
                    <?php if (empty($wo['bank_guarantees'])): ?>
                        <p style="color: #999; font-style: italic;">No Bank Guarantees added.</p>
                    <?php else: ?>
                        <?php foreach ($wo['bank_guarantees'] as $idx => $bg): ?>
                            <?php
                                $status = $bg['status'] ?? 'Active';
                                $isPending = ($status === 'Pending');
                                $isExpiring = false;
                                $badgeText = "";
                                $expiry = new DateTime($bg['expiry_date']);
                                $now = new DateTime();
                                $diff = $now->diff($expiry);
                                if ($expiry < $now) {
                                    $isExpiring = true;
                                    $badgeText = "EXPIRED";
                                } elseif ($diff->days <= 30 && $diff->invert == 0) {
                                    $isExpiring = true;
                                    $badgeText = "Expiring Soon";
                                }
                            ?>
                            <div class="bg-item <?php echo $isPending ? 'bg-pending' : ''; ?>">
                                <?php if ($isExpiring && !$isPending): ?>
                                    <div class="bg-warning"><?php echo $badgeText; ?></div>
                                <?php endif; ?>
                                <?php if ($isPending): ?>
                                    <div class="bg-warning" style="background: #ff9800;">PENDING APPROVAL</div>
                                <?php endif; ?>

                                <div>
                                    <strong>Ref:</strong> <?php echo htmlspecialchars($bg['ref_no']); ?>
                                    <?php if (!empty($bg['file_path'])): ?>
                                        <a href="view_attachment.php?path=<?php echo urlencode($bg['file_path']); ?>" target="_blank" style="margin-left: 5px; font-size: 0.85rem;">[View File]</a>
                                    <?php endif; ?>
                                </div>
                                <div><strong>Amount:</strong> ₹<?php echo number_format((float)$bg['amount'], 2); ?></div>
                                <div style="color: <?php echo $isExpiring ? '#c0392b' : '#7f8c8d'; ?>;">
                                    <strong>Expiry:</strong> <?php echo htmlspecialchars($bg['expiry_date']); ?>
                                </div>

                                <?php if ($isPending): ?>
                                    <form method="POST" style="margin-top: 5px;">
                                        <input type="hidden" name="action" value="approve_bg">
                                        <input type="hidden" name="bg_index" value="<?php echo $idx; ?>">
                                        <button type="submit" class="btn-small btn-primary">Approve</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Panel: Execution Monitor -->
            <div class="panel-card">
                <div class="panel-title">Execution Monitor</div>

                <!-- Time Tracking / Delay Calculator -->
                <div class="delay-calc-box">
                    <h3 style="margin-top: 0;">Time Tracking</h3>
                    <?php if ($wo['agreement']['status'] !== 'Signed'): ?>
                        <p style="color: #7f8c8d;">Waiting for Agreement Sign-off to start tracking.</p>
                    <?php else: ?>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #555;">
                            <span>Start: <?php echo htmlspecialchars($wo['agreement']['signed_date']); ?></span>
                            <span>Target: <?php echo htmlspecialchars($targetDateStr); ?></span>
                        </div>

                        <div class="progress-container">
                            <div class="progress-bar <?php echo $isDelayed ? 'progress-delayed' : 'progress-normal'; ?>"
                                 style="width: <?php echo $progressPercent; ?>%">
                                <?php echo $isDelayed ? 'Overdue' : round($progressPercent) . '%'; ?>
                            </div>
                        </div>

                        <?php if ($isDelayed): ?>
                            <div class="delay-text">DELAYED BY <?php echo $delayDays; ?> DAYS</div>
                        <?php else: ?>
                            <div style="color: #27ae60; font-weight: bold;"><?php echo $daysLeft; ?> Days Remaining</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Payment Milestones -->
                <div class="panel-title" style="font-size: 1rem;">Payment Milestones</div>

                <div class="milestones-list">
                    <?php
                    $paymentTerms = $wo['payment_terms'] ?? [];
                    $milestoneStatus = $wo['milestone_status'] ?? [];
                    if (empty($paymentTerms)): ?>
                        <p style="color: #999;">No milestones defined.</p>
                    <?php else: ?>
                        <?php foreach ($paymentTerms as $term): ?>
                            <?php
                                $stageName = $term['stage'];
                                $percent = $term['percent'];
                                $status = $milestoneStatus[$stageName] ?? 'Pending';
                                $isRequested = ($status === 'Inspection_Requested');
                            ?>
                            <div class="milestone-item" style="<?php echo $isRequested ? 'background: #f0f8ff;' : ''; ?>">
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($stageName); ?></div>
                                    <div style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($percent); ?>% Payment</div>
                                </div>
                                <div>
                                    <?php if ($isRequested): ?>
                                         <div style="display:flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                             <span class="ms-status ms-Inspection_Requested">⚠ Inspection Req.</span>
                                             <button onclick="openInspectionModal('<?php echo htmlspecialchars($stageName); ?>')" class="btn-small btn-primary">Generate Letter</button>
                                         </div>
                                    <?php elseif ($status === 'Pending'): ?>
                                        <div style="display:flex; gap:5px;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="complete_milestone">
                                                <input type="hidden" name="stage" value="<?php echo htmlspecialchars($stageName); ?>">
                                                <button type="submit" class="btn-small btn-secondary" title="Mark as Completed">✓ Done</button>
                                            </form>
                                            <button onclick="openInspectionModal('<?php echo htmlspecialchars($stageName); ?>')" class="btn-small" style="background: #8e44ad; color: white;">Req. Insp.</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="ms-status ms-Completed">✓ Completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Inspection Request Modal -->
    <div id="inspectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeInspectionModal()">&times;</span>
            <h2>Call for Inspection</h2>
            <form action="trigger_inspection.php" method="POST">
                <input type="hidden" name="wo_id" value="<?php echo htmlspecialchars($wo_id); ?>">
                <input type="hidden" name="stage" id="insp_stage">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Stage</label>
                    <input type="text" id="display_stage" readonly style="width: 100%; background: #eee; padding: 8px; border: 1px solid #ddd;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Select Officer</label>
                    <select name="officer_id" required style="width: 100%; padding: 8px;">
                        <option value="">-- Select Officer --</option>
                        <?php foreach ($inspectionOfficers as $uid => $label): ?>
                            <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Ready for Inspection On</label>
                    <input type="date" name="inspection_date" required min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px;">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;">Generate Letter</button>
            </form>
        </div>
    </div>

    <!-- Add BG Modal -->
    <div id="bgModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Add Bank Guarantee</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_bg">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Reference Number</label>
                    <input type="text" name="bg_ref" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Amount (₹)</label>
                    <input type="number" step="0.01" name="bg_amount" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Expiry Date</label>
                    <input type="date" name="bg_expiry" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%;">Add Instrument</button>
            </form>
        </div>
    </div>

    <script>
        function toggleDateInput() {
            var checkbox = document.getElementById('statusToggle');
            var container = document.getElementById('dateInputContainer');
            if (checkbox.checked) {
                container.style.display = 'block';
                // optional: set required if visible
                container.querySelector('input').required = true;
            } else {
                container.style.display = 'none';
                container.querySelector('input').required = false;
            }
        }

        function openModal() {
            document.getElementById('bgModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('bgModal').style.display = 'none';
        }

        function openInspectionModal(stage) {
            document.getElementById('insp_stage').value = stage;
            document.getElementById('display_stage').value = stage;
            document.getElementById('inspectionModal').style.display = 'block';
        }

        function closeInspectionModal() {
            document.getElementById('inspectionModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('bgModal');
            var inspModal = document.getElementById('inspectionModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
            if (event.target == inspModal) {
                inspModal.style.display = "none";
            }
        }

        // Initial check
        toggleDateInput();
    </script>
</body>
</html>
