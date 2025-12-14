<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$roleId = $_SESSION['role_id'];
$isDeptAdmin = ($roleId === 'admin.' . $deptId);

if (!$isDeptAdmin) {
    die("Access Denied. Only Department Administrators can manage contractors.");
}

$contractorsPath = 'departments/' . $deptId . '/data/contractors.json';
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $contractors = readJSON($contractorsPath) ?? [];

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $pan = trim($_POST['pan']);
        $gst = trim($_POST['gst']);
        $mobile = trim($_POST['mobile']);

        if ($action === 'create') {
            // Generate Unique ID CON-YYYY-XXXX
            $year = date('Y');
            $prefix = "CON-$year-";
            $maxNum = 0;

            foreach ($contractors as $k => $v) {
                if (strpos($k, $prefix) === 0) {
                    $numStr = substr($k, strlen($prefix));
                    if (is_numeric($numStr)) {
                        $num = (int)$numStr;
                        if ($num > $maxNum) $maxNum = $num;
                    }
                }
            }

            $nextNum = $maxNum + 1;
            $id = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT); // e.g., CON-2025-0001
        } else {
            // Update existing
            $id = $_POST['id'] ?? '';
            if (empty($id) || !isset($contractors[$id])) {
                $error = "Invalid Contractor ID.";
            }
        }

        if (!$error && (empty($name) || empty($mobile))) {
            $error = "Name and Mobile are required.";
        }

        if (!$error) {
            $existingPass = $contractors[$id]['password'] ?? null;
            $contractors[$id] = [
                'id' => $id,
                'name' => $name,
                'address' => $address,
                'pan' => $pan,
                'gst' => $gst,
                'mobile' => $mobile,
                'password' => $existingPass
            ];
             // If creating new and no password (shouldn't happen with this logic, but for safety), set default
            if ($action === 'create') {
                 $contractors[$id]['password'] = password_hash($mobile, PASSWORD_DEFAULT);
            }

            if (writeJSON($contractorsPath, $contractors)) {
                $message = "Contractor saved successfully.";
            } else {
                $error = "Failed to save contractor.";
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (isset($contractors[$id])) {
            unset($contractors[$id]);
            if (writeJSON($contractorsPath, $contractors)) {
                $message = "Contractor deleted.";
            } else {
                $error = "Failed to delete contractor.";
            }
        }
    } elseif ($action === 'reset_password') {
        $id = $_POST['id'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (isset($contractors[$id]) && !empty($newPass)) {
            $contractors[$id]['password'] = password_hash($newPass, PASSWORD_DEFAULT);
             if (writeJSON($contractorsPath, $contractors)) {
                $message = "Password updated successfully for " . htmlspecialchars($contractors[$id]['name']);
            } else {
                $error = "Failed to update password.";
            }
        } else {
            $error = "Invalid ID or empty password.";
        }
    }
}

$contractors = readJSON($contractorsPath) ?? [];

// Migration Logic: Default password to mobile hash if missing
$migrationNeeded = false;
foreach ($contractors as $k => &$c) {
    if (!isset($c['password']) && !empty($c['mobile'])) {
        $c['password'] = password_hash($c['mobile'], PASSWORD_DEFAULT);
        $migrationNeeded = true;
    }
}
unset($c);
if ($migrationNeeded) {
    writeJSON($contractorsPath, $contractors);
}

// Edit Mode
$editContractor = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    if (isset($contractors[$editId])) {
        $editContractor = $contractors[$editId];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contractors - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .contractor-list { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Manage Contractors</h1>
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
            <h2><?php echo $editContractor ? 'Edit Contractor' : 'Add New Contractor'; ?></h2>
            <form method="POST" action="manage_contractors.php">
                <input type="hidden" name="action" value="<?php echo $editContractor ? 'update' : 'create'; ?>">
                <?php if ($editContractor): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editContractor['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Contractor Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editContractor['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($editContractor['address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>PAN Number</label>
                    <input type="text" name="pan" value="<?php echo htmlspecialchars($editContractor['pan'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" name="gst" value="<?php echo htmlspecialchars($editContractor['gst'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" name="mobile" value="<?php echo htmlspecialchars($editContractor['mobile'] ?? ''); ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editContractor ? 'Update' : 'Save'; ?></button>
                    <?php if ($editContractor): ?>
                        <a href="manage_contractors.php" class="btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="contractor-list">
            <div style="padding: 10px; border-bottom: 1px solid #eee;">
                <input type="text" id="contractorSearch" placeholder="Search by ID or Name..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <table class="data-table" id="contractorTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>GST / PAN</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contractors)): ?>
                        <tr><td colspan="6" class="text-center">No contractors found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contractors as $c): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($c['gst'] ?: '-'); ?> /
                                    <?php echo htmlspecialchars($c['pan'] ?: '-'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['address']); ?></td>
                                <td>
                                    <button type="button" class="btn-small" onclick="openResetModal('<?php echo $c['id']; ?>', '<?php echo addslashes($c['name']); ?>')">Reset Pass</button>
                                    <a href="?edit=<?php echo $c['id']; ?>" class="btn-small">Edit</a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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

    <!-- Password Reset Modal -->
    <div id="resetPasswordModal" class="modal" style="display:none; position:fixed; z-index:1; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
      <div class="modal-content" style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:400px; border-radius: 8px;">
        <span class="close" onclick="document.getElementById('resetPasswordModal').style.display='none'" style="float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
        <h3>Reset Password</h3>
        <p>Set new password for <span id="resetName" style="font-weight:bold;"></span></p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetId">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required style="width:100%; padding:8px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="text-align: right; margin-top: 15px;">
                 <button type="button" class="btn-secondary" onclick="document.getElementById('resetPasswordModal').style.display='none'">Cancel</button>
                 <button type="submit" class="btn-primary">Set Password</button>
            </div>
        </form>
      </div>
    </div>

    <script>
        document.getElementById('contractorSearch').addEventListener('keyup', function() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("contractorSearch");
            filter = input.value.toUpperCase();
            table = document.getElementById("contractorTable");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                // Check ID (col 0) and Name (col 1)
                var tdId = tr[i].getElementsByTagName("td")[0];
                var tdName = tr[i].getElementsByTagName("td")[1];

                if (tdId || tdName) {
                    var txtId = tdId.textContent || tdId.innerText;
                    var txtName = tdName.textContent || tdName.innerText;

                    if (txtId.toUpperCase().indexOf(filter) > -1 || txtName.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        });

        function openResetModal(id, name) {
            document.getElementById('resetId').value = id;
            document.getElementById('resetName').innerText = name;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }

        // Close modal if clicked outside
        window.onclick = function(event) {
            var modal = document.getElementById('resetPasswordModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
