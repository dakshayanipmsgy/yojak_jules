<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Only department users can search (Superadmin dashboard is separate)
if (!isset($_SESSION['dept_id'])) {
    header("Location: dashboard.php");
    exit();
}

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$roleId = $_SESSION['role_id'];

// Determine if user is Admin
$isAdmin = ($roleId === 'admin.' . $deptId);

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query !== '') {
    // Search Logic
    // Use PHP glob to get all .json files in storage/departments/{current_dept}/documents/.
    $docDir = STORAGE_PATH . '/departments/' . $deptId . '/documents/';
    $files = glob($docDir . '*.json');

    if ($files) {
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $doc = json_decode($content, true);
                if ($doc) {
                    $docId = $doc['id'] ?? '';
                    $title = $doc['title'] ?? '';

                    // Check if title OR id contains the search string (case-insensitive)
                    if (stripos($docId, $query) !== false || stripos($title, $query) !== false) {

                        // Privacy Check
                        $canView = false;
                        if ($isAdmin) {
                            $canView = true;
                        } else {
                            // Regular User: Only show results where they are the current_owner OR where they appear in the history
                            if (isset($doc['current_owner']) && $doc['current_owner'] === $userId) {
                                $canView = true;
                            } elseif (isset($doc['history']) && is_array($doc['history'])) {
                                foreach ($doc['history'] as $historyItem) {
                                    // Check if user was involved in history (from or to)
                                    // History structure: {'action': 'moved', 'from': 'user1', 'to': 'user2', 'time': ...}
                                    if ((isset($historyItem['from']) && $historyItem['from'] === $userId) ||
                                        (isset($historyItem['to']) && $historyItem['to'] === $userId)) {
                                        $canView = true;
                                        break;
                                    }
                                    // Also check 'created_by' if it's not explicitly in history array (though typically creator handles it first)
                                    // But the requirement says "where they appear in the history (meaning they touched the file before)".
                                    // Let's also check 'created_by' just in case.
                                }
                            }

                            // Check created_by as well, as they definitely "touched" it.
                            if (isset($doc['created_by']) && $doc['created_by'] === $userId) {
                                $canView = true;
                            }
                        }

                        if ($canView) {
                            $results[] = $doc;
                        }
                    }
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - Yojak</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .search-container {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .search-header {
            margin-bottom: 20px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        .results-table th, .results-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .results-table th {
            background-color: #f4f4f4;
        }
        .no-results {
            padding: 20px;
            text-align: center;
            color: #777;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="search-container">
    <div class="search-header">
        <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
    </div>

    <?php if (empty($results)): ?>
        <div class="no-results">
            No documents found matching your query.
        </div>
    <?php else: ?>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Document ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Current Owner</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $doc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doc['id']); ?></td>
                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td><?php echo htmlspecialchars($doc['status'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($doc['current_owner'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($doc['updated_at'] ?? $doc['created_at'] ?? ''); ?></td>
                        <td>
                            <a href="view_document.php?id=<?php echo urlencode($doc['id']); ?>" class="btn">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
