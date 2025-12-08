<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$roleId = $_SESSION['role_id'];
$isDeptAdmin = ($roleId === 'admin.' . $deptId);

if (!$isDeptAdmin) {
    die("Access Denied. Only Department Administrators can manage templates.");
}

$templatesJsonPath = 'departments/' . $deptId . '/templates/templates.json';
$templatesDir = 'departments/' . $deptId . '/templates/';
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $templates = readJSON($templatesJsonPath) ?? [];

    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? uniqid('tpl_');
        $title = trim($_POST['title']);
        $content = $_POST['content']; // HTML content

        if (empty($title) || empty($content)) {
            $error = "Title and Content are required.";
        } else {
            // Save HTML file
            // Use ID for filename to avoid issues
            $filename = $id . '.html';

            // Note: writeJSON handles dir creation, but here we use file_put_contents.
            // We should check dir existence using writeJSON trick or manually.
            // Actually, writeJSON is for JSON. I should check dir existence.
            $fullTemplatesDir = STORAGE_PATH . '/' . $templatesDir;
            if (!is_dir($fullTemplatesDir)) {
                 mkdir($fullTemplatesDir, 0755, true);
            }

            if (file_put_contents($fullTemplatesDir . $filename, $content) !== false) {
                 // Update metadata
                 $templates[$id] = [
                     'id' => $id,
                     'title' => $title,
                     'filename' => $filename,
                     'updated_at' => date('Y-m-d H:i:s')
                 ];

                 if (writeJSON($templatesJsonPath, $templates)) {
                     $message = "Template saved successfully.";
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
            if (writeJSON($templatesJsonPath, $templates)) {
                // Try delete file
                @unlink(STORAGE_PATH . '/' . $templatesDir . $filename);
                $message = "Template deleted.";
            } else {
                $error = "Failed to delete template.";
            }
        }
    }
}

$templates = readJSON($templatesJsonPath) ?? [];

// Edit Mode
$editTemplate = null;
$editContent = '';
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    if (isset($templates[$editId])) {
        $editTemplate = $templates[$editId];
        $contentPath = STORAGE_PATH . '/' . $templatesDir . $editTemplate['filename'];
        if (file_exists($contentPath)) {
            $editContent = file_get_contents($contentPath);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Templates - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .template-list { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        textarea.template-editor {
            width: 100%;
            height: 300px;
            font-family: monospace;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .help-text {
            background: #f9f9f9;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Manage Templates</h1>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2><?php echo $editTemplate ? 'Edit Template' : 'Create New Template'; ?></h2>

            <div class="help-text">
                <strong>Available Variables:</strong> Use <code>{{contractor_name}}</code>, <code>{{contractor_address}}</code>, <code>{{current_date}}</code>.
            </div>

            <form method="POST" action="manage_templates.php">
                <input type="hidden" name="action" value="<?php echo $editTemplate ? 'update' : 'create'; ?>">
                <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editTemplate['id']); ?>">
                <?php endif; ?>

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
                    <?php if ($editTemplate): ?>
                        <a href="manage_templates.php" class="btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="template-list">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Filename</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="4" class="text-center">No templates found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['filename']); ?></td>
                                <td><?php echo htmlspecialchars($t['updated_at']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $t['id']; ?>" class="btn-small">Edit</a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete">
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
</body>
</html>
