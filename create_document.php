<?php
session_start();
require_once 'functions.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$message = '';
$error = '';
$generatedHtml = '';

// Load Data for Dropdowns
$contractors = readJSON('departments/' . $deptId . '/data/contractors.json') ?? [];
$templates = readJSON('departments/' . $deptId . '/templates/templates.json') ?? [];

// Handle Edit Mode
$editDocId = $_GET['edit_doc_id'] ?? '';
$editDoc = null;
if ($editDocId) {
    $editDoc = getDocument($deptId, $editDocId);
    if ($editDoc && $editDoc['status'] === 'Draft' && $editDoc['created_by'] === $_SESSION['user_id']) {
        // Valid edit
    } else {
        $editDoc = null; // Invalid or not allowed
        $error = "Cannot edit this document.";
    }
}

// Handle Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $templateId = $_POST['template_id'] ?? '';
    $contractorId = $_POST['contractor_id'] ?? '';

    if (empty($templateId) || empty($contractorId)) {
        $error = "Please select both a template and a contractor.";
    } else {
        if (!isset($templates[$templateId]) || !isset($contractors[$contractorId])) {
            $error = "Invalid selection.";
        } else {
            $templateData = $templates[$templateId];
            $contractorData = $contractors[$contractorId];

            // Load Template Content
            $templatePath = STORAGE_PATH . '/departments/' . $deptId . '/templates/' . $templateData['filename'];
            if (file_exists($templatePath)) {
                $content = file_get_contents($templatePath);

                // Replacements
                $replacements = [
                    '{{contractor_name}}' => $contractorData['name'],
                    '{{contractor_address}}' => $contractorData['address'],
                    '{{current_date}}' => date('d-m-Y')
                ];
                // Add more fields just in case
                $replacements['{{mobile}}'] = $contractorData['mobile'];
                $replacements['{{pan}}'] = $contractorData['pan'];
                $replacements['{{gst}}'] = $contractorData['gst'];

                // Department name replacement?
                // The prompt example used {{department_name}}.
                // Let's get department name.
                $deptMeta = getDepartment($deptId);
                $replacements['{{department_name}}'] = $deptMeta['name'] ?? 'Department';

                $generatedHtml = str_replace(array_keys($replacements), array_values($replacements), $content);

                // Keep the titles to pass to save logic
                $documentTitle = $templateData['title'] . ' - ' . $contractorData['name'];

            } else {
                $error = "Template file not found.";
            }
        }
    }
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_document'])) {
    $title = $_POST['title'] ?? 'Untitled Document';
    $htmlContent = $_POST['html_content'] ?? '';
    $templateIdSaved = $_POST['template_id'] ?? '';
    $contractorIdSaved = $_POST['contractor_id'] ?? '';
    $existingDocId = $_POST['existing_doc_id'] ?? '';

    if (empty($htmlContent)) {
        $error = "No content to save.";
    } else {
        $userId = $_SESSION['user_id'];

        if ($existingDocId) {
            // Update existing
            $docId = $existingDocId;
            $docData = getDocument($deptId, $docId);
            if (!$docData) {
                die("Error: Document not found.");
            }
            // Update fields
            $docData['title'] = $title;
            $docData['content'] = $htmlContent;
            $docData['template_id'] = $templateIdSaved;
            $docData['contractor_id'] = $contractorIdSaved;
            // Don't change created_by, created_at, or status (assumed still Draft if we are here)

            $docData['history'][] = [
                'action' => 'updated',
                'from' => $userId,
                'to' => $userId,
                'time' => date('Y-m-d H:i:s')
            ];

        } else {
            // Create New
            $docId = generateDocumentID();
            $docData = [
                'id' => $docId,
                'title' => $title,
                'content' => $htmlContent,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'current_owner' => $userId,
                'status' => 'Draft',
                'history' => [],
                'template_id' => $templateIdSaved,
                'contractor_id' => $contractorIdSaved
            ];

            // Initial Log
            $docData['history'][] = [
                'action' => 'created',
                'from' => $userId,
                'to' => $userId,
                'time' => date('Y-m-d H:i:s')
            ];
        }

        if (saveDocument($deptId, $docId, $docData)) {
            $message = "Document saved as Draft. ID: $docId";
            // Redirect to dashboard or view page
            header("Location: view_document.php?id=$docId");
            exit;
        } else {
            $error = "Failed to save document.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Document - Yojak</title>
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
            color: black; /* Ensure text is black for printing */
            box-sizing: border-box; /* Important for padding */
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

        .selection-panel {
            background: white;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preview-actions {
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-header no-print">
        <div class="header-left">
            <h1>Create Document</h1>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </div>

    <?php if (!$generatedHtml): ?>
        <div class="selection-panel no-print">
            <h2><?php echo $editDoc ? 'Edit Document: ' . htmlspecialchars($editDoc['title']) : 'Generate New Document'; ?></h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="generate" value="1">
                <?php if ($editDocId): ?>
                    <input type="hidden" name="existing_doc_id" value="<?php echo htmlspecialchars($editDocId); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Select Template</label>
                    <select name="template_id" required>
                        <option value="">-- Choose Template --</option>
                        <?php foreach ($templates as $t): ?>
                            <?php
                                $selected = '';
                                if (isset($_POST['template_id']) && $_POST['template_id'] == $t['id']) $selected = 'selected';
                                elseif ($editDoc && isset($editDoc['template_id']) && $editDoc['template_id'] == $t['id']) $selected = 'selected';
                            ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($t['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Contractor</label>
                    <select name="contractor_id" required>
                        <option value="">-- Choose Contractor --</option>
                        <?php foreach ($contractors as $c): ?>
                            <?php
                                $selected = '';
                                if (isset($_POST['contractor_id']) && $_POST['contractor_id'] == $c['id']) $selected = 'selected';
                                elseif ($editDoc && isset($editDoc['contractor_id']) && $editDoc['contractor_id'] == $c['id']) $selected = 'selected';
                            ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['mobile']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editDoc ? 'Update Preview' : 'Generate Document'; ?></button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="preview-actions no-print">
            <button onclick="window.print()" class="btn-primary">Print</button>

            <form method="POST" action="" style="display:inline-block;">
                <input type="hidden" name="save_document" value="1">
                <input type="hidden" name="html_content" value="<?php echo htmlspecialchars($generatedHtml); ?>">
                <input type="hidden" name="title" value="<?php echo htmlspecialchars($documentTitle ?? 'New Document'); ?>">
                <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($templateId); ?>">
                <input type="hidden" name="contractor_id" value="<?php echo htmlspecialchars($contractorId); ?>">
                <?php if ($editDocId): ?>
                    <input type="hidden" name="existing_doc_id" value="<?php echo htmlspecialchars($editDocId); ?>">
                <?php endif; ?>

                <button type="submit" class="btn-secondary"><?php echo $editDocId ? 'Update Draft' : 'Save as Draft'; ?></button>
            </form>

            <a href="create_document.php" class="btn-secondary">Start Over</a>
        </div>

        <div class="page">
            <?php echo $generatedHtml; ?>
        </div>
    <?php endif; ?>

</body>
</html>
