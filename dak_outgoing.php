<?php
require_once 'auth_check.php';
require_once 'functions.php';

if (!isset($_SESSION['dept_id'])) {
    header('Location: index.php');
    exit;
}

$currentDeptId = $_SESSION['dept_id'];
$currentUserId = $_SESSION['user_id'];
$currentRole = $_SESSION['role_id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetType = $_POST['target_type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($targetType) || empty($subject)) {
        $error = "Please fill in all required fields.";
    } else {
        $data = [
            'id' => 'OUT_' . date('Y') . '_' . uniqid(),
            'date' => date('Y-m-d H:i:s'),
            'sender_id' => $currentUserId,
            'sender_role' => $currentRole,
            'subject' => $subject,
            'description' => $description,
            'target_type' => $targetType,
            'status' => 'sent'
        ];

        if ($targetType === 'internal') {
            $data['recipient_dept'] = $currentDeptId;
            $data['recipient_role'] = $_POST['int_role'] ?? '';
            $data['recipient_user'] = $_POST['int_user'] ?? '';
            if (empty($data['recipient_role']) || empty($data['recipient_user'])) {
                $error = "Please select a recipient role and user.";
            }
        } elseif ($targetType === 'ext_reg') {
            $data['recipient_dept'] = $_POST['ext_dept'] ?? '';
            $data['recipient_role'] = $_POST['ext_role'] ?? '';
            $data['recipient_user'] = $_POST['ext_user'] ?? '';
            if (empty($data['recipient_dept']) || empty($data['recipient_role']) || empty($data['recipient_user'])) {
                $error = "Please select a recipient department, role, and user.";
            }
        } elseif ($targetType === 'ext_unreg') {
            $data['recipient_name'] = trim($_POST['recipient_name'] ?? '');
            $data['recipient_designation'] = trim($_POST['recipient_designation'] ?? '');
            $data['recipient_address'] = trim($_POST['recipient_address'] ?? '');
            if (empty($data['recipient_name']) || empty($data['recipient_address'])) {
                $error = "Please provide recipient name and address.";
            }
        } else {
            $error = "Invalid target type.";
        }

        if (empty($error)) {
            // Save to JSON
            $filePath = 'departments/' . $currentDeptId . '/data/dak_outgoing.json';
            $existingData = readJSON($filePath) ?? [];
            $existingData[] = $data;

            // Ensure writeJSON handles directory creation, which it does.
            if (writeJSON($filePath, $existingData)) {
                $message = "Dak dispatched successfully!";
            } else {
                $error = "Failed to save data.";
            }
        }
    }
}

// Prepare initial data for Internal (Current Dept)
$currentDeptRoles = [];
$rolesFile = 'departments/' . $currentDeptId . '/roles/roles.json';
$rolesData = readJSON($rolesFile);

if ($rolesData) {
    foreach ($rolesData as $id => $r) {
        if (($r['status'] ?? 'active') === 'active') {
            $currentDeptRoles[] = ['id' => $id, 'name' => $r['name']];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outgoing Dak - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <main class="dashboard-content">
            <div class="section-header">
                <h2>New Outgoing Dak</h2>
                <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" action="" id="dakForm">

                    <!-- Mode Selector -->
                    <div class="form-group">
                        <label>Destination Type</label>
                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                            <label>
                                <input type="radio" name="target_type" value="internal" checked onchange="handleModeChange()">
                                Internal (My Office)
                            </label>
                            <label>
                                <input type="radio" name="target_type" value="ext_reg" onchange="handleModeChange()">
                                External (Yojak Department)
                            </label>
                            <label>
                                <input type="radio" name="target_type" value="ext_unreg" onchange="handleModeChange()">
                                External (Outside World)
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" required placeholder="Subject of the letter/file">
                    </div>

                    <div class="form-group">
                        <label>Description / Remarks</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

                    <!-- Scenario 1: Internal -->
                    <div id="block_internal">
                        <h4>Internal Recipient</h4>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="int_role" id="int_role" onchange="fetchUsers('<?php echo $currentDeptId; ?>', this.value, 'int_user')">
                                <option value="">-- Select Role --</option>
                                <?php foreach ($currentDeptRoles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>User</label>
                            <select name="int_user" id="int_user">
                                <option value="">-- Select User --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Scenario 2: External Registered -->
                    <div id="block_ext_reg" style="display: none;">
                        <h4>External Recipient (Yojak)</h4>
                        <div class="form-group">
                            <label>Department</label>
                            <select name="ext_dept" id="ext_dept" onchange="fetchRoles(this.value, 'ext_role')">
                                <option value="">-- Select Department --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="ext_role" id="ext_role" onchange="fetchUsers(document.getElementById('ext_dept').value, this.value, 'ext_user')">
                                <option value="">-- Select Role --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>User</label>
                            <select name="ext_user" id="ext_user">
                                <option value="">-- Select User --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Scenario 3: External Unregistered -->
                    <div id="block_ext_unreg" style="display: none;">
                        <h4>External Recipient (Public/Other)</h4>
                        <div class="form-group">
                            <label>Receiver Name</label>
                            <input type="text" name="recipient_name" placeholder="Name of Person / Ministry">
                        </div>
                        <div class="form-group">
                            <label>Designation (Optional)</label>
                            <input type="text" name="recipient_designation" placeholder="e.g. Joint Secretary">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="recipient_address" rows="3" placeholder="Full Postal Address"></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn-primary">Dispatch Dak</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Load Departments on page load (lazy loading could be better but this is fine)
        document.addEventListener('DOMContentLoaded', function() {
            fetch('ajax_get_data.php?action=get_depts')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const deptSelect = document.getElementById('ext_dept');
                        data.data.forEach(dept => {
                            // Don't show current dept in external list?
                            // Usually fine to show, but if user wants to send internally they should use Internal mode.
                            // I'll leave it as is.
                            if (dept.id !== '<?php echo $currentDeptId; ?>') {
                                const option = document.createElement('option');
                                option.value = dept.id;
                                option.textContent = dept.name;
                                deptSelect.appendChild(option);
                            }
                        });
                    }
                });
        });

        function handleModeChange() {
            const mode = document.querySelector('input[name="target_type"]:checked').value;

            document.getElementById('block_internal').style.display = 'none';
            document.getElementById('block_ext_reg').style.display = 'none';
            document.getElementById('block_ext_unreg').style.display = 'none';

            if (mode === 'internal') {
                document.getElementById('block_internal').style.display = 'block';
            } else if (mode === 'ext_reg') {
                document.getElementById('block_ext_reg').style.display = 'block';
            } else if (mode === 'ext_unreg') {
                document.getElementById('block_ext_unreg').style.display = 'block';
            }
        }

        function fetchRoles(deptId, targetSelectId) {
            const select = document.getElementById(targetSelectId);
            select.innerHTML = '<option value="">-- Select Role --</option>';
            // Clear users as well
            if (targetSelectId === 'ext_role') {
                document.getElementById('ext_user').innerHTML = '<option value="">-- Select User --</option>';
            }

            if (!deptId) return;

            fetch(`ajax_get_data.php?action=get_roles&dept_id=${deptId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.data.forEach(role => {
                            const opt = document.createElement('option');
                            opt.value = role.id;
                            opt.textContent = role.name;
                            select.appendChild(opt);
                        });
                    }
                });
        }

        function fetchUsers(deptId, roleId, targetSelectId) {
            const select = document.getElementById(targetSelectId);
            select.innerHTML = '<option value="">-- Select User --</option>';

            if (!deptId || !roleId) return;

            fetch(`ajax_get_data.php?action=get_users&dept_id=${deptId}&role_id=${roleId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.data.forEach(user => {
                            const opt = document.createElement('option');
                            opt.value = user.id;
                            opt.textContent = user.full_name;
                            select.appendChild(opt);
                        });
                    }
                });
        }
    </script>
</body>
</html>
