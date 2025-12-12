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

$dakPath = 'departments/' . $deptId . '/data/dak_register.json';
$dakRegister = readJSON($dakPath) ?? [];

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
    <title>Unified Dak Register - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dak-tabs { display: flex; gap: 1rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; }
        .dak-tab { padding: 10px 20px; cursor: pointer; border-radius: 4px 4px 0 0; background: #f8f9fa; border: 1px solid #ddd; border-bottom: none; margin-bottom: -2px; }
        .dak-tab.active { background: #0056b3; color: white; border-color: #0056b3; }
        .dak-content { display: none; }
        .dak-content.active { display: block; }
        .hidden { display: none; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 5px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .detail-row { margin-bottom: 10px; }
        .detail-label { font-weight: bold; width: 150px; display: inline-block; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <main class="dashboard-content">
            <div class="section-header">
                <h2>Unified Dak Register</h2>
                <div>
                    <a href="offline_dak.php" class="btn-primary">+ Log Offline Dak</a>
                    <a href="dashboard.php" class="btn-secondary">Dashboard</a>
                </div>
            </div>

            <div class="dak-tabs">
                <div class="dak-tab active" onclick="showTab('incoming')">Incoming Dak</div>
                <div class="dak-tab" onclick="showTab('outgoing')">Outgoing Dak</div>
            </div>

            <!-- INCOMING TAB -->
            <div id="incoming" class="dak-content active">
                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Dak Ref No.</th>
                                    <th>Source</th>
                                    <th>Sender</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($incomingDak)): ?>
                                    <tr><td colspan="6">No incoming dak recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($incomingDak as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['ref_no']); ?></td>
                                            <td>
                                                <?php
                                                    $source = $d['source'] ?? 'Physical/Offline';
                                                    echo ($source === 'Digital File') ? '💻 Digital File' : '📄 Physical/Offline';
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['sender']); ?></td>
                                            <td><?php echo htmlspecialchars($d['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($d['received_date']); ?></td>
                                            <td>
                                                <?php if (($d['source'] ?? '') === 'Digital File'): ?>
                                                    <a href="view_file.php?id=<?php echo htmlspecialchars($d['file_id'] ?? ''); ?>" class="btn-small">View File</a>
                                                <?php else: ?>
                                                    <button class="btn-small" onclick='openModal(<?php echo json_encode($d); ?>)'>View Details</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- OUTGOING TAB -->
            <div id="outgoing" class="dak-content">
                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Dak Ref No.</th>
                                    <th>Source</th>
                                    <th>Receiver</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($outgoingDak)): ?>
                                    <tr><td colspan="6">No outgoing dak recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($outgoingDak as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['ref_no']); ?></td>
                                            <td>
                                                <?php
                                                    $source = $d['source'] ?? 'Physical/Offline';
                                                    echo ($source === 'Digital File') ? '💻 Digital File' : '📄 Physical/Offline';
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['sender']); // Assuming sender field holds receiver name for outgoing as per dak_service logic ?></td>
                                            <td><?php echo htmlspecialchars($d['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($d['received_date']); ?></td>
                                            <td>
                                                <?php if (($d['source'] ?? '') === 'Digital File'): ?>
                                                    <a href="view_file.php?id=<?php echo htmlspecialchars($d['file_id'] ?? ''); ?>" class="btn-small">View File</a>
                                                <?php else: ?>
                                                    <button class="btn-small" onclick='openModal(<?php echo json_encode($d); ?>)'>View Details</button>
                                                <?php endif; ?>
                                            </td>
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

    <!-- Modal -->
    <div id="dakModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Dak Details</h3>
            <div id="modalBody"></div>
        </div>
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
            event.target.classList.add('active');
        }

        function openModal(data) {
            var modal = document.getElementById("dakModal");
            var body = document.getElementById("modalBody");

            var html = '';
            html += '<div class="detail-row"><span class="detail-label">Ref No:</span> ' + (data.ref_no || '') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">Type:</span> ' + (data.type || '') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">Source:</span> ' + (data.source || 'Physical/Offline') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">' + (data.type === 'outgoing' ? 'Receiver' : 'Sender') + ':</span> ' + (data.sender || '') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">Subject:</span> ' + (data.subject || '') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">Mode:</span> ' + (data.mode || '') + '</div>';
            html += '<div class="detail-row"><span class="detail-label">Date:</span> ' + (data.received_date || '') + '</div>';

            if (data.attached_to_file) {
                html += '<div class="detail-row"><span class="detail-label">Attached to File:</span> <a href="view_file.php?id=' + data.attached_to_file + '">View File</a></div>';
            }

            body.innerHTML = html;
            modal.style.display = "block";
        }

        function closeModal() {
            document.getElementById("dakModal").style.display = "none";
        }

        window.onclick = function(event) {
            var modal = document.getElementById("dakModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
