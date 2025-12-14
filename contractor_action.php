<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor') {
    die("Access Denied");
}

$deptId = $_SESSION['dept_id'] ?? '';
$contractorId = $_SESSION['user_id'] ?? '';
$woId = $_POST['wo_id'] ?? '';
$action = $_POST['action'] ?? '';

if (!$deptId || !$contractorId || !$woId || !$action) {
    die("Invalid Request");
}

// Load Work Order
$woPath = 'departments/' . $deptId . '/data/work_orders.json';
$workOrders = readJSON($woPath) ?? [];

if (!isset($workOrders[$woId])) {
    die("Work Order not found.");
}

$wo = $workOrders[$woId];

// Verify Ownership
if ($wo['contractor_id'] !== $contractorId) {
    die("Access Denied.");
}

$fileId = $wo['file_id'] ?? '';
if (!$fileId) {
    die("System Error: No Case File linked to this Work Order.");
}

// Base Upload Path
$baseUploadDir = STORAGE_PATH . "/departments/$deptId/files/$fileId/contractor_uploads/";
if (!is_dir($baseUploadDir)) {
    mkdir($baseUploadDir, 0777, true);
}

$message = "";
$error = "";

if ($action === 'upload_agreement') {
    if (isset($_FILES['agreement_file']) && $_FILES['agreement_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['agreement_file']['tmp_name'];
        $fileName = $_FILES['agreement_file']['name'];
        $fileSize = $_FILES['agreement_file']['size'];
        $fileType = $_FILES['agreement_file']['type'];

        // Validate PDF
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fileTmpPath);
        if ($mime !== 'application/pdf') {
            die("Error: Only PDF files are allowed.");
        }

        $newFileName = 'Signed_Agreement_' . time() . '.pdf';
        $destPath = $baseUploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Update WO
            $workOrders[$woId]['agreement']['status'] = 'Submitted_Pending_Approval';
            $workOrders[$woId]['agreement']['file_path'] = 'files/' . $fileId . '/contractor_uploads/' . $newFileName;
            $workOrders[$woId]['agreement']['submitted_at'] = date('Y-m-d H:i:s');

            writeJSON($woPath, $workOrders);
            $message = "Agreement uploaded successfully.";
        } else {
            $error = "Failed to move uploaded file.";
        }
    } else {
        $error = "No file uploaded or upload error.";
    }

} elseif ($action === 'add_bg') {
    $ref = $_POST['bg_ref'] ?? '';
    $bank = $_POST['bg_bank'] ?? '';
    $amount = $_POST['bg_amount'] ?? '';
    $expiry = $_POST['bg_expiry'] ?? '';

    if ($ref && $bank && $amount && $expiry && isset($_FILES['bg_file']) && $_FILES['bg_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['bg_file']['tmp_name'];

        // Validate PDF
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fileTmpPath);
        if ($mime !== 'application/pdf') {
            die("Error: Only PDF files are allowed.");
        }

        $newFileName = 'BG_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ref) . '_' . time() . '.pdf';
        $destPath = $baseUploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $workOrders[$woId]['bank_guarantees'][] = [
                'ref_no' => $ref,
                'bank_name' => $bank,
                'amount' => $amount,
                'expiry_date' => $expiry,
                'file_path' => 'files/' . $fileId . '/contractor_uploads/' . $newFileName,
                'status' => 'Pending', // Pending Approval
                'added_at' => date('Y-m-d H:i:s')
            ];
            writeJSON($woPath, $workOrders);
            $message = "Bank Guarantee submitted.";
        } else {
            $error = "Failed to upload file.";
        }
    } else {
        $error = "Missing fields or file.";
    }

} elseif ($action === 'request_inspection') {
    $stage = $_POST['stage'] ?? '';
    if ($stage && isset($wo['milestone_status'][$stage])) {
        // Prevent re-request if already requested or completed?
        // Prompt says "Disables if already requested or completed" -> Handle in UI mostly, but safe to check here.
        $currentStatus = $wo['milestone_status'][$stage];
        if ($currentStatus !== 'Inspection_Requested' && $currentStatus !== 'Completed') {
            $workOrders[$woId]['milestone_status'][$stage] = 'Inspection_Requested';
            writeJSON($woPath, $workOrders);
            $message = "Inspection requested for $stage.";
        } else {
            $error = "Inspection already requested or completed.";
        }
    } else {
        $error = "Invalid milestone stage.";
    }
}

// Redirect
$url = "contractor_wo_view.php?id=" . $woId;
if ($message) {
    $url .= "&msg=" . urlencode($message);
}
if ($error) {
    $url .= "&err=" . urlencode($error);
}
header("Location: " . $url);
exit;
