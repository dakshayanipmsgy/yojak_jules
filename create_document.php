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
            } else {
                $error = "Template file not found.";
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
            <h2>Generate New Document</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="generate" value="1">
                <div class="form-group">
                    <label>Select Template</label>
                    <select name="template_id" required>
                        <option value="">-- Choose Template --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Contractor</label>
                    <select name="contractor_id" required>
                        <option value="">-- Choose Contractor --</option>
                        <?php foreach ($contractors as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['mobile']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Generate Document</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="preview-actions no-print">
            <button onclick="window.print()" class="btn-primary">Print</button>
            <button class="btn-secondary" onclick="alert('Save as Draft logic coming soon!')">Save as Draft</button>
            <a href="create_document.php" class="btn-secondary">Start Over</a>
        </div>

        <div class="page">
            <?php echo $generatedHtml; ?>
        </div>
    <?php endif; ?>

</body>
</html>
