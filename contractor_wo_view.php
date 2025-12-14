<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor') {
    header("Location: contractor_login.php");
    exit;
}

$deptId = $_SESSION['dept_id'];
$contractorId = $_SESSION['user_id'];
$woId = $_GET['id'] ?? '';

if (empty($woId)) {
    die("Invalid Work Order ID.");
}

// Data Paths
$workOrdersPath = 'departments/' . $deptId . '/data/work_orders.json';
$workOrders = readJSON($workOrdersPath) ?? [];

if (!isset($workOrders[$woId])) {
    die("Work Order not found.");
}

$wo = $workOrders[$woId];

// Verify Ownership
if (($wo['contractor_id'] ?? '') !== $contractorId) {
    die("Access Denied.");
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Fetch Linked Documents
$documents = [];
$fileId = $wo['file_id'] ?? '';
if ($fileId) {
    $docsPath = STORAGE_PATH . "/departments/$deptId/files/$fileId/documents/";
    // Use glob to find json files
    $files = glob($docsPath . "*.json");
    if ($files) {
        foreach ($files as $filepath) {
            $docData = json_decode(file_get_contents($filepath), true);
            if ($docData) {
                // Filter documents addressed to contractor
                $recipient = $docData['variables']['recipient_name'] ?? '';
                $isAddressedToContractor = false;

                if (stripos($recipient, $_SESSION['user_name']) !== false) {
                    $isAddressedToContractor = true;
                }

                $title = $docData['title'] ?? '';
                if (stripos($title, 'Work Order') !== false ||
                    stripos($title, 'Performance Guarantee') !== false ||
                    stripos($title, 'Agreement') !== false) {
                    $isAddressedToContractor = true;
                }

                if ($isAddressedToContractor) {
                    $documents[] = $docData;
                }
            }
        }
    }
}

$agreementStatus = $wo['agreement']['status'] ?? 'Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order Details - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .back-link { margin-bottom: 20px; display: inline-block; color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .detail-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .detail-section { margin-bottom: 2rem; }
        .detail-section h3 { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; color: #333; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .info-item label { display: block; color: #777; font-size: 14px; margin-bottom: 4px; }
        .info-item span { display: block; font-weight: 600; color: #333; font-size: 16px; }

        .milestone-track { margin-top: 10px; }
        .milestone { display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; padding: 10px 15px; border-radius: 4px; margin-bottom: 8px; border-left: 4px solid #ddd; }
        .milestone.completed { border-left-color: #4caf50; background: #e8f5e9; }
        .milestone.pending { border-left-color: #ff9800; background: #fff3e0; }
        .milestone.requested { border-left-color: #2196F3; background: #e3f2fd; }

        .doc-list { list-style: none; padding: 0; }
        .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; }
        .doc-item:last-child { border-bottom: none; }
        .doc-icon { margin-right: 10px; color: #666; }

        .upload-section { margin-top: 1rem; background: #f0f4f8; padding: 1rem; border-radius: 6px; }
        .form-row { display: flex; gap: 1rem; margin-bottom: 10px; flex-wrap: wrap; }
        .form-col { flex: 1; min-width: 150px; }

        .bg-list { margin-top: 1rem; }
        .bg-item { background: #fff; padding: 0.8rem; border: 1px solid #eee; margin-bottom: 0.5rem; border-radius: 4px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

    <div class="container">
        <a href="contractor_dashboard.php" class="back-link">&larr; Back to Dashboard</a>

        <?php if ($msg): ?>
            <div class="success-message" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="error-message" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($err); ?></div>
        <?php endif; ?>

        <div class="detail-card">
            <!-- Header Info -->
            <div class="detail-section">
                <h1><?php echo htmlspecialchars($wo['work_name']); ?></h1>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Work Order No</label>
                        <span><?php echo htmlspecialchars($woId); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status</label>
                        <span><?php echo htmlspecialchars($wo['status']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Agreed Amount</label>
                        <span>₹ <?php echo htmlspecialchars($wo['agreed_amount'] ?? '0'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Time Allowed</label>
                        <span><?php echo htmlspecialchars($wo['time_allowed'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Legal Actions Section -->
            <div class="detail-section">
                <h3>Legal Actions</h3>

                <!-- 1. Download Drafts -->
                <h4>1. Project Documents (Drafts)</h4>
                <?php if (empty($documents)): ?>
                    <p style="color:#777;">No documents available.</p>
                <?php else: ?>
                    <ul class="doc-list">
                        <?php foreach ($documents as $doc): ?>
                            <li class="doc-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($doc['title'] ?? 'Untitled'); ?></strong>
                                    <div style="font-size:12px; color:#777;">Ref: <?php echo htmlspecialchars($doc['ref_number'] ?? '-'); ?></div>
                                </div>
                                <a href="view_document.php?file_id=<?php echo $fileId; ?>&doc_id=<?php echo $doc['id']; ?>" class="btn-small" target="_blank">View / Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- 2. Upload Agreement -->
                <h4 style="margin-top: 1.5rem;">2. Submit Signed Agreement</h4>
                <div class="upload-section">
                    <?php if ($agreementStatus === 'Pending' || $agreementStatus === 'Correction'): ?>
                        <form action="contractor_action.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_agreement">
                            <input type="hidden" name="wo_id" value="<?php echo htmlspecialchars($woId); ?>">
                            <div class="form-row">
                                <div class="form-col">
                                    <label>Upload Signed Agreement (PDF)</label>
                                    <input type="file" name="agreement_file" accept=".pdf" required style="width:100%;">
                                </div>
                                <div class="form-col" style="display:flex; align-items:flex-end;">
                                    <button type="submit" class="btn-primary">Submit Signed Agreement</button>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($agreementStatus === 'Submitted_Pending_Approval'): ?>
                        <div class="status-badge status-warning" style="display:inline-block; background: #fff3cd; color: #856404; padding: 5px 10px; border-radius: 4px;">
                            Submitted - Pending Approval
                        </div>
                    <?php elseif ($agreementStatus === 'Signed'): ?>
                         <div class="status-badge status-success" style="display:inline-block; background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 4px;">
                            Agreement Signed & Active
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bank Guarantee Wallet -->
            <div class="detail-section">
                <h3>Bank Guarantee Wallet</h3>

                <div class="upload-section">
                    <h4>Add New BG / FDR</h4>
                    <form action="contractor_action.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_bg">
                        <input type="hidden" name="wo_id" value="<?php echo htmlspecialchars($woId); ?>">

                        <div class="form-row">
                            <div class="form-col">
                                <label>Ref No</label>
                                <input type="text" name="bg_ref" required placeholder="BG/FDR Number" style="width:100%; padding: 6px;">
                            </div>
                            <div class="form-col">
                                <label>Bank Name</label>
                                <input type="text" name="bg_bank" required placeholder="Issuing Bank" style="width:100%; padding: 6px;">
                            </div>
                        </div>
                        <div class="form-row">
                             <div class="form-col">
                                <label>Amount (₹)</label>
                                <input type="number" step="0.01" name="bg_amount" required placeholder="0.00" style="width:100%; padding: 6px;">
                            </div>
                            <div class="form-col">
                                <label>Expiry Date</label>
                                <input type="date" name="bg_expiry" required style="width:100%; padding: 6px;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <label>Upload Scan (PDF)</label>
                                <input type="file" name="bg_file" accept=".pdf" required style="width:100%;">
                            </div>
                            <div class="form-col" style="display:flex; align-items:flex-end;">
                                <button type="submit" class="btn-primary">Add to Wallet</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="bg-list">
                    <?php if (empty($wo['bank_guarantees'])): ?>
                         <p style="color:#777; font-style: italic;">No Bank Guarantees submitted yet.</p>
                    <?php else: ?>
                        <?php foreach ($wo['bank_guarantees'] as $bg): ?>
                            <div class="bg-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($bg['bank_name']); ?></strong> (<?php echo htmlspecialchars($bg['ref_no']); ?>)
                                    <br>
                                    <small>Amount: ₹<?php echo htmlspecialchars($bg['amount']); ?> | Expiry: <?php echo htmlspecialchars($bg['expiry_date']); ?></small>
                                </div>
                                <div class="status-badge">
                                    <?php echo htmlspecialchars($bg['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Milestone Tracker -->
            <div class="detail-section">
                <h3>Milestone Tracker</h3>
                <div class="milestone-track">
                    <?php
                        $milestones = $wo['milestone_status'] ?? [];
                        if (empty($milestones)) {
                            echo '<p style="color:#777;">No milestones defined.</p>';
                        } else {
                            foreach ($milestones as $name => $status) {
                                $isCompleted = (strtolower($status) === 'completed');
                                $isRequested = (strtolower($status) === 'inspection_requested');
                                $class = $isCompleted ? 'completed' : ($isRequested ? 'requested' : 'pending');

                                echo '<div class="milestone ' . $class . '">';
                                echo '<div>';
                                echo '<strong>' . htmlspecialchars(ucfirst($name)) . '</strong>';
                                echo '<div style="font-size: 0.8rem; color: #666;">Status: ' . htmlspecialchars($status) . '</div>';
                                echo '</div>';

                                if ($status === 'Pending') {
                                    echo '<form action="contractor_action.php" method="POST">';
                                    echo '<input type="hidden" name="action" value="request_inspection">';
                                    echo '<input type="hidden" name="wo_id" value="' . htmlspecialchars($woId) . '">';
                                    echo '<input type="hidden" name="stage" value="' . htmlspecialchars($name) . '">';
                                    echo '<button type="submit" class="btn-small" style="background:#2196F3; color:white;">Request Inspection</button>';
                                    echo '</form>';
                                } elseif ($isRequested) {
                                     echo '<span class="status-badge" style="background:#e3f2fd; color:#0d47a1;">Requested</span>';
                                } elseif ($isCompleted) {
                                    echo '<span class="status-badge" style="background:#e8f5e9; color:#1b5e20;">✓ Done</span>';
                                }

                                echo '</div>';
                            }
                        }
                    ?>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
