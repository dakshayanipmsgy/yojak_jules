<?php
session_start();
require_once 'functions.php';

// Security: Superadmin Only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    die("<h1>Access Denied</h1><p>Only Superadmin can access this utility.</p>");
}

$departments = getAllDepartments();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $deptId = $_POST['dept_id'] ?? '';
    $password = $_POST['password'] ?? '';

    // Verify Superadmin Password again for safety
    $config = readJSON('system/global_config.json');
    if (!password_verify($password, $config['password_hash'])) {
        $error = "Incorrect Password. Action aborted.";
    } else {
        if ($action === 'wipe_dept') {
            if (empty($deptId)) {
                $error = "Please select a department.";
            } else {
                if (wipeDepartmentData($deptId)) {
                    $message = "Department Data (Files, Tenders, Work Orders) wiped successfully for: " . htmlspecialchars($deptId);
                } else {
                    $error = "Failed to wipe some data.";
                }
            }
        } elseif ($action === 'factory_reset') {
            if (performFactoryReset()) {
                $message = "SYSTEM FACTORY RESET COMPLETE. All departments and data deleted.";
                // Refresh department list
                $departments = [];
            } else {
                $error = "Factory Reset Failed.";
            }
        }
    }
}

function wipeDepartmentData($deptId) {
    $basePath = STORAGE_PATH . '/departments/' . $deptId;
    if (!is_dir($basePath)) return false;

    // 1. Delete Documents/Files
    $filesDir = $basePath . '/files';
    if (is_dir($filesDir)) recursiveDelete($filesDir);
    mkdir($filesDir, 0755, true); // Recreate empty

    // 2. Delete Loose Documents
    $docsDir = $basePath . '/documents';
    if (is_dir($docsDir)) recursiveDelete($docsDir);
    mkdir($docsDir, 0755, true);

    // 3. Delete Data JSONs (Tenders, WOs, Dak)
    $dataDir = $basePath . '/data';
    $filesToDelete = ['tenders.json', 'work_orders.json', 'dak_register.json', 'dak_outgoing.json', 'notifications.json'];
    foreach ($filesToDelete as $f) {
        if (file_exists($dataDir . '/' . $f)) {
            unlink($dataDir . '/' . $f);
        }
    }

    // 4. Delete Uploads
    $uploadsDir = $basePath . '/uploads';
    if (is_dir($uploadsDir)) recursiveDelete($uploadsDir);
    mkdir($uploadsDir, 0755, true);

    return true;
}

function performFactoryReset() {
    $deptsPath = STORAGE_PATH . '/departments';

    // Delete all departments
    if (is_dir($deptsPath)) {
        recursiveDelete($deptsPath);
        mkdir($deptsPath, 0755, true);
    }

    // Delete Contractors
    $contractorsPath = STORAGE_PATH . '/global/data/contractors.json';
    if (file_exists($contractorsPath)) {
        unlink($contractorsPath);
        file_put_contents($contractorsPath, json_encode([], JSON_PRETTY_PRINT));
    }

    // Delete Global Uploads (Vaults)
    $globalUploads = STORAGE_PATH . '/global/uploads';
    if (is_dir($globalUploads)) {
        recursiveDelete($globalUploads);
        mkdir($globalUploads, 0755, true);
    }

    return true;
}

function recursiveDelete($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        (is_dir($path)) ? recursiveDelete($path) : unlink($path);
    }
    return rmdir($dir);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Reset Tool - Yojak</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .danger-zone {
            border: 2px solid #d32f2f;
            background: #fff5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .danger-title {
            color: #d32f2f;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-danger {
            background-color: #d32f2f;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-danger:hover {
            background-color: #b71c1c;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <h1>System Reset & Cleanup Tool</h1>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Option 1: Wipe Department Data -->
        <div class="danger-zone">
            <div class="danger-title">⚠️ Option 1: Wipe Department Data</div>
            <p>Deletes all Files, Tenders, Work Orders, and Dak for a specific department. <strong>Users and Settings are preserved.</strong> Use this to clear test data.</p>

            <form method="POST" onsubmit="return confirm('Are you sure you want to wipe data for this department?');">
                <input type="hidden" name="action" value="wipe_dept">

                <div class="form-group">
                    <label>Select Department:</label>
                    <select name="dept_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                                <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Confirm Superadmin Password:</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-danger">Wipe Data Only</button>
            </form>
        </div>

        <!-- Option 2: Factory Reset -->
        <div class="danger-zone" style="border-color: #000; background: #fafafa;">
            <div class="danger-title" style="color: #000;">☢️ Option 2: Factory Reset (Nuclear)</div>
            <p><strong>WARNING:</strong> This deletes EVERYTHING. All Departments, Users, Contractors, and Files will be lost. The system will return to a fresh install state.</p>

            <form method="POST" onsubmit="return confirm('CRITICAL WARNING: This will delete ALL DATA. Are you absolutely sure?');">
                <input type="hidden" name="action" value="factory_reset">

                <div class="form-group">
                    <label>Confirm Superadmin Password:</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-danger" style="background: #000;">PERFORM FACTORY RESET</button>
            </form>
        </div>

    </div>
</body>
</html>
