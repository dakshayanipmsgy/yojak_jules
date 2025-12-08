<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Department Admin Only
$deptId = $_SESSION['dept_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;

if (!$deptId || $roleId !== 'admin.' . $deptId) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access Denied: You must be a Department Administrator to view this page.";
    exit();
}

// Handle "Pull File" Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pull_file') {
    $docId = $_POST['doc_id'] ?? '';
    if ($docId) {
        $doc = getDocument($deptId, $docId);
        if ($doc) {
            $previousOwner = $doc['current_owner'];
            $adminId = $_SESSION['user_id'];

            // Update Document
            $doc['current_owner'] = $adminId;
            $doc['history'][] = [
                'action' => 'admin_override',
                'note' => 'File pulled by Administrator',
                'from' => $previousOwner,
                'to' => $adminId,
                'time' => date('Y-m-d H:i:s')
            ];
            // Also update timestamp? The prompt doesn't explicitly say update "Last Updated" but usually moving a file updates it.
            // Let's update 'updated_at' if it exists or relies on history.
            // The prompt says "The file instantly moves from the Clerk's inbox to the Admin's inbox."
            // Status stays the same? Prompt doesn't say change status. "The system updates the JSON file: Change current_owner... Add to history..."

            if (saveDocument($deptId, $docId, $doc)) {
                $message = "Document pulled successfully.";
            } else {
                $error = "Failed to pull document.";
            }
        } else {
            $error = "Document not found.";
        }
    }
}

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $allDocs = getAllDocuments($deptId);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="master_register_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Document ID', 'Title', 'Created By', 'Current Location', 'Status', 'Last Updated']);

    foreach ($allDocs as $doc) {
        $lastUpdated = $doc['updated_at'] ?? $doc['created_at'] ?? '';
        fputcsv($output, [
            $doc['id'],
            $doc['title'],
            $doc['created_by'],
            $doc['current_owner'], // Current Location
            $doc['status'],
            $lastUpdated
        ]);
    }

    fclose($output);
    exit();
}

// Get all documents for display
$documents = getAllDocuments($deptId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master Register - Yojak</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .register-container {
            width: 95%;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .register-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        .register-table th, .register-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .register-table th {
            background-color: #f4f4f4;
        }
        .row-bottleneck {
            background-color: #ffe6e6; /* Light red */
        }
        .btn-pull {
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
        }
        .btn-pull:hover {
            background-color: #c9302c;
        }
        .btn-export {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-export:hover {
            background-color: #4cae4c;
        }
        .message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="register-container">
    <div class="header-actions">
        <h2>Master Register</h2>
        <a href="master_register.php?action=export" class="btn-export">Export Report (CSV)</a>
    </div>

    <?php if (isset($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <table class="register-table">
        <thead>
            <tr>
                <th>Document ID</th>
                <th>Title</th>
                <th>Created By</th>
                <th>Current Location (Owner)</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $doc): ?>
                <?php
                // Check for Bottleneck: Status "Pending" > 7 days
                $isBottleneck = false;
                if (stripos($doc['status'], 'Pending') !== false) {
                    // Determine when it entered "Pending" status?
                    // The prompt says: "If Status is 'Pending' for > 7 days".
                    // We need to look at the last update time or when it was moved.
                    // Assuming 'updated_at' reflects the last move.

                    // If updated_at is missing, fallback to created_at
                    $lastUpdateStr = $doc['updated_at'] ?? $doc['created_at'] ?? 'now';
                    $lastUpdate = strtotime($lastUpdateStr);
                    $sevenDaysAgo = strtotime('-7 days');

                    if ($lastUpdate < $sevenDaysAgo) {
                        $isBottleneck = true;
                    }
                }

                $rowClass = $isBottleneck ? 'row-bottleneck' : '';
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($doc['id']); ?></td>
                    <td><?php echo htmlspecialchars($doc['title']); ?></td>
                    <td><?php echo htmlspecialchars($doc['created_by']); ?></td>
                    <td><?php echo htmlspecialchars($doc['current_owner']); ?></td>
                    <td><?php echo htmlspecialchars($doc['status']); ?></td>
                    <td><?php echo htmlspecialchars($doc['updated_at'] ?? $doc['created_at'] ?? ''); ?></td>
                    <td>
                        <a href="view_document.php?id=<?php echo urlencode($doc['id']); ?>" class="btn">View</a>
                        <?php if ($doc['current_owner'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to pull this file to your inbox?');">
                                <input type="hidden" name="action" value="pull_file">
                                <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($doc['id']); ?>">
                                <button type="submit" class="btn-pull">Pull File</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
