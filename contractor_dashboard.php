<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor') {
    header("Location: contractor_login.php");
    exit;
}

$deptId = $_SESSION['dept_id'];
$contractorId = $_SESSION['user_id'];
$contractorName = $_SESSION['user_name'];

// Data Paths
$tendersPath = 'departments/' . $deptId . '/data/tenders.json';
$workOrdersPath = 'departments/' . $deptId . '/data/work_orders.json';

// Fetch Data
$allTenders = readJSON($tendersPath) ?? [];
$allWorkOrders = readJSON($workOrdersPath) ?? [];

// Filter Data
$myTenders = [];
foreach ($allTenders as $tId => $t) {
    // Check if contractor is in participants
    // Structure of tender might vary, assuming 'participants' is an array of IDs or objects
    // Need to verify 'participants' structure.
    // Usually it's an array of contractor IDs or objects with 'id' key.
    // Let's assume broad check for now.

    $isParticipant = false;
    if (isset($t['participants']) && is_array($t['participants'])) {
        foreach ($t['participants'] as $p) {
            if (is_array($p) && isset($p['id']) && $p['id'] === $contractorId) {
                $isParticipant = true;
                break;
            } elseif (is_string($p) && $p === $contractorId) {
                $isParticipant = true;
                break;
            }
        }
    }

    if ($isParticipant) {
        $myTenders[$tId] = $t;
    }
}

$myWorkOrders = [];
foreach ($allWorkOrders as $woId => $wo) {
    // Check contractor_id match
    if (isset($wo['contractor_id']) && $wo['contractor_id'] === $contractorId) {
        $myWorkOrders[$woId] = $wo;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractor Dashboard - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .welcome-banner { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .welcome-text h2 { margin: 0; color: var(--primary-color); }
        .welcome-text p { margin: 5px 0 0; color: #666; }

        .tabs { overflow: hidden; border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .tab-btn { background-color: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 14px 16px; transition: 0.3s; font-size: 16px; font-weight: 600; color: #555; border-bottom: 3px solid transparent; }
        .tab-btn:hover { background-color: #ddd; }
        .tab-btn.active { border-bottom: 3px solid var(--primary-color); color: var(--primary-color); }

        .tab-content { display: none; animation: fadeEffect 1s; }
        @keyframes fadeEffect { from {opacity: 0;} to {opacity: 1;} }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1.5rem; transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .card h3 { margin-top: 0; font-size: 18px; color: #333; }
        .card .meta { color: #777; font-size: 14px; margin-bottom: 10px; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-issued { background-color: #e3f2fd; color: #1976d2; }
        .status-pending { background-color: #fff3e0; color: #f57c00; }

        table.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table.data-table th, table.data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table.data-table th { background-color: #f8f9fa; font-weight: 600; color: #444; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Welcome, <?php echo htmlspecialchars($contractorName); ?></h2>
                <p>Contractor ID: <?php echo htmlspecialchars($contractorId); ?></p>
            </div>
            <div>
                <a href="logout.php" class="btn-secondary">Logout</a>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab(event, 'WorkOrders')">My Work Orders (<?php echo count($myWorkOrders); ?>)</button>
            <button class="tab-btn" onclick="openTab(event, 'Tenders')">My Tenders (<?php echo count($myTenders); ?>)</button>
        </div>

        <div id="WorkOrders" class="tab-content" style="display: block;">
            <?php if (empty($myWorkOrders)): ?>
                <div class="card" style="text-align:center; color:#777;">No active work orders found.</div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($myWorkOrders as $woId => $wo): ?>
                        <div class="card">
                            <div class="meta">WO #: <?php echo htmlspecialchars($woId); ?></div>
                            <h3><?php echo htmlspecialchars($wo['work_name']); ?></h3>
                            <div style="margin-bottom: 15px;">
                                <span class="status-badge <?php echo ($wo['status'] ?? '') === 'Issued' ? 'status-issued' : 'status-pending'; ?>">
                                    <?php echo htmlspecialchars($wo['status'] ?? 'Unknown'); ?>
                                </span>
                            </div>
                            <a href="contractor_wo_view.php?id=<?php echo $woId; ?>" class="btn-primary btn-small">View Details</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="Tenders" class="tab-content">
            <?php if (empty($myTenders)): ?>
                <div class="card" style="text-align:center; color:#777;">No tender history found.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tender ID</th>
                            <th>Title</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myTenders as $tId => $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tId); ?></td>
                                <td><?php echo htmlspecialchars($t['title']); ?></td>
                                <td>
                                    <?php
                                        // Determine status relevant to contractor if possible
                                        // For now just show general status
                                        echo htmlspecialchars($t['status'] ?? 'Active');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>

</body>
</html>
