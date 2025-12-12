<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];

// Load Dependencies
$contractorsPath = 'departments/' . $deptId . '/data/contractors.json';
$contractors = readJSON($contractorsPath) ?? [];

$tendersPath = 'departments/' . $deptId . '/data/tenders.json';
$tenders = readJSON($tendersPath) ?? [];

$settingsPath = 'departments/' . $deptId . '/data/settings.json';
$settings = readJSON($settingsPath) ?? [];

// Pre-fill Data
$tenderId = $_GET['tender_id'] ?? '';
$contractorId = $_GET['contractor_id'] ?? '';

$prefillWorkName = '';
$prefillAmount = '';

if ($tenderId && isset($tenders[$tenderId])) {
    $prefillWorkName = $tenders[$tenderId]['title'];
    // Try to find the contractor's bid amount if available?
    // The prompt says "Agreed Amount: (Number - Important: This may differ from Tender Estimate)".
    // So we might not auto-fill amount, or maybe we leave it blank.
    // Tenders data usually has participants.
    if ($contractorId) {
        foreach ($tenders[$tenderId]['participants'] as $p) {
            if ($p['contractor_id'] === $contractorId) {
                // If we had bid amount we could fill it. For now leaving blank or using estimate?
                // Prompt doesn't say to auto-fill amount from bid, just says "Agreed Amount... may differ".
                break;
            }
        }
    }
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source = $_POST['source'] ?? ''; // 'tender' or 'none'
    $contractor_id = $_POST['contractor_id'] ?? '';
    $work_name = trim($_POST['work_name'] ?? '');
    $agreed_amount = $_POST['agreed_amount'] ?? '';
    $time_completion = $_POST['time_completion'] ?? '';
    $date_issue = $_POST['date_issue'] ?? '';
    $tender_id_linked = ($source === 'tender') ? ($_POST['tender_id'] ?? '') : '';

    if (empty($work_name) || empty($contractor_id) || empty($agreed_amount) || empty($date_issue)) {
        $error = "Please fill in all required fields.";
    } else {
        // 1. Generate Number
        // Reload settings to ensure atomic-ish counter
        $settings = readJSON($settingsPath) ?? [];
        $prefix = $settings['wo_prefix'] ?? 'WO/';
        $counter = $settings['wo_counter'] ?? 1;

        $wo_number = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);

        // 2. Increment Counter
        $settings['wo_counter'] = $counter + 1;
        writeJSON($settingsPath, $settings);

        // 3. Save to work_orders.json
        $woPath = 'departments/' . $deptId . '/data/work_orders.json';
        $workOrders = readJSON($woPath) ?? [];

        $wo_unique_id = 'WO_AUTO_' . strtoupper(bin2hex(random_bytes(3)));

        $newWO = [
            'wo_unique_id' => $wo_unique_id,
            'wo_number' => $wo_number,
            'tender_id' => $tender_id_linked,
            'contractor_id' => $contractor_id,
            'work_name' => $work_name,
            'agreed_amount' => $agreed_amount,
            'time_completion' => $time_completion,
            'date_issue' => $date_issue,
            'status' => 'Issued',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ];

        // Key by Unique ID or Number? Prompt says "List View: WO Number...".
        // Using Unique ID as key is safer.
        $workOrders[$wo_unique_id] = $newWO;
        writeJSON($woPath, $workOrders);

        // 4. Update Tender if linked
        if ($tender_id_linked && isset($tenders[$tender_id_linked])) {
            $updated = false;
            foreach ($tenders[$tender_id_linked]['participants'] as &$p) {
                if ($p['contractor_id'] === $contractor_id) {
                    $p['work_order_issued'] = true;
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                writeJSON($tendersPath, $tenders);
            }
        }

        // Redirect
        header("Location: work_orders.php?msg=created");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Work Order - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            background: white;
            padding: 2rem;
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-col { flex: 1; min-width: 200px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <a href="work_orders.php" class="back-link">&larr; Back to Dashboard</a>
            <h1>Create Work Order</h1>
        </div>
    </div>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Source</label>
                <select name="source" id="source" onchange="toggleSource()">
                    <option value="none">Direct (No Tender)</option>
                    <option value="tender" <?php if($tenderId) echo 'selected'; ?>>From Tender</option>
                </select>
            </div>

            <!-- Tender Selection (Visible if Source is Tender) -->
            <div class="form-group" id="tender_group" style="display: <?php echo $tenderId ? 'block' : 'none'; ?>;">
                <label>Select Tender</label>
                <select name="tender_id" id="tender_id" onchange="loadTenderDetails()">
                    <option value="">-- Choose Tender --</option>
                    <?php foreach ($tenders as $id => $t): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php if($tenderId === $id) echo 'selected'; ?> data-title="<?php echo htmlspecialchars($t['title']); ?>">
                            <?php echo htmlspecialchars($t['title'] . ' (' . $id . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Contractor</label>
                <select name="contractor_id" id="contractor_id" required>
                    <option value="">-- Choose Contractor --</option>
                    <?php foreach ($contractors as $id => $c): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php if($contractorId === $id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Work Name</label>
                <input type="text" name="work_name" id="work_name" value="<?php echo htmlspecialchars($prefillWorkName); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Agreed Amount (₹)</label>
                    <input type="number" step="0.01" name="agreed_amount" required>
                </div>
                <div class="form-col">
                    <label>Date of Issue</label>
                    <input type="date" name="date_issue" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Time of Completion</label>
                <input type="text" name="time_completion" placeholder="e.g. 6 Months" required>
            </div>

            <button type="submit" class="btn-primary">Create Work Order</button>
        </form>
    </div>

    <script>
        function toggleSource() {
            var source = document.getElementById('source').value;
            var tenderGroup = document.getElementById('tender_group');
            if (source === 'tender') {
                tenderGroup.style.display = 'block';
            } else {
                tenderGroup.style.display = 'none';
                // Clear Tender ID if switching to None
                document.getElementById('tender_id').value = '';
            }
        }

        function loadTenderDetails() {
            var select = document.getElementById('tender_id');
            var selectedOption = select.options[select.selectedIndex];
            var title = selectedOption.getAttribute('data-title');

            if (title) {
                document.getElementById('work_name').value = title;
            }
        }
    </script>
</body>
</html>
