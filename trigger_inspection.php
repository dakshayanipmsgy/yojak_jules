<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Enforce required features if any (though this is part of basic workflow)
// Assuming user has access if they can access manage_wo.php

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request Method");
}

$woId = $_POST['wo_id'] ?? '';
$stage = $_POST['stage'] ?? '';
$officerId = $_POST['officer_id'] ?? '';
$inspectionDate = $_POST['inspection_date'] ?? '';

if (!$woId || !$stage || !$officerId || !$inspectionDate) {
    die("Missing required parameters.");
}

// 1. Load Work Order Data
$woPath = 'departments/' . $deptId . '/data/work_orders.json';
$workOrders = readJSON($woPath);

if (!isset($workOrders[$woId])) {
    die("Work Order not found.");
}
$wo = $workOrders[$woId];
$fileId = $wo['file_id'] ?? '';

if (!$fileId) {
    die("No Linked Case File found for this Work Order.");
}

// 2. Load Officer Data
$users = getUsers($deptId);
$officerName = "Officer";
$officerDesignation = "Designation";

if (isset($users[$officerId])) {
    $officerName = $users[$officerId]['full_name'];
    // Try to get role name for designation
    $roleId = $users[$officerId]['role'];
    $roles = getRoles($deptId);
    if (isset($roles[$roleId])) {
        $officerDesignation = $roles[$roleId]['name'];
    }
} elseif ($officerId === 'external') {
    // If we support non-user officers later, but for now prompt said "dropdown of users"
    $officerName = "External Consultant";
}

// 3. Load Sender Data (Current User)
$senderName = $users[$userId]['full_name'] ?? 'Authorized Signatory';
$senderRole = $users[$userId]['role'] ?? '';
$senderDesignation = "Officer";
$roles = getRoles($deptId);
if (isset($roles[$senderRole])) {
    $senderDesignation = $roles[$senderRole]['name'];
}

// 4. Load Template
$templatePath = STORAGE_PATH . '/system/templates/inspection_request.html';
if (!file_exists($templatePath)) {
    die("Template inspection_request.html not found.");
}
$templateContent = file_get_contents($templatePath);

// 5. Replace Placeholders
$deptMeta = getDepartment($deptId);
$deptName = $deptMeta['name'] ?? 'Department';

$replacements = [
    '{{officer_name}}' => $officerName,
    '{{officer_designation}}' => $officerDesignation,
    '{{department_name}}' => $deptName,
    '{{current_date}}' => date('d-m-Y'),
    '{{stage}}' => $stage,
    '{{wo_number}}' => $wo['wo_number'],
    '{{work_name}}' => $wo['work_name'],
    '{{inspection_date}}' => date('d-m-Y', strtotime($inspectionDate)),
    '{{sender_name}}' => $senderName,
    '{{sender_designation}}' => $senderDesignation
];

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

// 6. Generate Document ID and Save Draft
$docId = 'doc_' . time() . '_' . rand(1000,9999);
$docData = [
    'id' => $docId,
    'title' => "Inspection Request - $stage",
    'content' => $htmlContent,
    'status' => 'draft',
    'created_by' => $userId,
    'created_at' => date('Y-m-d H:i:s'),
    'type' => 'document'
];

$docPath = 'departments/' . $deptId . '/files/' . $fileId . '/documents/' . $docId . '.json';
$fullDocPath = STORAGE_PATH . '/' . $docPath;

if (!is_dir(dirname($fullDocPath))) {
    mkdir(dirname($fullDocPath), 0777, true);
}

if (file_put_contents($fullDocPath, json_encode($docData, JSON_PRETTY_PRINT))) {
    // Success - Redirect to Editor
    header("Location: add_document.php?file_id=$fileId&doc_id=$docId");
    exit;
} else {
    die("Failed to save document.");
}
?>
