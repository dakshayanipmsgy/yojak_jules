<?php
session_start();
require_once 'auth_check.php';
require_once 'functions.php';

enforceFeature('file_system');

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];

// Path to files
$filesDir = "storage/departments/$deptId/files/";
if (!is_dir($filesDir)) {
    mkdir($filesDir, 0777, true);
}

// Handle File Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_file') {
    $subject = trim($_POST['subject']);
    if (!empty($subject)) {
        // Generate File Number/ID
        // Pattern: FILE_YYYY_XXX (001, 002, etc.)
        $year = date('Y');

        // Scan existing files to find the next number
        $existingFiles = glob($filesDir . 'FILE_' . $year . '_*');
        $maxNum = 0;
        foreach ($existingFiles as $file) {
            $basename = basename($file); // FILE_2025_001
            $parts = explode('_', $basename);
            if (isset($parts[2]) && is_numeric($parts[2])) {
                $num = intval($parts[2]);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        $nextNum = $maxNum + 1;
        $fileId = 'FILE_' . $year . '_' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        $fileNumber = "FILE/$year/" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $newFileDir = $filesDir . $fileId . '/';

        if (mkdir($newFileDir, 0777, true)) {
            // Create subdirectories
            mkdir($newFileDir . 'documents/', 0777, true);
            mkdir($newFileDir . 'attachments/', 0777, true);

            // Create meta.json
            $meta = [
                'file_number' => $fileNumber,
                'subject' => $subject,
                'created_by' => $userId,
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($newFileDir . 'meta.json', json_encode($meta, JSON_PRETTY_PRINT));

            // Create notes.json (Green Sheet)
            // Empty array initially
            file_put_contents($newFileDir . 'notes.json', json_encode([], JSON_PRETTY_PRINT));

            // Redirect to view the file
            header("Location: view_file.php?id=" . $fileId);
            exit;
        } else {
            $error = "Failed to create file directory.";
        }
    } else {
        $error = "Subject is required.";
    }
}

// List Files
$fileList = [];
$scan = glob($filesDir . 'FILE_*');
if ($scan) {
    foreach ($scan as $path) {
        if (is_dir($path)) {
            $metaPath = $path . '/meta.json';
            if (file_exists($metaPath)) {
                $meta = json_decode(file_get_contents($metaPath), true);
                $meta['id'] = basename($path); // Add ID to the array for easy access
                $fileList[] = $meta;
            }
        }
    }
}

// Sort files by created_at desc
usort($fileList, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Manager - Yojak</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .file-manager-container {
            padding: 20px;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .file-table th, .file-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .file-table th {
            background-color: #f2f2f2;
        }
        .create-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }
        .create-btn:hover {
            background-color: #218838;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .action-link {
            color: #007bff;
            text-decoration: none;
            margin-right: 10px;
        }
        .action-link:hover {
            text-decoration: underline;
        }
        .status-open {
            color: green;
            font-weight: bold;
        }
        .status-closed {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="file-manager-container">
    <h1>File Manager</h1>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <button id="createFileBtn" class="create-btn">Create New File</button>

    <table class="file-table">
        <thead>
            <tr>
                <th>File Number</th>
                <th>Subject</th>
                <th>Created By</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($fileList)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No files found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($fileList as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['file_number']); ?></td>
                        <td><?php echo htmlspecialchars($file['subject']); ?></td>
                        <td><?php echo htmlspecialchars($file['created_by']); ?></td>
                        <td>
                            <span class="<?php echo $file['status'] === 'open' ? 'status-open' : 'status-closed'; ?>">
                                <?php echo ucfirst(htmlspecialchars($file['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                        <td>
                            <a href="view_file.php?id=<?php echo $file['id']; ?>" class="action-link">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- The Modal -->
<div id="createFileModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Create New File</h2>
    <form method="POST" action="file_manager.php">
        <input type="hidden" name="action" value="create_file">
        <div class="form-group">
            <label for="subject">File Subject:</label>
            <input type="text" id="subject" name="subject" required placeholder="e.g. Construction of Boundary Wall">
        </div>
        <button type="submit" class="create-btn">Create File</button>
    </form>
  </div>
</div>

<script>
    // Get the modal
    var modal = document.getElementById("createFileModal");

    // Get the button that opens the modal
    var btn = document.getElementById("createFileBtn");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks on the button, open the modal
    btn.onclick = function() {
        modal.style.display = "block";
        document.getElementById("subject").focus();
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

</body>
</html>
