<?php
session_start();
require_once 'functions.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$docId = $_GET['id'] ?? '';
$message = '';
$error = '';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    if (isset($_POST['move_document'])) {
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
    </style>
</head>
<body>
    <div class="dashboard-header no-print">
        <div class="header-left">
            <h1><?php echo htmlspecialchars($doc['title']); ?></h1>
            <span class="status-badge"><?php echo htmlspecialchars($doc['status']); ?></span>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </div>

    <div class="action-panel no-print">
        <div>
            <strong>Current Owner:</strong> <?php echo htmlspecialchars($doc['current_owner']); ?>
        </div>

        <?php if ($isOwner): ?>
            <div>
                <button onclick="window.print()" class="btn-secondary">Print</button>

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
