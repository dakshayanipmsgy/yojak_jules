<?php
require_once 'functions.php';

$searchId = $_GET['doc_id'] ?? '';
$searchResult = null;
$error = '';

if (!empty($searchId)) {
    // Sanitize input: Remove potential path traversal characters, though we will scan directories.
    $searchId = trim($searchId);

    // Safety check: ID should be alphanumeric + underscores/dashes to match ID generation
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $searchId)) {
        $error = "Invalid Document ID format.";
    } else {
        // Search Logic: Iterate through all departments
        $departments = getAllDepartments();
        $found = false;

        foreach ($departments as $dept) {
            $deptId = $dept['id'];
            $doc = getDocument($deptId, $searchId);

            if ($doc) {
                // Found! Extract SAFE data only.
                // Security Filter: ID, Status, Current Owner (Role only), Received Date/Created At

                // Determine "Since" date.
                // If the document was moved, history[last]['time'] might be the "since" date for current owner.
                // Or just use created_at if no history.
                $sinceDate = $doc['created_at'];
                if (!empty($doc['history'])) {
                     $lastEntry = end($doc['history']);
                     if (isset($lastEntry['time'])) {
                         $sinceDate = $lastEntry['time'];
                     }
                }

                // Format "Currently With"
                // Owner format: user.{role}.{dept} -> extract {role} and {dept}
                // or just look up role name if possible.
                // Since we don't want to expose internal user IDs, we should try to map it to a friendly name.
                // If we can't map easily without loading all users, we parse the ID.
                // Format: user.clerk.road_dept
                $currentOwnerId = $doc['current_owner'];
                $ownerDisplay = "Unknown";

                $users = getUsers($deptId);
                if (isset($users[$currentOwnerId])) {
                     // Get role name
                     $roleId = $users[$currentOwnerId]['role'];
                     $roles = getRoles($deptId);
                     $roleName = isset($roles[$roleId]) ? $roles[$roleId]['name'] : $roleId;
                     $ownerDisplay = $dept['name'] . ' - ' . $roleName;
                } else {
                    $ownerDisplay = $dept['name'] . ' - ' . $currentOwnerId;
                }

                $searchResult = [
                    'id' => $doc['id'],
                    'status' => $doc['status'],
                    'current_location' => $ownerDisplay,
                    'since' => $sinceDate
                ];
                $found = true;
                break; // Stop searching once found
            }
        }

        if (!$found) {
            $error = "Document not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Document Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #333;
        }
        .container {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #003366; /* Official Blue */
            margin-bottom: 1.5rem;
            font-family: 'Roboto', sans-serif;
        }
        input[type="text"] {
            width: 80%;
            padding: 12px;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }
        button {
            background-color: #003366;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background-color: #002244;
        }
        .result-box {
            margin-top: 2rem;
            text-align: left;
            border-top: 1px solid #eee;
            padding-top: 1rem;
        }
        .result-item {
            margin-bottom: 0.8rem;
        }
        .result-label {
            font-weight: 600;
            color: #555;
            display: block;
            margin-bottom: 0.2rem;
        }
        .result-value {
            font-size: 1.1rem;
            color: #000;
        }
        .error {
            color: #dc3545;
            margin-top: 1rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Document Tracker</h1>
    <p style="color:#666; margin-bottom: 2rem;">Enter your Document Reference Number to check its status.</p>

    <form method="GET" action="">
        <input type="text" name="doc_id" placeholder="e.g., DOC_2023_001" value="<?php echo htmlspecialchars($searchId); ?>" required>
        <br>
        <button type="submit">Track Status</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($searchResult): ?>
        <div class="result-box">
            <div class="result-item">
                <span class="result-label">Document Reference No:</span>
                <span class="result-value"><?php echo htmlspecialchars($searchResult['id']); ?></span>
            </div>
            <div class="result-item">
                <span class="result-label">Status:</span>
                <span class="status-badge"><?php echo htmlspecialchars($searchResult['status']); ?></span>
            </div>
            <div class="result-item">
                <span class="result-label">Currently With:</span>
                <span class="result-value"><?php echo htmlspecialchars($searchResult['current_location']); ?></span>
            </div>
            <div class="result-item">
                <span class="result-label">Since:</span>
                <span class="result-value"><?php echo htmlspecialchars($searchResult['since']); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
