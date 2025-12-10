<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Ensure Superadmin
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
$configPath = 'system/global_config.json';
$config = readJSON($configPath);

if (!$config) {
    die("Error: Global configuration not found.");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $openaiKey = trim($_POST['openai_key'] ?? '');
    $openaiModel = trim($_POST['openai_model'] ?? 'gpt-4o');
    $geminiKey = trim($_POST['gemini_key'] ?? '');
    $geminiModel = trim($_POST['gemini_model'] ?? 'gemini-1.5-flash');

    if (empty($openaiModel)) $openaiModel = 'gpt-4o';
    if (empty($geminiModel)) $geminiModel = 'gemini-1.5-flash';

    // Update config, preserving keys if input is empty
    $currentOpenAIKey = $config['ai_config']['openai']['key'] ?? '';
    $currentGeminiKey = $config['ai_config']['gemini']['key'] ?? '';

    $newOpenAIKey = (!empty($openaiKey)) ? $openaiKey : $currentOpenAIKey;
    $newGeminiKey = (!empty($geminiKey)) ? $geminiKey : $currentGeminiKey;

    $config['ai_config'] = [
        'openai' => [
            'key' => $newOpenAIKey,
            'model' => $openaiModel
        ],
        'gemini' => [
            'key' => $newGeminiKey,
            'model' => $geminiModel
        ]
    ];

    if (writeJSON($configPath, $config)) {
        $message = "Settings updated successfully.";
    } else {
        $error = "Failed to save settings.";
    }
}

// Load current values
$aiConfig = $config['ai_config'] ?? [
    'openai' => ['key' => '', 'model' => 'gpt-4o'],
    'gemini' => ['key' => '', 'model' => 'gemini-1.5-flash']
];

$hasOpenAIKey = !empty($aiConfig['openai']['key']);
$hasGeminiKey = !empty($aiConfig['gemini']['key']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .form-section h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        .key-status {
            font-size: 0.85em;
            margin-top: 5px;
            display: block;
        }
        .status-set { color: green; }
        .status-missing { color: red; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="settings-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h1>System Configuration</h1>
            <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-section">
                <h3>AI Configuration</h3>
                <p style="margin-bottom:1rem; color:#666;">Configure API keys and model identifiers for the "Draft with AI" feature.</p>

                <h4>OpenAI (ChatGPT)</h4>
                <div class="form-group">
                    <label for="openai_key">API Key</label>
                    <input type="password" id="openai_key" name="openai_key" value="" placeholder="<?php echo $hasOpenAIKey ? 'Leave empty to keep current key' : 'Enter API Key (sk-...)'; ?>">
                    <?php if ($hasOpenAIKey): ?>
                        <span class="key-status status-set">✓ Key is currently set</span>
                    <?php else: ?>
                        <span class="key-status status-missing">✕ Key is missing</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="openai_model">Model Code</label>
                    <input type="text" id="openai_model" name="openai_model" value="<?php echo htmlspecialchars($aiConfig['openai']['model'] ?? 'gpt-4o'); ?>" placeholder="e.g. gpt-4o">
                </div>

                <hr style="border:0; border-top:1px solid #eee; margin: 1.5rem 0;">

                <h4>Google Gemini</h4>
                <div class="form-group">
                    <label for="gemini_key">API Key</label>
                    <input type="password" id="gemini_key" name="gemini_key" value="" placeholder="<?php echo $hasGeminiKey ? 'Leave empty to keep current key' : 'Enter API Key'; ?>">
                     <?php if ($hasGeminiKey): ?>
                        <span class="key-status status-set">✓ Key is currently set</span>
                    <?php else: ?>
                        <span class="key-status status-missing">✕ Key is missing</span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="gemini_model">Model Code</label>
                    <input type="text" id="gemini_model" name="gemini_model" value="<?php echo htmlspecialchars($aiConfig['gemini']['model'] ?? 'gemini-1.5-flash'); ?>" placeholder="e.g. gemini-1.5-flash">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

</body>
</html>
