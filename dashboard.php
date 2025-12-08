<?php
require_once 'auth_check.php';
require_once 'functions.php';

$isSuperadmin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin');
$deptId = $_SESSION['dept_id'] ?? null;
$message = '';
$error = '';

$isAdmin = false;
if (!$isSuperadmin && $deptId) {
    $roleId = $_SESSION['role_id'];
    $isAdmin = ($roleId === 'admin.' . $deptId);
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Superadmin Actions
    if ($isSuperadmin && $action === 'create_department') {
        $deptName = trim($_POST['dept_name'] ?? '');
        $newDeptId = trim($_POST['dept_id'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminUserId = trim($_POST['admin_user_id'] ?? '');

        if (empty($deptName) || empty($newDeptId) || empty($adminPassword)) {
            $error = "All fields except Admin User ID are required.";
        } else {
            $result = createDepartment($deptName, $newDeptId, $adminPassword, $adminUserId ?: null);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
    // Department Admin Actions
    elseif (!$isSuperadmin && $deptId && $isAdmin) {
        if ($action === 'create_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            if (empty($roleName)) {
                $error = "Role Name is required.";
            } else {
                $result = createRole($deptId, $roleName);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Data Fetching
$departments = [];
$deptData = null;
$deptRoles = [];
$deptUsers = [];
$inbox = [];
$outbox = [];

if ($isSuperadmin) {
    $departments = getAllDepartments();
} else {
    $deptData = getDepartment($deptId);
    $deptRoles = getRoles($deptId);
    $deptUsers = getUsers($deptId);
    $inbox = getInbox($deptId, $_SESSION['user_id']);
    $outbox = getOutbox($deptId, $_SESSION['user_id']);
}

// Deadline System Sorting Logic
if (!empty($inbox)) {
    usort($inbox, function($a, $b) {
        $today = date('Y-m-d');

        $aDue = $a['due_date'] ?? '';
        $bDue = $b['due_date'] ?? '';

        $aExpired = (!empty($aDue) && $today > $aDue);
        $bExpired = (!empty($bDue) && $today > $bDue);

        $aUrgent = (!empty($aDue) && $today == $aDue);
        $bUrgent = (!empty($bDue) && $today == $bDue);

        // Priority 1: Expired
        if ($aExpired && !$bExpired) return -1;
        if (!$aExpired && $bExpired) return 1;

        // Priority 2: Urgent
        if ($aUrgent && !$bUrgent) return -1;
        if (!$aUrgent && $bUrgent) return 1;

        // Fallback: Created Date (Newest first)
        return strcmp($b['created_at'], $a['created_at']);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - <?php echo $isSuperadmin ? 'Superadmin' : 'Dashboard'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">

        <main class="dashboard-content">
            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($isSuperadmin): ?>
                <!-- SUPERADMIN VIEW -->
                <section class="departments-list">
                    <div class="section-header">
                        <h2>Departments</h2>
                        <button id="showCreateFormBtn" class="btn-primary">Create New Department</button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>Department ID</th>
                                    <th>Created Date</th>
                                    <th>User Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No departments found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                            <td><?php echo htmlspecialchars($dept['id']); ?></td>
                                            <td><?php echo htmlspecialchars($dept['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($dept['user_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div id="createDepartmentModal" class="modal">
                    <div class="modal-content">
                        <span class="close-btn">&times;</span>
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

            <?php else: ?>
                <!-- DEPARTMENT DASHBOARD -->

                <div class="dashboard-grid">
                    <!-- Inbox -->
                    <section class="card" style="grid-column: span 2;">
                        <div class="section-header">
                            <h2>Inbox (Assigned to Me)</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Received From</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($inbox)): ?>
                                        <tr><td colspan="4">No documents in inbox.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($inbox as $doc): ?>
                                            <?php
                                                // Get 'Received From' - last history entry or created_by
                                                $receivedFrom = $doc['created_by'];
                                                if (!empty($doc['history'])) {
                                                    $lastEntry = end($doc['history']);
                                                    if ($lastEntry['action'] === 'moved') {
                                                        $receivedFrom = $lastEntry['from'];
                                                    }
                                                }

                                                // Deadline Logic
                                                $rowClass = '';
                                                $statusText = '';
                                                $today = date('Y-m-d');
                                                if (isset($doc['due_date']) && !empty($doc['due_date'])) {
                                                    if ($today > $doc['due_date']) {
                                                        $rowClass = 'expired';
                                                        $statusText = '<br><span style="color:red; font-weight:bold;">OVERDUE</span>';
                                                    } elseif ($today == $doc['due_date']) {
                                                        $rowClass = 'urgent';
                                                        $statusText = '<br><span style="color:orange; font-weight:bold;">DUE TODAY</span>';
                                                    }
                                                }
                                            ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td>
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                    <?php echo $statusText; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($receivedFrom); ?></td>
                                                <td><?php echo htmlspecialchars($doc['created_at']); ?></td>
                                                <td><a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn-small">Open</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Outbox -->
                    <section class="card" style="grid-column: span 2;">
                        <div class="section-header">
                            <h2>Outbox (Sent / Created)</h2>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Currently With</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($outbox)): ?>
                                        <tr><td colspan="5">No sent documents.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($outbox as $doc): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['current_owner']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($doc['status']); ?></td>
                                                <td><a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn-small">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Common Actions -->
                    <section class="card">
                        <div class="section-header">
                            <h2>Work Area</h2>
                        </div>
                        <p style="margin-bottom: 1rem;">Generate official documents using templates.</p>
                        <a href="create_document.php" class="btn-primary">Create New Document</a>
                    </section>

                    <?php if ($isAdmin): ?>
                        <!-- Admin Actions -->
                        <section class="card">
                            <div class="section-header">
                                <h2>Administration</h2>
                            </div>
                            <ul class="list-group">
                                <li>
                                    <strong>Contractors</strong>
                                    <a href="manage_contractors.php" class="btn-small">Manage</a>
                                </li>
                                <li>
                                    <strong>Templates</strong>
                                    <a href="manage_templates.php" class="btn-small">Manage</a>
                                </li>
                                <li>
                                    <strong>Backup</strong>
                                    <a href="backup.php" class="btn-small" style="background-color: #6610f2; border-color: #6610f2;">Download Data (.zip)</a>
                                </li>
                            </ul>
                        </section>

                        <!-- Manage Roles -->
                        <section class="card">
                            <div class="section-header">
                                <h2>Manage Roles</h2>
                            </div>
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="action" value="create_role">
                                <div class="form-group">
                                    <input type="text" name="role_name" placeholder="New Role Name" required>
                                </div>
                                <button type="submit" class="btn-secondary">Add</button>
                            </form>

                            <ul class="list-group">
                                <?php foreach ($deptRoles as $rId => $role): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                        <small>(ID: <?php echo htmlspecialchars($rId); ?>)</small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <!-- Manage Users -->
                        <section class="card">
                            <div class="section-header">
                                <h2>Manage Users</h2>
                                <div>
                                    <a href="create_user.php" class="btn-primary">Add User</a>
                                    <a href="bulk_upload.php" class="btn-secondary" style="margin-left: 5px;">Bulk Import</a>
                                </div>
                            </div>

                             <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deptUsers as $uId => $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php
                                                        $rId = $user['role'];
                                                        echo htmlspecialchars($deptRoles[$rId]['name'] ?? $rId);
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // Modal Logic
        // Superadmin Modal
        var saModal = document.getElementById("createDepartmentModal");
        var saBtn = document.getElementById("showCreateFormBtn");
        var saClose = document.getElementsByClassName("close-btn");

        if (saModal && saBtn) {
            saBtn.onclick = function() { saModal.style.display = "block"; }
            if (saClose[0]) saClose[0].onclick = function() { saModal.style.display = "none"; }
        }

        window.onclick = function(event) {
            if (event.target == saModal) saModal.style.display = "none";
        }
    </script>
</body>
</html>
