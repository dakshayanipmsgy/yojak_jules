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
                // Check recipient_name or context
                // Or maybe check if contractor is mentioned?
                // The prompt says: "List only the documents addressed to them (e.g., 'Work Order', 'Request for PG')"

                // Let's check variables for recipient_name
                // Also check template type?

                // Usually documents have 'recipient_name' in variables or meta.
                // Assuming standard template variables.

                $recipient = $docData['variables']['recipient_name'] ?? '';
                $isAddressedToContractor = false;

                // Check if recipient name matches contractor name (fuzzy match?) or just include specific types
                // Better: check if the document is meant for the contractor.
                // If the contractor's name is in recipient_name
                if (stripos($recipient, $_SESSION['user_name']) !== false) {
                    $isAddressedToContractor = true;
                }

                // Also include if it's a "Work Order" or "Request for PG" or "Agreement"
                // created for this work order.
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

        .doc-list { list-style: none; padding: 0; }
        .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; }
        .doc-item:last-child { border-bottom: none; }
        .doc-icon { margin-right: 10px; color: #666; }
    </style>
</head>
<body>

    <div class="container">
        <a href="contractor_dashboard.php" class="back-link">&larr; Back to Dashboard</a>

        <div class="detail-card">
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
                                $class = $isCompleted ? 'completed' : 'pending';
                                echo '<div class="milestone ' . $class . '">';
                                echo '<span>' . htmlspecialchars(ucfirst($name)) . '</span>';
                                echo '<span class="status-badge">' . htmlspecialchars($status) . '</span>';
                                echo '</div>';
                            }
                        }
                    ?>
                </div>
            </div>

            <div class="detail-section">
                <h3>Documents</h3>
                <?php if (empty($documents)): ?>
                    <p style="color:#777;">No documents available.</p>
                <?php else: ?>
                    <ul class="doc-list">
                        <?php foreach ($documents as $doc): ?>
                            <li class="doc-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($doc['title'] ?? 'Untitled'); ?></strong>
                                    <div style="font-size:12px; color:#777;">Ref: <?php echo htmlspecialchars($doc['ref_number'] ?? '-'); ?> | <?php echo htmlspecialchars($doc['creation_date'] ?? ''); ?></div>
                                </div>
                                <a href="view_document.php?file_id=<?php echo $fileId; ?>&doc_id=<?php echo $doc['id']; ?>" class="btn-small" target="_blank">View</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
