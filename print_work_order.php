<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Authentication and Authorization
if (!isset($_SESSION['dept_id'])) {
    die("Error: Session expired or invalid.");
}
$deptId = $_SESSION['dept_id'];

// Get Work Order ID
if (!isset($_GET['id'])) {
    die("Error: Invalid Work Order ID.");
}
$woUniqueId = $_GET['id'];

// Load Data
// Note: readJSON() in functions.php handles the 'storage/' prefix internally.
// We pass relative paths starting from 'departments/...'.
$woPath = 'departments/' . $deptId . '/data/work_orders.json';
$workOrders = readJSON($woPath);
$contractors = readJSON('departments/' . $deptId . '/data/contractors.json');
$tenders = readJSON('departments/' . $deptId . '/data/tenders.json') ?? [];
$settings = readJSON('departments/' . $deptId . '/data/settings.json');
// Fallback for settings if 'department.json' has header info?
// Using settings.json as per memory and standard practice in this codebase for prefixes/headers
$department = readJSON('departments/' . $deptId . '/department.json'); // Might need this for office name if not in settings

// Find specific Work Order
$wo = null;
if ($workOrders) {
    foreach ($workOrders as $item) {
        if ($item['wo_unique_id'] === $woUniqueId) {
            $wo = $item;
            break;
        }
    }
}

if (!$wo) {
    die("Error: Work Order not found.");
}

// Find Contractor
$contractor = $contractors[$wo['contractor_id']] ?? null;
if (!$contractor) {
    die("Error: Contractor details not found.");
}

// Find Tender (Optional)
$tender = null;
if (!empty($wo['tender_id'])) {
    // Tenders are stored with ID as key in tenders.json? Or as a list?
    // Let's assume list or key. Tenders usually key-based in memory logs.
    // "Tender data is stored in storage/departments/{dept_id}/data/tenders.json and uses the ID format TND/YYYY/XX."
    // Let's try direct access first, then scan if needed.
    if (isset($tenders[$wo['tender_id']])) {
        $tender = $tenders[$wo['tender_id']];
    } else {
        // Fallback scan
        foreach ($tenders as $t) {
            if (($t['tender_id'] ?? '') === $wo['tender_id']) {
                $tender = $t;
                break;
            }
        }
    }
}

// Load Template
$templatePath = 'storage/system/templates/work_order.html';
if (!file_exists($templatePath)) {
    die("Error: Work Order template not found.");
}
$html = file_get_contents($templatePath);

// Prepare Data for Replacement
$officeName = $settings['office_name'] ?? ('Office of the Executive Engineer, ' . $deptId);
// Sometimes office name is in settings, sometimes implied.
// Memory says: "Department-specific configuration ... office name ... is stored in storage/departments/{dept_id}/data/settings.json"
// The template uses {{department_name}}.

$departmentName = $settings['office_name'] ?? $department['name'] ?? $deptId;

$recipientName = $contractor['name'] ?? 'N/A';
$recipientAddress = $contractor['address'] ?? 'N/A';
$recipientId = $wo['contractor_id'];

$tenderDate = $tender['created_at'] ?? 'N/A'; // Assuming created_at or some date field
if ($tender && isset($tender['publish_date'])) {
    $tenderDate = $tender['publish_date'];
}

// Replacements map
$replacements = [
    '{{department_name}}' => htmlspecialchars($departmentName),
    '{{ref_number}}' => htmlspecialchars($wo['wo_number']),
    '{{current_date}}' => htmlspecialchars($wo['date_issue']), // Issue date
    '{{recipient_name}}' => htmlspecialchars($recipientName),
    '{{recipient_address}}' => nl2br(htmlspecialchars($recipientAddress)),
    '{{recipient_id}}' => htmlspecialchars($recipientId),
    '{{work_name}}' => htmlspecialchars($wo['work_name']),
    '{{tender_id}}' => htmlspecialchars($wo['tender_id'] ?? 'N/A'),
    '{{tender_date}}' => htmlspecialchars($tenderDate),
    '{{estimated_cost}}' => htmlspecialchars($wo['estimated_cost'] ?? 'N/A'),
    '{{agreed_amount}}' => htmlspecialchars($wo['agreed_amount']),
    '{{completion_time}}' => htmlspecialchars($wo['time_completion'])
];

// Perform Replacement
foreach ($replacements as $key => $value) {
    $html = str_replace($key, $value, $html);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Work Order - <?php echo htmlspecialchars($wo['wo_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        body {
            background-color: #525659; /* Chrome Print Preview Grey */
            font-family: 'Times New Roman', Times, serif; /* Formal look */
            margin: 0;
            padding: 20px;
        }

        /* Page Simulation */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }

        /* Print Button */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-print {
            background-color: #0052cc;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            font-family: sans-serif;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-print:hover {
            background-color: #0747a6;
        }

        /* Template Styles Correction */
        table {
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid black;
            padding: 8px;
        }

        /* Print Media Query */
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .page {
                width: 100%;
                margin: 0;
                padding: 0; /* Adjust padding if printer adds margins, or keep 20mm if printer bleeds */
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none;
            }
            @page {
                size: A4;
                margin: 20mm;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Print Work Order</button>
        <button onclick="window.close()" class="btn-print" style="background-color: #666; margin-left: 10px;">Close</button>
    </div>

    <div class="page">
        <?php echo $html; ?>
    </div>

</body>
</html>
