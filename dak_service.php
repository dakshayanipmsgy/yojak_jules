<?php
require_once 'auth_check.php';
require_once 'functions.php';

if (!isset($_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role_id'] === 'admin.' . $deptId);

$dakPath = 'departments/' . $deptId . '/data/dak_register.json';
$dakRegister = readJSON($dakPath) ?? [];

$message = '';
$error = '';

$deptUsers = getUsers($deptId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_dak') {
        $type = $_POST['type'] ?? ''; // incoming, outgoing
        $sender = trim($_POST['sender'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $mode = $_POST['mode'] ?? '';
        $assignedTo = $_POST['assigned_to'] ?? '';

        if (empty($type) || empty($sender) || empty($subject) || empty($mode)) {
            $error = "All fields are required.";
        } else {
            // Generate ID
            $year = date('Y');
            $count = count($dakRegister) + 1;
            $refNo = 'DAK/' . strtoupper(substr($type, 0, 2)) . '/' . $year . '/' . sprintf('%03d', $count);

            $entry = [
                'ref_no' => $refNo,
                'type' => $type,
                'sender' => $sender,
                'subject' => $subject,
                'mode' => $mode,
                'assigned_to' => $assignedTo, // For Incoming
                'received_date' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'status' => 'logged'
            ];

            $dakRegister[] = $entry;
            if (writeJSON($dakPath, $dakRegister)) {
                $message = "Dak entry created: " . $refNo;
            } else {
                $error = "Failed to save Dak entry.";
            }
        }
    }
}

$incomingDak = array_filter($dakRegister, function($d) { return $d['type'] === 'incoming'; });
$outgoingDak = array_filter($dakRegister, function($d) { return $d['type'] === 'outgoing'; });

// Sort by date desc
usort($incomingDak, function($a, $b) { return strcmp($b['received_date'], $a['received_date']); });
usort($outgoingDak, function($a, $b) { return strcmp($b['received_date'], $a['received_date']); });

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dak Register - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dak-tabs { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .dak-tab { padding: 10px 20px; background: #eee; cursor: pointer; border-radius: 4px; }
        .dak-tab.active { background: #0056b3; color: white; }
        .dak-content { display: none; }
        .dak-content.active { display: block; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <main class="dashboard-content">
            <div class="section-header">
                <h2>Dak Register</h2>
                <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>New Dak Entry</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_dak">

                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" required>
                            <option value="incoming">Incoming (Received)</option>
                            <option value="outgoing">Outgoing (Sent)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sender / Ministry / Recipient</label>
                        <input type="text" name="sender" required placeholder="Who sent this or who is it for?">
                    </div>

                    <div class="form-group">
                        <label>Subject / File Name</label>
                        <input type="text" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label>Physical Mode</label>
                        <select name="mode" required>
                            <option value="Speed Post">Speed Post</option>
                            <option value="Courier">Courier</option>
                            <option value="Peon Book">Peon Book</option>
                            <option value="Hand">Hand Delivery</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned To (For Incoming)</label>
                        <select name="assigned_to">
                            <option value="">-- Select User --</option>
                            <?php foreach ($deptUsers as $uid => $u): ?>
                                <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary">Register Dak</button>
                </form>
            </div>

            <div class="dak-tabs">
                <div class="dak-tab active" onclick="showTab('incoming')">Incoming Dak</div>
                <div class="dak-tab" onclick="showTab('outgoing')">Outgoing Dak</div>
            </div>

            <div id="incoming" class="dak-content active">
                <div class="card">
                    <h3>Incoming Register</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ref No</th>
                                    <th>Sender</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Mode</th>
                                    <th>Assigned To</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($incomingDak)): ?>
                                    <tr><td colspan="7">No incoming dak recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($incomingDak as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['ref_no']); ?></td>
                                            <td><?php echo htmlspecialchars($d['sender']); ?></td>
                                            <td><?php echo htmlspecialchars($d['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($d['received_date']); ?></td>
                                            <td><?php echo htmlspecialchars($d['mode']); ?></td>
                                            <td>
                                                <?php
                                                    $uid = $d['assigned_to'];
                                                    echo htmlspecialchars($deptUsers[$uid]['full_name'] ?? $uid);
                                                ?>
                                            </td>
                                            <td>
                                                <a href="create_document.php?dak_ref=<?php echo urlencode($d['ref_no']); ?>&subject=<?php echo urlencode($d['subject']); ?>&sender=<?php echo urlencode($d['sender']); ?>" class="btn-small">Convert to Digital</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="outgoing" class="dak-content">
                <div class="card">
                    <h3>Outgoing Register</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ref No</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($outgoingDak)): ?>
                                    <tr><td colspan="5">No outgoing dak recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($outgoingDak as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['ref_no']); ?></td>
                                            <td><?php echo htmlspecialchars($d['sender']); ?></td>
                                            <td><?php echo htmlspecialchars($d['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($d['received_date']); ?></td>
                                            <td><?php echo htmlspecialchars($d['mode']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        function showTab(tabName) {
            var contents = document.getElementsByClassName('dak-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            document.getElementById(tabName).classList.add('active');

            var tabs = document.getElementsByClassName('dak-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            // Add active class to clicked tab (needs better selector logic or just text match)
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
