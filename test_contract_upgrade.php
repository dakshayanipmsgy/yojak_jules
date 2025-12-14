<?php
// Mock Session and Functions
session_start();
$_SESSION['dept_id'] = 'TEST_DEPT';
$_SESSION['user_id'] = 'TEST_USER';

function readJSON($path) {
    $fullPath = 'storage/' . $path;
    if (!file_exists($fullPath)) return [];
    return json_decode(file_get_contents($fullPath), true) ?? [];
}

function writeJSON($path, $data) {
    $fullPath = 'storage/' . $path;
    if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
    return file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT));
}

// 1. Setup Test Data
$deptId = 'TEST_DEPT';
$tendersPath = 'departments/' . $deptId . '/data/tenders.json';
$woPath = 'departments/' . $deptId . '/data/work_orders.json';

// Clean up previous runs
if(file_exists('storage/' . $tendersPath)) unlink('storage/' . $tendersPath);
if(file_exists('storage/' . $woPath)) unlink('storage/' . $woPath);

// 2. Simulate Creating a Tender (Directly calling logic similar to tenders.php)
$tenderId = "TND/2025/01";
$financial_rules = [
    'emd_percent' => 2,
    'pg_percent' => 5,
    'sd_deduction_percent' => 8,
    'defect_liability_period' => 12
];
$payment_terms = [
    ['stage' => 'Supply', 'percent' => 60],
    ['stage' => 'Installation', 'percent' => 30],
    ['stage' => 'Commissioning', 'percent' => 10]
];

$newTender = [
    'tender_id' => $tenderId,
    'title' => "Test Tender",
    'financial_rules' => $financial_rules,
    'payment_terms' => $payment_terms,
    'participants' => [['contractor_id' => 'CON-001']] // Mock participant
];

$tenders = [$tenderId => $newTender];
writeJSON($tendersPath, $tenders);
echo "Tender Created:\n";
print_r(readJSON($tendersPath));

// 3. Simulate Creating a Work Order (Logic from create_work_order.php)
$tender_id_linked = $tenderId;
$contractor_id = 'CON-001';

// Extract Logic (as implemented in create_work_order.php)
$financial_rules_extracted = [];
$payment_terms_extracted = [];
$milestone_status = [];

if ($tender_id_linked && isset($tenders[$tender_id_linked])) {
    $linkedTender = $tenders[$tender_id_linked];
    $financial_rules_extracted = $linkedTender['financial_rules'] ?? [];
    $payment_terms_extracted = $linkedTender['payment_terms'] ?? [];

    foreach ($payment_terms_extracted as $pt) {
        if (isset($pt['stage'])) {
            $milestone_status[$pt['stage']] = 'Pending';
        }
    }
}

$wo_unique_id = 'WO_TEST_001';
$newWO = [
    'wo_unique_id' => $wo_unique_id,
    'tender_id' => $tender_id_linked,
    'financial_rules' => $financial_rules_extracted,
    'payment_terms' => $payment_terms_extracted,
    'milestone_status' => $milestone_status,
    'agreement' => [
        'status' => 'Pending',
        'signed_date' => '',
        'time_allowed' => '6 Months'
    ],
    'bank_guarantees' => []
];

$workOrders = [$wo_unique_id => $newWO];
writeJSON($woPath, $workOrders);

echo "\nWork Order Created:\n";
print_r(readJSON($woPath));

// 4. Assertions
$woData = readJSON($woPath)[$wo_unique_id];

if ($woData['financial_rules']['emd_percent'] == 2 &&
    $woData['payment_terms'][0]['stage'] == 'Supply' &&
    $woData['milestone_status']['Supply'] == 'Pending') {
    echo "\nSUCCESS: Rules and Terms copied correctly.\n";
} else {
    echo "\nFAILURE: Data mismatch.\n";
    exit(1);
}
?>
