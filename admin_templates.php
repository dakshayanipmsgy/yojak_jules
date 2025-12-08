<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Superadmin Only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    die("Access Denied. Superadmin only.");
}

$departments = getAllDepartments();
$systemTemplatesDir = 'system/templates/';
$systemTemplatesJson = 'system/templates/templates.json';

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scope = $_POST['scope'] ?? 'universal';
    $targetDeptId = $_POST['dept_id'] ?? '';

    // Determine target paths
    if ($scope === 'universal') {
        $jsonPath = $systemTemplatesJson;
        $dirPath = $systemTemplatesDir;
    } else {
        if (empty($targetDeptId)) {
            $error = "Department selection is required for Department Specific templates.";
        } else {
            $jsonPath = 'departments/' . $targetDeptId . '/templates/templates.json';
            $dirPath = 'departments/' . $targetDeptId . '/templates/';
        }
    }

    if (empty($error)) {
        $templates = readJSON($jsonPath) ?? [];

        if ($action === 'create' || $action === 'update') {
            $id = $_POST['id'] ?? uniqid('tpl_');
            // Sanitize ID (Alphanumeric, underscore, dash)
            $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);

            $title = trim($_POST['title']);
            $content = $_POST['content'];

            if (empty($title) || empty($content) || empty($id)) {
                $error = "Title, Content, and valid ID are required.";
            } else {
                $filename = $id . '.html';
                $fullDir = STORAGE_PATH . '/' . $dirPath;

                if (!is_dir($fullDir)) {
                     mkdir($fullDir, 0755, true);
                }

                if (file_put_contents($fullDir . $filename, $content) !== false) {
                     $templates[$id] = [
                         'id' => $id,
                         'title' => $title,
                         'filename' => $filename,
                         'updated_at' => date('Y-m-d H:i:s'),
                         'is_universal' => ($scope === 'universal')
                     ];

                     if (writeJSON($jsonPath, $templates)) {
                         $message = "Template saved successfully (" . ucfirst($scope) . ").";
                     } else {
                         $error = "Failed to save template metadata.";
                     }
                } else {
                    $error = "Failed to save template content.";
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? '';
            if (isset($templates[$id])) {
                $filename = $templates[$id]['filename'];
                unset($templates[$id]);
                if (writeJSON($jsonPath, $templates)) {
                    @unlink(STORAGE_PATH . '/' . $dirPath . $filename);
                    $message = "Template deleted.";
                } else {
                    $error = "Failed to delete template.";
                }
            }
        }
    }
}

// Data Fetching for View
$universalTemplates = readJSON($systemTemplatesJson) ?? [];
$selectedDeptId = $_GET['view_dept'] ?? '';
$deptTemplates = [];
if ($selectedDeptId) {
    $deptTemplates = readJSON('departments/' . $selectedDeptId . '/templates/templates.json') ?? [];
}

// Edit Mode
$editTemplate = null;
$editContent = '';
$editScope = 'universal';
$editDeptId = '';

if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editScope = $_GET['scope'] ?? 'universal';
    $editDeptId = $_GET['dept'] ?? '';

    if ($editScope === 'universal') {
        if (isset($universalTemplates[$editId])) {
            $editTemplate = $universalTemplates[$editId];
            $path = STORAGE_PATH . '/' . $systemTemplatesDir . $editTemplate['filename'];
            if (file_exists($path)) $editContent = file_get_contents($path);
        }
    } else {
        if ($editDeptId) {
             $localTpls = readJSON('departments/' . $editDeptId . '/templates/templates.json') ?? [];
             if (isset($localTpls[$editId])) {
                 $editTemplate = $localTpls[$editId];
                 $path = STORAGE_PATH . '/departments/' . $editDeptId . '/templates/' . $editTemplate['filename'];
                 if (file_exists($path)) $editContent = file_get_contents($path);
             }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Templates - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        textarea.template-editor {
            width: 100%;
            height: 300px;
            font-family: monospace;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Superadmin Template Manager</h1>
        </div>
        <div class="header-right">
             <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Editor -->
        <div class="form-card">
            <h2><?php echo $editTemplate ? 'Edit Template' : 'Create New Template'; ?></h2>
            <form method="POST" action="admin_templates.php">
                <input type="hidden" name="action" value="<?php echo $editTemplate ? 'update' : 'create'; ?>">
                <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editTemplate['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Scope</label>
                    <select name="scope" id="scopeSelect" onchange="toggleDeptSelect()" required>
                        <option value="universal" <?php if($editScope === 'universal') echo 'selected'; ?>>Universal (Global)</option>
                        <option value="department" <?php if($editScope === 'department') echo 'selected'; ?>>Department Specific</option>
                    </select>
                </div>

                <div class="form-group" id="deptSelectGroup" style="display: <?php echo $editScope === 'department' ? 'block' : 'none'; ?>;">
                    <label>Target Department</label>
                    <select name="dept_id" id="deptSelect">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php if($editDeptId === $d['id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?> (<?php echo $d['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Template Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($editTemplate['title'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>HTML Content</label>
                    <textarea name="content" class="template-editor" required><?php echo htmlspecialchars($editContent); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editTemplate ? 'Update' : 'Save'; ?></button>
                    <a href="admin_templates.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- List Universal -->
        <div class="form-card">
            <h2>Universal Templates</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($universalTemplates)): ?>
                            <tr><td colspan="3">No universal templates.</td></tr>
                        <?php else: ?>
                            <?php foreach ($universalTemplates as $t): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['title']); ?> <span class="badge">[Global]</span></td>
                                    <td><?php echo htmlspecialchars($t['updated_at']); ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $t['id']; ?>&scope=universal" class="btn-small">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this global template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="scope" value="universal">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- List Department Specific -->
        <div class="form-card">
            <div class="section-header">
                <h2>Department Templates</h2>
                <form method="GET" action="" style="display:inline;">
                    <select name="view_dept" onchange="this.form.submit()" style="padding: 5px;">
                        <option value="">-- Select to View --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php if($selectedDeptId === $d['id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selectedDeptId): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deptTemplates)): ?>
                                <tr><td colspan="3">No templates found for this department.</td></tr>
                            <?php else: ?>
                                <?php foreach ($deptTemplates as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['title']); ?></td>
                                        <td><?php echo htmlspecialchars($t['updated_at']); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $t['id']; ?>&scope=department&dept=<?php echo $selectedDeptId; ?>" class="btn-small">Edit</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="scope" value="department">
                                                <input type="hidden" name="dept_id" value="<?php echo $selectedDeptId; ?>">
                                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                <button type="submit" class="btn-small btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Select a department to view and manage its templates.</p>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function toggleDeptSelect() {
            var scope = document.getElementById('scopeSelect').value;
            var deptGroup = document.getElementById('deptSelectGroup');
            deptGroup.style.display = (scope === 'department') ? 'block' : 'none';
        }
        // Run on load
        toggleDeptSelect();
    </script>
</body>
</html>
