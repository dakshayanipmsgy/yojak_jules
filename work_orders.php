<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$woPath = 'departments/' . $deptId . '/data/work_orders.json';

// Initialize Work Orders Data if not exists
$workOrders = readJSON($woPath);
if ($workOrders === null) {
    $workOrders = [];
    writeJSON($woPath, []);
}

// Load Dependencies
$contractors = readJSON('departments/' . $deptId . '/data/contractors.json') ?? [];

// Prepare list
$woList = array_values($workOrders);
usort($woList, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Orders - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .wo-card {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 5px solid #0052cc;
        }
        .wo-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .wo-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #0052cc;
        }
        .wo-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
            color: #444;
        }
        .detail-item strong {
            display: block;
            color: #666;
            margin-bottom: 2px;
        }
        .actions {
            margin-top: 1rem;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Work Orders</h1>
        </div>
        <div class="header-right">
            <a href="create_work_order.php" class="btn-primary">Create Work Order</a>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
            <div class="success-message">Work Order created successfully.</div>
        <?php endif; ?>

        <!-- List -->
        <?php if (empty($woList)): ?>
            <p>No work orders issued.</p>
        <?php else: ?>
            <?php foreach ($woList as $wo): ?>
                <div class="wo-card">
                    <div class="wo-header">
                        <div>
                            <div class="wo-number"><?php echo htmlspecialchars($wo['wo_number']); ?></div>
                            <small>ID: <?php echo htmlspecialchars($wo['wo_unique_id']); ?></small>
                        </div>
                        <span style="background: #deebff; color: #0747a6; padding: 4px 8px; border-radius: 4px; font-weight: bold;"><?php echo htmlspecialchars($wo['status']); ?></span>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <h3 style="margin: 0; color: #333;"><?php echo htmlspecialchars($wo['work_name']); ?></h3>
                    </div>
                    <div class="wo-details">
                        <div class="detail-item">
                            <strong>Contractor</strong>
                            <?php
                                $cName = $contractors[$wo['contractor_id']]['name'] ?? $wo['contractor_id'];
                                echo htmlspecialchars($cName);
                            ?>
                        </div>
                        <div class="detail-item">
                            <strong>Amount</strong>
                            ₹<?php echo htmlspecialchars($wo['agreed_amount']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Timeline</strong>
                            Issue: <?php echo htmlspecialchars($wo['date_issue']); ?><br>
                            Duration: <?php echo htmlspecialchars($wo['time_completion']); ?>
                        </div>
                        <?php if(!empty($wo['tender_id'])): ?>
                        <div class="detail-item">
                            <strong>Linked Tender</strong>
                            <?php echo htmlspecialchars($wo['tender_id']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="actions">
                        <a href="print_work_order.php?id=<?php echo htmlspecialchars($wo['wo_unique_id']); ?>" target="_blank" class="btn-secondary">View/Print</a>
                        <!-- Edit could link to create_work_order.php?id=... if implemented later -->
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
