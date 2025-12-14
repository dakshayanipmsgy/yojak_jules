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

if ($tenderId && isset($tenders[$tenderId])) {
    $prefillWorkName = $tenders[$tenderId]['title'];
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

        // Extract Rules from Tender
        $financial_rules = [];
        $payment_terms = [];
        $milestone_status = [];

        if ($tender_id_linked && isset($tenders[$tender_id_linked])) {
            $linkedTender = $tenders[$tender_id_linked];
            $financial_rules = $linkedTender['financial_rules'] ?? [];
            $payment_terms = $linkedTender['payment_terms'] ?? [];

            // Initialize Milestone Status
            foreach ($payment_terms as $pt) {
                if (isset($pt['stage'])) {
                    $milestone_status[$pt['stage']] = 'Pending';
                }
            }
        }

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
            'financial_rules' => $financial_rules,
            'payment_terms' => $payment_terms,
            'agreement' => [
                'status' => 'Pending',
                'signed_date' => '',
                'time_allowed' => $time_completion
            ],
            'bank_guarantees' => [],
            'milestone_status' => $milestone_status,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ];

        // Key by Unique ID
        $workOrders[$wo_unique_id] = $newWO;

        // 4. Trigger Automation: Create Case File and Draft Documents
        // "Project Kickoff" Automation
        $fileId = createCaseFile($deptId, "Project: " . $work_name, $_SESSION['user_id']);
        if ($fileId) {
            $workOrders[$wo_unique_id]['file_id'] = $fileId;

            // Calculate PG Amount (5% of Agreed Amount)
            // Sanitize amount (remove commas if any)
            $sanitizedAmount = str_replace(',', '', $agreed_amount);
            $pgAmount = floatval($sanitizedAmount) * 0.05;

            // Fetch Department Name
            $deptMeta = getDepartment($deptId);
            $deptName = $deptMeta['name'] ?? 'Department';

            // Fetch Contractor Details
            $cName = 'Contractor';
            $cAddress = '';
            if (isset($contractors[$contractor_id])) {
                $cName = $contractors[$contractor_id]['name'];
                $cAddress = $contractors[$contractor_id]['address'];
            }

            $commonReplacements = [
                '{{work_name}}' => $work_name,
                '{{contractor_name}}' => $cName,
                '{{contractor_address}}' => $cAddress,
                '{{agreed_amount}}' => $agreed_amount,
                '{{estimated_cost}}' => $agreed_amount, // Approximation
                '{{time_allowed}}' => $time_completion,
                '{{date_issue}}' => $date_issue,
                '{{current_date}}' => date('d-m-Y'),
                '{{current_year}}' => date('Y'),
                '{{department_name}}' => $deptName,
                '{{pg_amount}}' => number_format($pgAmount, 2),
                '{{ref_number}}' => 'DRAFT/' . date('Y') . '/XX'
            ];

            // Template 1: Draft Agreement (F-2)
            $tplAgreement = @file_get_contents('storage/system/templates/agreement_f2.html');
            if ($tplAgreement) {
                $contentAgreement = str_replace(array_keys($commonReplacements), array_values($commonReplacements), $tplAgreement);
                $docId1 = generateDocumentID($deptId);
                $doc1 = [
                    'id' => $docId1,
                    'title' => 'Agreement - ' . $work_name,
                    'content' => $contentAgreement,
                    'status' => 'draft',
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'current_owner' => $_SESSION['user_id'],
                    'type' => 'document',
                    'history' => [
                        [
                            'action' => 'created',
                            'user_id' => $_SESSION['user_id'],
                            'timestamp' => date('Y-m-d H:i:s'),
                            'details' => 'Auto-generated from Work Order'
                        ]
                    ]
                ];
                // Save to Case File documents folder
                $fileDocPath = 'departments/' . $deptId . '/files/' . $fileId . '/documents/' . $docId1 . '.json';
                writeJSON($fileDocPath, $doc1);
            }

            // Template 2: Request for PG
            $tplPG = @file_get_contents('storage/system/templates/pg_request.html');
            if ($tplPG) {
                $contentPG = str_replace(array_keys($commonReplacements), array_values($commonReplacements), $tplPG);
                $docId2 = generateDocumentID($deptId);
                $doc2 = [
                    'id' => $docId2,
                    'title' => 'Letter: Request for PG - ' . $work_name,
                    'content' => $contentPG,
                    'status' => 'draft',
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'current_owner' => $_SESSION['user_id'],
                    'type' => 'document',
                    'history' => [
                        [
                            'action' => 'created',
                            'user_id' => $_SESSION['user_id'],
                            'timestamp' => date('Y-m-d H:i:s'),
                            'details' => 'Auto-generated from Work Order'
                        ]
                    ]
                ];
                $fileDocPath = 'departments/' . $deptId . '/files/' . $fileId . '/documents/' . $docId2 . '.json';
                writeJSON($fileDocPath, $doc2);
            }
        }

        // Save Work Order Again (with file_id)
        writeJSON($woPath, $workOrders);

        // 5. Update Tender if linked
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
