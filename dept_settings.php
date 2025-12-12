<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Department Admin Only
if (strpos($_SESSION['role_id'], 'admin.') !== 0) {
    http_response_code(403);
    die("<h1>Access Denied</h1><p>Only Department Administrators can access this page.</p>");
}

$deptId = $_SESSION['dept_id'];
$settingsPath = 'departments/' . $deptId . '/data/settings.json';

// Initialize Settings if not exists
$settings = readJSON($settingsPath);
if ($settings === null) {
    $settings = [
        'doc_prefix' => 'DOC/' . date('Y') . '/',
        'doc_counter' => 1,
        'wo_prefix' => 'WO/' . date('Y') . '/',
        'wo_counter' => 1,
        'office_name' => ''
    ];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['doc_prefix'] = trim($_POST['doc_prefix'] ?? '');
    $settings['doc_counter'] = (int)($_POST['doc_counter'] ?? 1);
    $settings['wo_prefix'] = trim($_POST['wo_prefix'] ?? '');
    $settings['wo_counter'] = (int)($_POST['wo_counter'] ?? 1);
    $settings['office_name'] = trim($_POST['office_name'] ?? '');

    if (writeJSON($settingsPath, $settings)) {
        $message = "Settings saved successfully.";
    } else {
        $error = "Failed to save settings.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Settings - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .form-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .preview-box {
            background: #f8f9fa;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            margin-top: 0.5rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Department Settings</h1>
        </div>
    </div>

    <div class="settings-container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-section">
                <h3>General Settings</h3>
                <div class="form-group">
                    <label>Office Name (for Headers)</label>
                    <input type="text" name="office_name" value="<?php echo htmlspecialchars($settings['office_name'] ?? ''); ?>" placeholder="e.g. Office of the Executive Engineer, Road Div">
                    <div class="help-text">This name will appear on official document headers.</div>
                </div>
            </div>

            <div class="form-section">
                <h3>Document Numbering</h3>
                <div class="form-group">
                    <label>Document Prefix</label>
                    <input type="text" name="doc_prefix" id="doc_prefix" value="<?php echo htmlspecialchars($settings['doc_prefix'] ?? ''); ?>" required>
                    <div class="help-text">Example: DWS/LET/2025/</div>
                </div>
                <div class="form-group">
                    <label>Running Counter</label>
                    <input type="number" name="doc_counter" id="doc_counter" value="<?php echo (int)($settings['doc_counter'] ?? 1); ?>" required min="1">
                    <div class="help-text">The next document will start from this number.</div>
                </div>
                <div class="help-text">Preview: <span class="preview-box" id="doc_preview"></span></div>
            </div>

            <div class="form-section">
                <h3>Work Order Numbering</h3>
                <div class="form-group">
                    <label>Work Order Prefix</label>
                    <input type="text" name="wo_prefix" id="wo_prefix" value="<?php echo htmlspecialchars($settings['wo_prefix'] ?? ''); ?>" required>
                    <div class="help-text">Example: DWS/WO/2025/</div>
                </div>
                <div class="form-group">
                    <label>Running Counter</label>
                    <input type="number" name="wo_counter" id="wo_counter" value="<?php echo (int)($settings['wo_counter'] ?? 1); ?>" required min="1">
                    <div class="help-text">The next work order will start from this number.</div>
                </div>
                <div class="help-text">Preview: <span class="preview-box" id="wo_preview"></span></div>
            </div>

            <button type="submit" class="btn-primary">Save Settings</button>
        </form>
    </div>

    <script>
        function updatePreviews() {
            // Docs
            const docPrefix = document.getElementById('doc_prefix').value;
            const docCounter = document.getElementById('doc_counter').value;
            // Pad counter to 3 digits? Prompt example: DWS/LET/2025/001
            const paddedDocCounter = docCounter.padStart(3, '0');
            document.getElementById('doc_preview').textContent = docPrefix + paddedDocCounter;

            // WOs
            const woPrefix = document.getElementById('wo_prefix').value;
            const woCounter = document.getElementById('wo_counter').value;
            // Prompt example: DWS/WO/2025/05 (2 digits?)
            // Let's assume 2 or 3. I'll stick to at least 2 for now or just 3 for consistency.
            // "05" suggests 2.
            const paddedWoCounter = woCounter.padStart(2, '0');
            document.getElementById('wo_preview').textContent = woPrefix + paddedWoCounter;
        }

        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', updatePreviews);
        });

        updatePreviews();
    </script>
</body>
</html>
