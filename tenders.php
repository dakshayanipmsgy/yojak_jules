<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$tendersPath = 'departments/' . $deptId . '/data/tenders.json';

// Initialize Tenders Data if not exists
$tenders = readJSON($tendersPath);
if ($tenders === null) {
    $tenders = [];
    // Ensure directory exists
    // writeJSON creates dirs
    writeJSON($tendersPath, []);
}

$message = '';
$error = '';

// Handle Create New Tender
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tender'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $work_description = trim($_POST['work_description'] ?? '');
    $estimated_cost = (float)($_POST['estimated_cost'] ?? 0);
    $emd_amount = (float)($_POST['emd_amount'] ?? 0);
    $tender_fee = (float)($_POST['tender_fee'] ?? 0);
    $security_deposit_percent = (float)($_POST['security_deposit_percent'] ?? 0);

    // Financial Rules
    $emd_percent = (float)($_POST['emd_percent'] ?? 0);
    $pg_percent = (float)($_POST['pg_percent'] ?? 0);
    $sd_deduction_percent = (float)($_POST['sd_deduction_percent'] ?? 0);
    $defect_liability_period = (int)($_POST['defect_liability_period'] ?? 0);

    // Payment Terms
    $payment_terms = [];
    if (isset($_POST['pt_stage']) && is_array($_POST['pt_stage'])) {
        foreach ($_POST['pt_stage'] as $index => $stage) {
            $percent = (float)($_POST['pt_percent'][$index] ?? 0);
            if (!empty($stage) && $percent > 0) {
                $payment_terms[] = [
                    'stage' => trim($stage),
                    'percent' => $percent
                ];
            }
        }
    }

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        // Generate ID: TND/YYYY/XX
        $year = date('Y');
        $count = 0;
        foreach ($tenders as $t) {
            if (strpos($t['tender_id'], "TND/$year/") === 0) {
                $count++;
            }
        }
        $nextNum = $count + 1;
        $tenderId = "TND/$year/" . sprintf('%02d', $nextNum);

        $newTender = [
            'tender_id' => $tenderId,
            'title' => $title,
            'description' => $description,
            'work_description' => $work_description,
            'estimated_cost' => $estimated_cost,
            'emd_amount' => $emd_amount,
            'tender_fee' => $tender_fee,
            'security_deposit_percent' => $security_deposit_percent,
            'financial_rules' => [
                'emd_percent' => $emd_percent,
                'pg_percent' => $pg_percent,
                'sd_deduction_percent' => $sd_deduction_percent,
                'defect_liability_period' => $defect_liability_period
            ],
            'payment_terms' => $payment_terms,
            'participants' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'Open',
            'created_by' => $_SESSION['user_id']
        ];

        // Key by ID for easy access
        $tenders[$tenderId] = $newTender;

        if (writeJSON($tendersPath, $tenders)) {
            $message = "Tender created successfully: $tenderId";
        } else {
            $error = "Failed to save tender.";
        }
    }
}

// Prepare list for display (Sort by ID desc)
$tenderList = array_values($tenders);
usort($tenderList, function($a, $b) {
    return strcmp($b['tender_id'], $a['tender_id']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tender Dashboard - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .tender-card {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tender-info h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        .tender-meta {
            font-size: 0.9rem;
            color: #666;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-open { background: #e3fcef; color: #006644; }
        .status-closed { background: #dfe1e6; color: #42526e; }

        .create-panel {
            background: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Tender Management</h1>
        </div>
    </div>

    <div class="dashboard-container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Create New Tender -->
        <div class="create-panel">
            <h2>Create New Tender</h2>
            <form method="POST" action="">
                <input type="hidden" name="create_tender" value="1">
                <div class="form-group">
                    <label>Tender Title</label>
                    <input type="text" name="title" required placeholder="e.g. Road Repair Zone 4">
                </div>
                <div class="form-group">
                    <label>Short Description (Optional)</label>
                    <textarea name="description" rows="2" placeholder="Brief summary..."></textarea>
                </div>

                <div class="form-group">
                    <label>Detailed Work Description</label>
                    <textarea name="work_description" rows="4" placeholder="Full details of the work required..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>Estimated Cost (₹)</label>
                        <input type="number" step="0.01" name="estimated_cost" placeholder="0.00">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>EMD Amount (₹)</label>
                        <input type="number" step="0.01" name="emd_amount" placeholder="0.00">
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>Tender Fee (₹)</label>
                        <input type="number" step="0.01" name="tender_fee" placeholder="0.00">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Security Deposit (%)</label>
                        <input type="number" step="0.01" name="security_deposit_percent" placeholder="e.g. 10">
                    </div>
                </div>

                <h3>Financial Rules</h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>EMD (%)</label>
                        <input type="number" step="0.01" name="emd_percent" placeholder="e.g. 2">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Performance Guarantee (%)</label>
                        <input type="number" step="0.01" name="pg_percent" placeholder="e.g. 5">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>SD Deduction (%)</label>
                        <input type="number" step="0.01" name="sd_deduction_percent" placeholder="e.g. 8">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Defect Liability Period (Months)</label>
                        <input type="number" name="defect_liability_period" placeholder="e.g. 12">
                    </div>
                </div>

                <h3>Payment Terms</h3>
                <table id="payment-terms-table" style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Stage Name</th>
                            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Percentage (%)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows will be added here -->
                    </tbody>
                </table>
                <button type="button" class="btn-secondary" onclick="addPaymentStage()" style="margin-bottom: 1.5rem;">+ Add Stage</button>

                <br><br>
                <button type="submit" class="btn-primary" onclick="return validateTenderForm()">Create Tender</button>
            </form>
        </div>

        <script>
            function addPaymentStage(name = '', percent = '') {
                const tbody = document.querySelector('#payment-terms-table tbody');
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="padding: 8px;"><input type="text" name="pt_stage[]" value="${name}" placeholder="e.g. Supply" style="width: 100%; padding: 6px;" required></td>
                    <td style="padding: 8px;"><input type="number" step="0.01" name="pt_percent[]" value="${percent}" placeholder="0" style="width: 100%; padding: 6px;" class="pt-percent" required></td>
                    <td style="padding: 8px; text-align: center;"><button type="button" onclick="this.closest('tr').remove()" style="color: red; background: none; border: none; cursor: pointer;">&times;</button></td>
                `;
                tbody.appendChild(row);
            }

            // Add default stage
            addPaymentStage('Completion', '100');

            function validateTenderForm() {
                const percents = document.querySelectorAll('.pt-percent');
                let total = 0;
                percents.forEach(input => {
                    total += parseFloat(input.value || 0);
                });

                if (Math.abs(total - 100) > 0.01) {
                    alert('Total Payment Terms percentage must equal 100%. Current total: ' + total + '%');
                    return false;
                }
                return true;
            }
        </script>

        <!-- Tender List -->
        <h2>Active Tenders</h2>
        <?php if (empty($tenderList)): ?>
            <p>No tenders found.</p>
        <?php else: ?>
            <?php foreach ($tenderList as $t): ?>
                <div class="tender-card">
                    <div class="tender-info">
                        <h3><?php echo htmlspecialchars($t['title']); ?> <small>(<?php echo htmlspecialchars($t['tender_id']); ?>)</small></h3>
                        <div class="tender-meta">
                            Participants: <?php echo count($t['participants']); ?> |
                            Created: <?php echo htmlspecialchars($t['created_at']); ?>
                        </div>
                    </div>
                    <div class="tender-actions" style="text-align: right;">
                        <span class="status-badge <?php echo $t['status'] === 'Open' ? 'status-open' : 'status-closed'; ?>">
                            <?php echo htmlspecialchars($t['status']); ?>
                        </span>
                        <br><br>
                        <a href="view_tender.php?id=<?php echo urlencode($t['tender_id']); ?>" class="btn-secondary">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
