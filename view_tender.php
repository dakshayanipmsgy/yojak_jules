<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$tenderId = $_GET['id'] ?? '';

if (!$tenderId) {
    die("Tender ID not specified.");
}

$tendersPath = 'departments/' . $deptId . '/data/tenders.json';
$tenders = readJSON($tendersPath);

if (!$tenders || !isset($tenders[$tenderId])) {
    die("Tender not found.");
}

$tender = $tenders[$tenderId];
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add Participant
    if ($action === 'add_participant') {
        $contractorId = trim($_POST['contractor_id'] ?? '');
        $contractorName = trim($_POST['contractor_name'] ?? ''); // From hidden field or lookup

        if ($contractorId && $contractorName) {
            // Check duplicates
            $exists = false;
            foreach ($tender['participants'] as $p) {
                if ($p['contractor_id'] === $contractorId) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $error = "Contractor already participating.";
            } else {
                $tender['participants'][] = [
                    'contractor_id' => $contractorId,
                    'name' => $contractorName,
                    'status' => 'Applied', // Default
                    'work_order_issued' => false,
                    'agreement_signed' => false
                ];
                $message = "Participant added.";
            }
        } else {
            $error = "Invalid contractor details.";
        }
    }

    // Update Participant Status/Flags
    if ($action === 'update_participant') {
        $pCid = $_POST['p_contractor_id'] ?? '';
        $newStatus = $_POST['status'] ?? 'Applied';
        $woIssued = isset($_POST['work_order_issued']) ? true : false;
        $aggSigned = isset($_POST['agreement_signed']) ? true : false;

        foreach ($tender['participants'] as &$p) {
            if ($p['contractor_id'] === $pCid) {
                $p['status'] = $newStatus;
                $p['work_order_issued'] = $woIssued;
                $p['agreement_signed'] = $aggSigned;
                $message = "Participant updated.";
                break;
            }
        }
        unset($p); // break reference
    }

    // Toggle Tender Status
    if ($action === 'toggle_status') {
        $tender['status'] = ($tender['status'] === 'Open') ? 'Closed' : 'Open';
        $message = "Tender status updated to " . $tender['status'];
    }

    // Save Changes
    $tenders[$tenderId] = $tender;
    if (writeJSON($tendersPath, $tenders)) {
        // Success
    } else {
        $error = "Failed to save changes.";
    }
}

// Re-read to display latest
$tender = $tenders[$tenderId];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tender Details - <?php echo htmlspecialchars($tender['tender_id']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .detail-header {
            background: white;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 1200px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
        }
        .participants-panel {
            background: white;
            padding: 1.5rem;
            margin: 1rem auto;
            max-width: 1200px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .wo-badge {
            background-color: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .status-select {
            padding: 4px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        th { background-color: #f8f9fa; }

        .add-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <a href="tenders.php" class="back-link">&larr; Back to Tenders</a>
            <h1><?php echo htmlspecialchars($tender['tender_id']); ?></h1>
        </div>
    </div>

    <div class="detail-header">
        <div>
            <h2><?php echo htmlspecialchars($tender['title']); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($tender['description'])); ?></p>
        </div>
        <div style="text-align:right;">
            <p><strong>Status:</strong> <?php echo htmlspecialchars($tender['status']); ?></p>
            <p><strong>Created:</strong> <?php echo htmlspecialchars($tender['created_at']); ?></p>
            <form method="POST" action="" style="margin-top:10px;">
                <input type="hidden" name="action" value="toggle_status">
                <button type="submit" class="btn-secondary">
                    <?php echo $tender['status'] === 'Open' ? 'Close Tender' : 'Reopen Tender'; ?>
                </button>
            </form>
        </div>
    </div>

    <div class="participants-panel">
        <h3>Participating Contractors</h3>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add Participant -->
        <?php if ($tender['status'] === 'Open'): ?>
        <div class="add-box">
            <div class="form-group" style="margin-bottom:0; flex-grow:1;">
                <label>Add Participant (Contractor ID)</label>
                <input type="text" id="new_contractor_id" placeholder="CON-YYYY-XXXX" onblur="lookupContractor()">
                <div id="lookup_result" style="font-size:0.9rem; margin-top:5px;"></div>
            </div>
            <form method="POST" action="" id="addForm" style="display:none;">
                <input type="hidden" name="action" value="add_participant">
                <input type="hidden" name="contractor_id" id="form_contractor_id">
                <input type="hidden" name="contractor_name" id="form_contractor_name">
                <button type="submit" class="btn-primary">Add to Tender</button>
            </form>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Flags</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tender['participants'] as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['contractor_id']); ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_participant">
                            <input type="hidden" name="p_contractor_id" value="<?php echo htmlspecialchars($p['contractor_id']); ?>">
                            <select name="status" class="status-select" onchange="this.form.submit()">
                                <?php
                                $statuses = ['Applied', 'Tech-Qualified', 'L1 Winner', 'Rejected'];
                                foreach ($statuses as $s) {
                                    $sel = ($p['status'] === $s) ? 'selected' : '';
                                    echo "<option value='$s' $sel>$s</option>";
                                }
                                ?>
                            </select>

                            <div style="margin-top:5px;">
                                <label><input type="checkbox" name="work_order_issued" value="1" <?php if($p['work_order_issued']) echo 'checked'; ?> onchange="this.form.submit()"> WO Issued</label>
                                <label style="margin-left:5px;"><input type="checkbox" name="agreement_signed" value="1" <?php if($p['agreement_signed']) echo 'checked'; ?> onchange="this.form.submit()"> Agreement</label>
                            </div>
                        </form>
                    </td>
                    <td>
                        <?php if ($p['work_order_issued']): ?>
                            <span class="wo-badge">WO ISSUED</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'L1 Winner'): ?>
                            <a href="create_document.php?contractor_id=<?php echo urlencode($p['contractor_id']); ?>&template=work_order" class="btn-primary" style="font-size:0.8rem; text-decoration:none;">Draft WO</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tender['participants'])): ?>
                <tr><td colspan="5" style="text-align:center;">No participants yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    function lookupContractor() {
        var id = document.getElementById('new_contractor_id').value.trim();
        var resultDiv = document.getElementById('lookup_result');
        var addForm = document.getElementById('addForm');

        if (!id) {
            resultDiv.innerHTML = '';
            addForm.style.display = 'none';
            return;
        }

        resultDiv.innerHTML = 'Searching...';

        fetch('ajax_get_data.php?action=get_contractor&id=' + encodeURIComponent(id))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    resultDiv.innerHTML = '<span style="color:green">Found: ' + data.data.name + ' (' + data.data.mobile + ')</span>';
                    document.getElementById('form_contractor_id').value = id;
                    document.getElementById('form_contractor_name').value = data.data.name;
                    addForm.style.display = 'block';
                } else {
                    resultDiv.innerHTML = '<span style="color:red">' + data.message + '</span>';
                    addForm.style.display = 'none';
                }
            })
            .catch(err => {
                resultDiv.innerHTML = '<span style="color:red">Error fetching data.</span>';
                addForm.style.display = 'none';
            });
    }
    </script>
</body>
</html>
