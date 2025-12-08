<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$docId = $_GET['id'] ?? '';
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

if (empty($docId)) {
    header('Location: dashboard.php');
    exit;
}

$doc = getDocument($deptId, $docId);

if (!$doc) {
    die("Document not found.");
}

// Check Permission (Simple: Can only view if Current Owner or Created By)
// Prompt says: "The current_owner field... determines who sees the file."
// Also: "Outbox... Show columns: Title, Currently With" implies Creator can also see it, or at least metadata.
// But "Inbox View... filtered by current_owner".
// "View & Action Page: When a user opens a document from their Inbox" implies owner access.
// Let's allow if Owner OR Creator.
if ($doc['current_owner'] !== $userId && $doc['created_by'] !== $userId) {
    die("Access Denied.");
}

$isOwner = ($doc['current_owner'] === $userId);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Note (Green Sheet) - Access: Any user who can view (owner or creator) can add a note?
    // "Internal: Officers need to write comments about a file without changing the file itself"
    // Usually only the current owner works on the file, but note sheet might be accessible to anyone involved?
    // Let's restrict adding notes to the current owner for now to maintain workflow integrity,
    // OR if the prompt implies anyone with access. "Officers need to write comments... without changing the file".
    // Since "Viewing" is restricted to Owner/Creator, let's allow both to comment.
    if (isset($_POST['add_note'])) {
        $noteContent = trim($_POST['note_content'] ?? '');
        if (!empty($noteContent)) {
            // Get Current User Role Name
            $currentUserRole = $_SESSION['role_id']; // ID, we need name
            $roles = getRoles($deptId);
            $roleName = $roles[$currentUserRole]['name'] ?? $currentUserRole;

            $noteEntry = [
                'user' => $roleName . ' (' . $userId . ')',
                'note' => $noteContent,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            if (!isset($doc['note_sheet'])) {
                $doc['note_sheet'] = [];
            }
            $doc['note_sheet'][] = $noteEntry;

            if (saveDocument($deptId, $docId, $doc)) {
                $message = "Note added successfully.";
            } else {
                $error = "Failed to save note.";
            }
        } else {
            $error = "Note cannot be empty.";
        }
    }

    // Move Document (Only Owner)
    if (isset($_POST['move_document']) && $isOwner) {
        $targetUserId = $_POST['target_user_id'] ?? '';

        if (empty($targetUserId)) {
            $error = "Please select a user to forward to.";
        } else {
            $result = moveDocument($deptId, $docId, $targetUserId, $userId);
            if ($result['success']) {
                $message = $result['message'];
                // Reload doc
                $doc = getDocument($deptId, $docId);
                $isOwner = false; // No longer owner
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get Users for Dropdown (excluding self)
$deptUsers = getUsers($deptId);
// Filter out self
$forwardUsers = [];
foreach ($deptUsers as $uid => $u) {
    if ($uid !== $userId) {
        $forwardUsers[$uid] = $u;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Document - <?php echo htmlspecialchars($doc['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            border: 1px solid #d3d3d3;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            color: black;
            box-sizing: border-box;
        }
        @media print {
            .page {
                margin: 0;
                border: initial;
                box-shadow: none;
                width: initial;
                min-height: initial;
            }
            body { background: white; }
            .sidebar, .no-print, .dashboard-header { display: none !important; }
        }

        .action-panel {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-panel {
            max-width: 210mm;
            margin: 2rem auto;
            padding: 1rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .history-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        .history-item:last-child { border-bottom: none; }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
        }

        /* Note Sheet Styles */
        .note-sheet-panel {
            max-width: 210mm;
            margin: 2rem auto;
            padding: 1rem;
            background: #fdfdfd;
            border: 1px solid #c3e6cb; /* Green-ish border */
            border-radius: 4px;
        }
        .note-bubble {
            background-color: #f1f3f5;
            border-radius: 15px;
            padding: 10px 15px;
            margin-bottom: 10px;
            position: relative;
            max-width: 80%;
        }
        .note-bubble.self {
            background-color: #d1e7dd;
            margin-left: auto;
        }
        .note-meta {
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 4px;
            display: block;
        }
        .note-content {
            font-size: 0.95rem;
            white-space: pre-wrap;
        }

        /* Print Specifics */
        @media print {
            /* Default: Print Letter (Page) - Hide everything else */
            body.print-letter .no-print,
            body.print-letter .note-sheet-panel,
            body.print-letter .history-panel,
            body.print-letter .attachments-panel {
                display: none !important;
            }
            body.print-letter .page {
                display: block !important;
            }

            /* Option 2: Print Note Sheet - Hide Page, Show Notes & History */
            body.print-notes .page,
            body.print-notes .attachments-panel,
            body.print-notes .no-print {
                display: none !important;
            }
            body.print-notes .note-sheet-panel,
            body.print-notes .history-panel {
                display: block !important;
                margin: 0;
                border: none;
                width: 100%;
                max-width: 100%;
            }
            body.print-notes .note-bubble {
                border: 1px solid #ddd;
                background: white;
                color: black;
                max-width: 100%;
                margin-left: 0;
            }
            /* Fallback if no class set on body (default browser print) -> Hide UI, show Page */
            body:not(.print-notes):not(.print-letter) .no-print,
            body:not(.print-notes):not(.print-letter) .note-sheet-panel,
            body:not(.print-notes):not(.print-letter) .history-panel,
            body:not(.print-notes):not(.print-letter) .attachments-panel {
                display: none !important;
            }
        }
    </style>
    <script>
        function printLetter() {
            document.body.classList.remove('print-notes');
            document.body.classList.add('print-letter');
            window.print();
        }
        function printNoteSheet() {
            document.body.classList.remove('print-letter');
            document.body.classList.add('print-notes');
            window.print();
        }
    </script>
</head>
<body>
    <div class="no-print">
        <?php include 'navbar.php'; ?>
    </div>

    <div class="dashboard-header no-print">
        <div class="header-left">
            <h1><?php echo htmlspecialchars($doc['title']); ?></h1>
            <span class="status-badge"><?php echo htmlspecialchars($doc['status']); ?></span>
        </div>
    </div>

    <div class="action-panel no-print">
        <div>
            <strong>Current Owner:</strong> <?php echo htmlspecialchars($doc['current_owner']); ?>
        </div>

        <?php if ($isOwner): ?>
            <div>
                <!-- Print Dropdown / Buttons -->
                <div style="display:inline-block; position: relative;">
                    <button onclick="document.getElementById('print-options').style.display = document.getElementById('print-options').style.display === 'none' ? 'block' : 'none'" class="btn-secondary">Print ▼</button>
                    <div id="print-options" style="display:none; position: absolute; background: white; border: 1px solid #ccc; z-index: 1000; width: 150px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <button onclick="printLetter(); document.getElementById('print-options').style.display='none'" style="display:block; width: 100%; padding: 8px; border:none; background:white; text-align:left; cursor:pointer; border-bottom:1px solid #eee;">Print Letter</button>
                        <button onclick="printNoteSheet(); document.getElementById('print-options').style.display='none'" style="display:block; width: 100%; padding: 8px; border:none; background:white; text-align:left; cursor:pointer;">Print Note Sheet</button>
                    </div>
                </div>

                <?php if ($doc['status'] === 'Draft' || $doc['status'] === 'Correction'): ?>
                    <a href="create_document.php?edit_doc_id=<?php echo $doc['id']; ?>" class="btn-secondary">Edit</a>
                <?php endif; ?>

                <form method="POST" action="" style="display:inline-block; margin-left: 1rem;">
                    <input type="hidden" name="move_document" value="1">
                    <select name="target_user_id" required style="padding: 0.5rem;">
                        <option value="">-- Forward To --</option>
                        <?php foreach ($forwardUsers as $uid => $u): ?>
                            <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($uid); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-primary">Send / Forward</button>
                </form>
            </div>
        <?php else: ?>
            <div>
                <em>You are viewing this document because you created it. You cannot edit or move it.</em>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="success-message no-print" style="text-align:center;"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-message no-print" style="text-align:center;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="page">
        <?php echo $doc['content']; ?>
    </div>

    <!-- Green Sheet (Departmental Notes) -->
    <div class="note-sheet-panel">
        <h3 style="color: #155724; border-bottom: 2px solid #c3e6cb; padding-bottom: 0.5rem; margin-top:0;">Departmental Notes (Green Sheet)</h3>

        <?php if (!empty($doc['note_sheet'])): ?>
            <?php foreach ($doc['note_sheet'] as $note): ?>
                <div class="note-bubble">
                    <span class="note-meta">
                        <strong><?php echo htmlspecialchars($note['user']); ?></strong> |
                        <?php echo htmlspecialchars($note['timestamp']); ?>
                    </span>
                    <div class="note-content"><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #6c757d; font-style: italic;">No notes yet.</p>
        <?php endif; ?>

        <div class="no-print" style="margin-top: 1.5rem; border-top: 1px solid #e9ecef; padding-top: 1rem;">
            <form method="POST" action="">
                <textarea name="note_content" rows="3" placeholder="Add a departmental note..." style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px; resize: vertical;" required></textarea>
                <button type="submit" name="add_note" value="1" class="btn-primary" style="margin-top: 0.5rem; background-color: #28a745; border-color: #28a745;">Add Note</button>
            </form>
        </div>
    </div>

    <div class="attachments-panel no-print" style="max-width: 210mm; margin: 2rem auto; padding: 1rem; background: white; border: 1px solid #dee2e6; border-radius: 4px;">
        <h3>Supporting Documents</h3>

        <?php if (!empty($doc['attachments'])): ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($doc['attachments'] as $att): ?>
                    <li style="margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">
                        <div>
                            <span style="font-size: 1.2rem; margin-right: 0.5rem;">📎</span>
                            <a href="view_attachment.php?doc_id=<?php echo $docId; ?>&file=<?php echo urlencode($att['filename']); ?>" target="_blank" style="text-decoration: none; color: #0056b3; font-weight: 500;">
                                <?php echo htmlspecialchars($att['original_name'] ?? $att['filename']); ?>
                            </a>
                            <span style="font-size: 0.8rem; color: #6c757d; margin-left: 1rem;">
                                (Uploaded by <?php echo htmlspecialchars($att['uploaded_by']); ?>)
                            </span>
                        </div>
                        <?php if ($isOwner): ?>
                            <form method="POST" action="upload_attachment.php" onsubmit="return confirm('Are you sure you want to delete this attachment?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="doc_id" value="<?php echo $docId; ?>">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($att['filename']); ?>">
                                <button type="submit" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.9rem;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: #6c757d;">No supporting documents attached.</p>
        <?php endif; ?>

        <?php if ($isOwner): ?>
            <div style="margin-top: 1.5rem; border-top: 2px dashed #dee2e6; padding-top: 1rem;">
                <h4>Upload Attachments</h4>
                <form method="POST" action="upload_attachment.php" enctype="multipart/form-data">
                    <input type="hidden" name="doc_id" value="<?php echo $docId; ?>">
                    <input type="hidden" name="action" value="upload">
                    <div style="margin-bottom: 1rem;">
                        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.png,.jpeg,.docx,.xlsx" required>
                    </div>
                    <button type="submit" class="btn-primary">Upload Files</button>
                    <p style="font-size: 0.85rem; color: #6c757d; margin-top: 0.5rem;">
                        Allowed: PDF, JPG, PNG, DOCX, XLSX. Max size per file depends on server settings.
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="history-panel no-print">
        <h3>Document History / Audit Trail</h3>
        <?php if (!empty($doc['history'])): ?>
            <?php foreach ($doc['history'] as $h): ?>
                <div class="history-item">
                    <strong><?php echo htmlspecialchars($h['time']); ?></strong> -
                    Action: <?php echo htmlspecialchars($h['action']); ?> |
                    From: <?php echo htmlspecialchars($h['from']); ?> |
                    To: <?php echo htmlspecialchars($h['to']); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No history available.</p>
        <?php endif; ?>
    </div>

</body>
</html>
