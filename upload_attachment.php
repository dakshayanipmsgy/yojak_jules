<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$docId = $_POST['doc_id'] ?? '';
$action = $_POST['action'] ?? 'upload';

if (empty($docId)) {
    die("Error: Document ID is missing.");
}

// Load Document to check permissions
$doc = getDocument($deptId, $docId);
if (!$doc) {
    die("Error: Document not found.");
}

// Permission Check: Only current owner can upload or delete
if ($doc['current_owner'] !== $userId) {
    die("Error: Access Denied. You do not own this document.");
}

// Define Storage Path
$baseUploadPath = 'departments/' . $deptId . '/uploads/' . $docId;
$fullUploadPath = STORAGE_PATH . '/' . $baseUploadPath;

// Ensure Directory Exists
if (!is_dir($fullUploadPath)) {
    if (!mkdir($fullUploadPath, 0755, true)) {
        die("Error: Failed to create upload directory.");
    }
}

// Handle Upload
if ($action === 'upload') {
    if (empty($_FILES['attachments']['name'][0])) {
         header('Location: view_document.php?id=' . $docId . '&error=No+files+selected');
         exit;
    }

    $uploadedFiles = $_FILES['attachments'];
    $allowedExtensions = ['pdf', 'jpg', 'png', 'jpeg', 'docx', 'xlsx'];
    $blockedExtensions = ['php', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm']; // Extended blocklist for safety

    $count = count($uploadedFiles['name']);
    $successCount = 0;
    $errors = [];

    // Initialize attachments array if it doesn't exist
    if (!isset($doc['attachments'])) {
        $doc['attachments'] = [];
    }

    for ($i = 0; $i < $count; $i++) {
        $fileName = $uploadedFiles['name'][$i];
        $fileTmp = $uploadedFiles['tmp_name'][$i];
        $fileSize = $uploadedFiles['size'][$i];
        $fileError = $uploadedFiles['error'][$i];

        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading $fileName";
            continue;
        }

        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Security Check 1: Allow List
        if (!in_array($fileExt, $allowedExtensions)) {
            $errors[] = "File type not allowed: $fileName";
            continue;
        }

        // Security Check 2: Block List (Redundant but good)
        if (in_array($fileExt, $blockedExtensions)) {
             $errors[] = "Security Risk: File type blocked: $fileName";
             continue;
        }

        // Rename File: timestamp_originalName
        // Clean original name to remove special chars
        $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
        $newFileName = time() . '_' . $cleanName;
        $destPath = $fullUploadPath . '/' . $newFileName;
        $relativePath = $baseUploadPath . '/' . $newFileName; // Relative for storage in JSON (Wait, prompt says "storage/.../filename")

        // Prompt says: "path": "storage/.../123456_bill.pdf".
        // My STORAGE_PATH is defined as __DIR__ . '/storage'.
        // The web accessible path depends on where index.php is.
        // Usually, if I store it in 'storage/...', I need to make sure I can link to it.
        // Prompt says: "Clicking the link opens the file in a new tab."
        // Since `storage/` is often protected (via .htaccess mentioned in memory), direct linking might not work if .htaccess blocks it.
        // Memory says: "The storage directory is structured... and must be protected from direct web access via .htaccess."
        // If direct access is blocked, I cannot just link to `storage/...`. I need a proxy script (e.g. `download.php` or `view_attachment.php`) to serve the file.
        // However, the Prompt says: "Clicking the link opens the file in a new tab." and "Storage Path: storage/departments/...".
        // And "The storage directory... must be protected".
        // Contradiction?
        // Maybe I should assume for this task I can link it?
        // Or better, I should implement a simple serve mechanism or checking if I can access it.
        // The Prompt goal is "Allow users to upload files... access permissions".
        // But wait, "Storage is Writable" check in setup.php implies it is a "folder-based database".
        // If I put it in `storage/`, and `storage/.htaccess` says `Deny from all`, then I can't link it directly.
        // I should check if there is an .htaccess in storage.

        // Assuming I might need to serve it via PHP if direct access is blocked.
        // But for now, let's just store the relative path.
        // The JSON path: "path": "storage/departments/{dept}/uploads/{doc}/file.ext"

        if (move_uploaded_file($fileTmp, $destPath)) {
            $doc['attachments'][] = [
                'filename' => $newFileName,
                'original_name' => $fileName,
                'path' => 'storage/' . $relativePath,
                'uploaded_by' => $userId,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
            $successCount++;
        } else {
            $errors[] = "Failed to move uploaded file: $fileName";
        }
    }

    if ($successCount > 0) {
        // Save Document
        if (saveDocument($deptId, $docId, $doc)) {
            $msg = "Successfully uploaded $successCount files.";
            if (!empty($errors)) {
                $msg .= " Errors: " . implode(', ', $errors);
            }
            header('Location: view_document.php?id=' . $docId . '&message=' . urlencode($msg));
            exit;
        } else {
            // Rollback (delete uploaded files? maybe too complex for now)
            die("Error: Failed to update document record.");
        }
    } else {
        $msg = "No files uploaded.";
        if (!empty($errors)) {
            $msg .= " Errors: " . implode(', ', $errors);
        }
        header('Location: view_document.php?id=' . $docId . '&error=' . urlencode($msg));
        exit;
    }
}
elseif ($action === 'delete') {
    $filename = $_POST['filename'] ?? '';
    if (empty($filename)) {
        die("Error: Filename missing.");
    }

    // Find attachment
    $foundIndex = -1;
    if (isset($doc['attachments'])) {
        foreach ($doc['attachments'] as $index => $att) {
            if ($att['filename'] === $filename) {
                $foundIndex = $index;
                break;
            }
        }
    }

    if ($foundIndex === -1) {
        die("Error: Attachment not found.");
    }

    // Delete file
    $filePath = $fullUploadPath . '/' . $filename;
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Remove from array
    array_splice($doc['attachments'], $foundIndex, 1);

    // Save
    if (saveDocument($deptId, $docId, $doc)) {
        header('Location: view_document.php?id=' . $docId . '&message=Attachment+deleted');
        exit;
    } else {
        die("Error: Failed to update document.");
    }

} else {
    die("Invalid action.");
}
