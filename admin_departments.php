<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Superadmin Only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    die("Access Denied. Superadmin only.");
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_tier') {
        $targetDeptId = $_POST['dept_id'] ?? '';
        $newTier = $_POST['tier'] ?? '';

        if (!empty($targetDeptId) && is_numeric($newTier) && $newTier >= 1 && $newTier <= 4) {
            $deptMeta = readJSON('departments/' . $targetDeptId . '/department.json');
            if ($deptMeta) {
                $deptMeta['tier'] = (string)$newTier;
                if (writeJSON('departments/' . $targetDeptId . '/department.json', $deptMeta)) {
                    $message = "Department tier updated to Tier $newTier.";
                } else {
                    $error = "Failed to update tier.";
                }
            } else {
                $error = "Department not found.";
            }
        } else {
            $error = "Invalid tier or department ID.";
        }
    }
    elseif ($action === 'create_department') {
        $deptName = trim($_POST['dept_name'] ?? '');
        $newDeptId = trim($_POST['dept_id'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminUserId = trim($_POST['admin_user_id'] ?? '');

        if (empty($deptName) || empty($newDeptId) || empty($adminPassword)) {
            $error = "All fields except Admin User ID are required.";
        } else {
            // Tier 1 is default in createDepartment now (once functions.php is fixed)
            $result = createDepartment($deptName, $newDeptId, $adminPassword, $adminUserId ?: null);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
    elseif ($action === 'suspend_department' || $action === 'archive_department' || $action === 'activate_department') {
        $targetDeptId = $_POST['dept_id'] ?? '';
        if (!empty($targetDeptId)) {
            $newStatus = ($action === 'suspend_department') ? 'suspended' : (($action === 'archive_department') ? 'archived' : 'active');
            $deptMeta = readJSON('departments/' . $targetDeptId . '/department.json');
            if ($deptMeta) {
                $deptMeta['status'] = $newStatus;
                if (writeJSON('departments/' . $targetDeptId . '/department.json', $deptMeta)) {
                    $message = "Department status updated to $newStatus.";
                } else {
                    $error = "Failed to update department status.";
                }
            } else {
                $error = "Department not found.";
            }
        }
    }
}

// Data Fetching
$departments = getAllDepartments();
$activeDepts = [];
$archivedDepts = [];
foreach ($departments as $d) {
    if (($d['status'] ?? 'active') === 'archived') {
        $archivedDepts[] = $d;
    } else {
        $activeDepts[] = $d;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .tabs { display: flex; gap: 1rem; margin-bottom: 1rem; border-bottom: 2px solid #ddd; }
        .tab { padding: 10px 20px; cursor: pointer; border-radius: 4px 4px 0 0; background: #f8f9fa; border: 1px solid transparent; }
        .tab.active { background: #fff; border: 1px solid #ddd; border-bottom: 2px solid #fff; font-weight: bold; margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <main class="dashboard-content">
            <div class="section-header">
                <h2>Manage Departments</h2>
                <div style="display:flex; gap:10px;">
                    <button id="showCreateFormBtn" class="btn-primary">Create New Department</button>
                    <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" onclick="showTab('active-depts', this)">Active Departments</div>
                <div class="tab" onclick="showTab('archived-depts', this)">Archived</div>
            </div>

            <!-- Active Tab -->
            <div id="active-depts" class="tab-content active">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Department ID</th>
                                <th>Created Date</th>
                                <th>User Count</th>
                                <th>Tier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeDepts)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No active departments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activeDepts as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['id']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['user_count']); ?></td>
                                        <td>
                                            <form method="POST" class="inline-form" style="display: flex; gap: 5px;">
                                                <input type="hidden" name="action" value="change_tier">
                                                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                                <select name="tier" onchange="this.form.submit()" style="padding: 2px;">
                                                    <option value="1" <?php if(($dept['tier']??'1') == '1') echo 'selected'; ?>>Tier 1: Basic</option>
                                                    <option value="2" <?php if(($dept['tier']??'1') == '2') echo 'selected'; ?>>Tier 2: Smart</option>
                                                    <option value="3" <?php if(($dept['tier']??'1') == '3') echo 'selected'; ?>>Tier 3: Office</option>
                                                    <option value="4" <?php if(($dept['tier']??'1') == '4') echo 'selected'; ?>>Tier 4: Gov</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo htmlspecialchars($dept['status'] ?? 'active'); ?></td>
                                        <td>
                                            <a href="admin_view_dept.php?dept_id=<?php echo $dept['id']; ?>" class="btn-small btn-secondary">View Staff</a>

                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                                <?php if (($dept['status'] ?? 'active') !== 'suspended'): ?>
                                                    <button type="submit" name="action" value="suspend_department" class="btn-small" style="background: orange; border-color: orange;" onclick="return confirm('Suspend this department?');">Suspend</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="activate_department" class="btn-small" style="background: green; border-color: green;">Activate</button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="archive_department" class="btn-small" style="background: gray; border-color: gray;" onclick="return confirm('Archive this department?');">Archive</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Archived Tab -->
            <div id="archived-depts" class="tab-content">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Department ID</th>
                                <th>Created Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($archivedDepts)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No archived departments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($archivedDepts as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['id']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['status']); ?></td>
                                        <td>
                                            <a href="admin_view_dept.php?dept_id=<?php echo $dept['id']; ?>" class="btn-small btn-secondary">View Staff</a>

                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                                <button type="submit" name="action" value="activate_department" class="btn-small" style="background: green; border-color: green;">Un-Archive</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="createDepartmentModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" id="closeCreate">&times;</span>
                    <h2>Create New Department</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_department">
                        <div class="form-group">
                            <label for="dept_name">Department Name</label>
                            <input type="text" id="dept_name" name="dept_name" placeholder="e.g. Road Construction Dept" required>
                        </div>
                        <div class="form-group">
                            <label for="dept_id">Department ID (URL-friendly)</label>
                            <input type="text" id="dept_id" name="dept_id" placeholder="e.g. road_dept" required pattern="[a-zA-Z0-9_]+">
                            <small>Alphanumeric and underscores only.</small>
                        </div>
                        <div class="form-group">
                            <label for="admin_user_id">Initial Admin User ID (Optional)</label>
                            <input type="text" id="admin_user_id" name="admin_user_id" placeholder="e.g. admin_john">
                            <small>Leave empty to auto-generate.</small>
                        </div>
                        <div class="form-group">
                            <label for="admin_password">Initial Admin Password</label>
                            <input type="text" id="admin_password" name="admin_password" placeholder="Enter password" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Create Department</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Modal Logic
        var saModal = document.getElementById("createDepartmentModal");
        var saBtn = document.getElementById("showCreateFormBtn");
        var saCloseCreate = document.getElementById("closeCreate");

        if (saModal && saBtn) {
            saBtn.onclick = function() { saModal.style.display = "block"; }
            if (saCloseCreate) saCloseCreate.onclick = function() { saModal.style.display = "none"; }
        }

        window.onclick = function(event) {
            if (event.target == saModal) saModal.style.display = "none";
        }

        function showTab(tabId, tabElement) {
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].style.display = 'none';
                contents[i].classList.remove('active');
            }
            document.getElementById(tabId).style.display = 'block';
            document.getElementById(tabId).classList.add('active');

            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            tabElement.classList.add('active');
        }
    </script>
</body>
</html>
