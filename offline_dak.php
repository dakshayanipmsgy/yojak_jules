<?php
require_once 'auth_check.php';
require_once 'functions.php';

enforceFeature('dak_register');

if (!isset($_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Path for Dak Register
$dakPath = 'departments/' . $deptId . '/data/dak_register.json';
$dakRegister = readJSON($dakPath) ?? [];

// Get Open Files for Dropdown
$filesDir = "storage/departments/$deptId/files/";
$openFiles = [];
if (is_dir($filesDir)) {
    $scan = glob($filesDir . 'FILE_*');
    if ($scan) {
        foreach ($scan as $path) {
            if (is_dir($path)) {
                $metaPath = $path . '/meta.json';
                if (file_exists($metaPath)) {
                    $meta = json_decode(file_get_contents($metaPath), true);
                    if (($meta['status'] ?? 'closed') === 'open') {
                        $meta['id'] = basename($path);
                        $openFiles[] = $meta;
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $sender = trim($_POST['sender'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $mode = $_POST['mode'] ?? '';
    $attachFileId = $_POST['attach_file'] ?? '';

    if (empty($type) || empty($sender) || empty($subject) || empty($mode)) {
        $error = "All fields are required.";
    } else {
        // Generate Dak ID
        $year = date('Y');
        $count = count($dakRegister) + 1;
        $refNo = 'DAK/' . strtoupper(substr($type, 0, 2)) . '/' . $year . '/' . sprintf('%03d', $count);

        $entry = [
            'ref_no' => $refNo,
            'type' => $type,
            'sender' => $sender,
            'subject' => $subject,
            'mode' => $mode,
            'source' => 'Physical/Offline',
            'received_date' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
            'status' => 'logged'
        ];

        // Attach to File Logic
        if (!empty($attachFileId)) {
            // Verify file exists and is open
            $fileFound = false;
            foreach ($openFiles as $f) {
                if ($f['id'] === $attachFileId) {
                    $fileFound = true;
                    break;
                }
            }

            if ($fileFound) {
                $entry['attached_to_file'] = $attachFileId;

                // Create Document in File
                $docId = generateDocumentID($deptId);
                $docData = [
                    'id' => $docId,
                    'title' => "Offline Dak: " . $subject,
                    'type' => 'offline_entry',
                    'status' => 'final',
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $userId,
                    'content' => "Ref No: $refNo\nSender/Receiver: $sender\nMode: $mode\nSubject: $subject\n\n(This is a record of physical mail.)",
                    'dak_ref' => $refNo
                ];

                $docPath = 'departments/' . $deptId . '/files/' . $attachFileId . '/documents/' . $docId . '.json';

                // Ensure documents directory exists
                $absDocPath = STORAGE_PATH . '/' . $docPath;
                $docDir = dirname($absDocPath);
                if (!is_dir($docDir)) {
                    mkdir($docDir, 0777, true);
                }

                if (writeJSON($docPath, $docData)) {
                    $entry['attached_doc_id'] = $docId;
                } else {
                     $error = "Dak logged, but failed to attach to file.";
                }
            }
        }

        $dakRegister[] = $entry;
        if (writeJSON($dakPath, $dakRegister)) {
            $message = "Offline Dak registered successfully: " . $refNo;

            // Notification
            $notifPath = 'departments/' . $deptId . '/data/notifications.json';
            $notifList = readJSON($notifPath) ?? [];
            $notifList[] = [
                'id' => generateID('NOTIF'),
                'user_id' => $userId,
                'message' => "Logged Offline Dak: $refNo",
                'link' => 'dak_register.php',
                'read' => false,
                'time' => date('Y-m-d H:i:s')
            ];
            writeJSON($notifPath, $notifList);

        } else {
            $error = "Failed to save Dak entry.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log Offline Dak - Yojak</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <div class="section-header">
            <h2>Log Offline Dak</h2>
            <a href="dak_register.php" class="btn-secondary">Back to Register</a>
        </div>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="incoming">Incoming (Received)</option>
                        <option value="outgoing">Outgoing (Sent)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Sender / Receiver Name & Address</label>
                    <input type="text" name="sender" required placeholder="e.g. Municipal Corp, Zone 4">
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required placeholder="e.g. Letter regarding road repair">
                </div>

                <div class="form-group">
                    <label>Mode</label>
                    <select name="mode" required>
                        <option value="Hand">Hand Delivery</option>
                        <option value="Post">Post (Speed/Regd)</option>
                        <option value="Courier">Courier</option>
                        <option value="Peon Book">Peon Book</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Attach to File (Optional)</label>
                    <select name="attach_file">
                        <option value="">-- None --</option>
                        <?php foreach ($openFiles as $f): ?>
                            <option value="<?php echo htmlspecialchars($f['id']); ?>">
                                <?php echo htmlspecialchars($f['file_number'] . ' - ' . $f['subject']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>If selected, this entry will be added to the file's timeline.</small>
                </div>

                <button type="submit" class="btn-primary">Register Offline Dak</button>
            </form>
        </div>
    </div>
</body>
</html>
