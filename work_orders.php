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
$tenders = readJSON('departments/' . $deptId . '/data/tenders.json') ?? [];

$message = '';
$error = '';

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_wo'])) {
    $work_name = trim($_POST['work_name'] ?? '');
    $contractor_id = $_POST['contractor_id'] ?? '';
    $tender_id = $_POST['tender_id'] ?? '';
    $agreed_amount = $_POST['agreed_amount'] ?? '';
    $date_of_start = $_POST['date_of_start'] ?? '';
    $time_allowed = $_POST['time_allowed'] ?? '';

    if (empty($work_name) || empty($contractor_id) || empty($agreed_amount)) {
        $error = "Work Name, Contractor, and Amount are required.";
    } else {
        // Generate IDs
        $wo_unique_id = 'WO_AUTO_' . strtoupper(bin2hex(random_bytes(3)));
        $wo_number = getNextID($deptId, 'wo');

        $newWO = [
            'wo_unique_id' => $wo_unique_id,
            'wo_number' => $wo_number,
            'tender_id' => $tender_id,
            'contractor_id' => $contractor_id,
            'work_name' => $work_name,
            'agreed_amount' => $agreed_amount,
            'date_of_start' => $date_of_start,
            'time_allowed' => $time_allowed,
            'status' => 'Issued',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ];

        // Key by Unique ID
        $workOrders[$wo_unique_id] = $newWO;

        if (writeJSON($woPath, $workOrders)) {
            $message = "Work Order issued successfully: $wo_number";
        } else {
            $error = "Failed to save Work Order.";
        }
    }
}

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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Work Orders</h1>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Create Panel -->
        <div class="create-panel" style="background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2>Issue New Work Order</h2>
            <form method="POST" action="">
                <input type="hidden" name="create_wo" value="1">

                <div class="form-group">
                    <label>Work Name</label>
                    <input type="text" name="work_name" required placeholder="e.g. Repair of Road X">
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>Select Contractor</label>
                        <select name="contractor_id" required>
                            <option value="">-- Choose Contractor --</option>
                            <?php foreach ($contractors as $id => $c): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Link Tender (Optional)</label>
                        <select name="tender_id">
                            <option value="">-- None --</option>
                            <?php foreach ($tenders as $id => $t): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>">
                                    <?php echo htmlspecialchars($t['title'] . ' (' . $id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>Agreed Amount (₹)</label>
                        <input type="number" step="0.01" name="agreed_amount" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Date of Start</label>
                        <input type="date" name="date_of_start">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time Allowed</label>
                        <input type="text" name="time_allowed" placeholder="e.g. 6 Months">
                    </div>
                </div>

                <button type="submit" class="btn-primary">Issue Work Order</button>
            </form>
        </div>

        <!-- List -->
        <h2>Issued Work Orders</h2>
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
                            Start: <?php echo htmlspecialchars($wo['date_of_start']); ?><br>
                            Duration: <?php echo htmlspecialchars($wo['time_allowed']); ?>
                        </div>
                        <?php if(!empty($wo['tender_id'])): ?>
                        <div class="detail-item">
                            <strong>Linked Tender</strong>
                            <?php echo htmlspecialchars($wo['tender_id']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
