<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$docId = $_GET['doc_id'] ?? '';
$filename = $_GET['file'] ?? '';

if (empty($docId) || empty($filename)) {
    die("Invalid request.");
}

// 1. Get Document
$doc = getDocument($deptId, $docId);
if (!$doc) {
    die("Document not found.");
}

// 2. Check Permissions (Same as view_document.php)
// Allow if Current Owner OR Created By
if ($doc['current_owner'] !== $userId && $doc['created_by'] !== $userId) {
    die("Access Denied.");
}

// 3. Locate File
// Sanitize filename to prevent traversal
$filename = basename($filename);
$filePath = STORAGE_PATH . "/departments/$deptId/uploads/$docId/$filename";

if (!file_exists($filePath)) {
    die("File not found.");
}

// 4. Serve File
$mimeType = mime_content_type($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $doc['attachments'][array_search($filename, array_column($doc['attachments'], 'filename'))]['original_name'] . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
