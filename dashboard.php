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
    if ($isSuperadmin) {
        if ($action === 'create_department') {
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
        elseif ($action === 'process_request') {
            $reqId = $_POST['request_id'] ?? '';
            $decision = $_POST['decision'] ?? ''; // approve or reject

            $requests = readJSON('system/requests.json');
            $reqIndex = -1;
            foreach ($requests as $idx => $r) {
                if ($r['id'] === $reqId) {
                    $reqIndex = $idx;
                    break;
                }
            }

            if ($reqIndex !== -1 && $requests[$reqIndex]['status'] === 'pending') {
                $req = $requests[$reqIndex];
                $newStatus = ($decision === 'approve') ? 'approved' : 'rejected';

                $success = true;
                if ($newStatus === 'approved') {
                    // Execute the action
                    $targetDept = $req['dept_id'];
                    $targetType = $req['target_type'];
                    $targetId = $req['target_id'];
                    $actionType = $req['action_type'];

                    // Update User or Role
                    if ($targetType === 'user') {
                        $usersPath = 'departments/' . $targetDept . '/users/users.json';
                        $users = readJSON($usersPath);
                        if (isset($users[$targetId])) {
                            $users[$targetId]['status'] = ($actionType === 'suspend') ? 'suspended' : 'archived';
                            if (!writeJSON($usersPath, $users)) $success = false;
                        } else $success = false;
                    } elseif ($targetType === 'role') {
                        $rolesPath = 'departments/' . $targetDept . '/roles/roles.json';
                        $roles = readJSON($rolesPath);
                        if (isset($roles[$targetId])) {
                            $roles[$targetId]['status'] = ($actionType === 'suspend') ? 'suspended' : 'archived';
                            if (!writeJSON($rolesPath, $roles)) $success = false;
                        } else $success = false;
                    }
                }

                if ($success) {
                    $requests[$reqIndex]['status'] = $newStatus;
                    writeJSON('system/requests.json', $requests);
                    $message = "Request " . $decision . "d successfully.";
                } else {
                    $error = "Failed to execute request action.";
                }
            }
        }
    }
    // Department Admin Actions
    elseif (!$isSuperadmin && $deptId && $isAdmin) {
        if ($action === 'create_role') {
            $roleName = trim($_POST['role_name'] ?? '');
            $roleSlug = trim($_POST['role_slug'] ?? '');

            if (empty($roleName) || empty($roleSlug)) {
                $error = "Role Name and ID are required.";
            } else {
                $result = createRole($deptId, $roleName, $roleSlug);
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

    // Verify User Existence & Status (Safety Check as requested)
    // Key-based lookup using session user_id
    if (!isset($deptUsers[$_SESSION['user_id']])) {
        // Force logout if user no longer exists in JSON
        session_destroy();
        header("Location: index.php?msg=invalid_session");
        exit;
    }
    $currentUser = $deptUsers[$_SESSION['user_id']];
    if (isset($currentUser['status']) && $currentUser['status'] !== 'active') {
        // Force logout if user is suspended/archived
        session_destroy();
        header("Location: index.php?msg=account_suspended");
        exit;
    }

    $inbox = getInbox($deptId, $_SESSION['user_id']);
    $outbox = getOutbox($deptId, $_SESSION['user_id']);
}

// Superadmin Requests Fetching
$pendingRequests = [];
if ($isSuperadmin) {
    $allReqs = readJSON('system/requests.json');
    if ($allReqs) {
        foreach ($allReqs as $r) {
            if ($r['status'] === 'pending') {
                $pendingRequests[] = $r;
            }
        }
    }
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
            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($isSuperadmin): ?>
                <!-- SUPERADMIN VIEW -->

                <!-- System Management Section -->
                <section class="card" style="margin-bottom: 2rem;">
                    <div class="section-header">
                        <h2>System Management</h2>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <a href="admin_templates.php" class="btn-primary">Manage Global Templates</a>
                        <a href="admin_settings.php" class="btn-secondary">AI Configuration</a>
                    </div>
                </section>

                <section class="departments-list">
                    <div class="section-header">
                        <h2>Pending Governance Requests</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Target</th>
                                    <th>Action</th>
                                    <th>Reason</th>
                                    <th>Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingRequests)): ?>
                                    <tr><td colspan="5" class="text-center">No pending requests.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pendingRequests as $req): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($req['dept_id']); ?></td>
                                            <td><?php echo htmlspecialchars($req['target_name']); ?> (<?php echo htmlspecialchars($req['target_type']); ?>)</td>
                                            <td><?php echo htmlspecialchars(ucfirst($req['action_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="process_request">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="decision" value="approve" class="btn-small" style="background: green; border-color: green;">Approve</button>
                                                    <button type="submit" name="decision" value="reject" class="btn-small" style="background: red; border-color: red;">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php
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

                <section class="departments-list" style="margin-top: 2rem;">
                    <div class="section-header">
                        <h2>Departments</h2>
                        <button id="showCreateFormBtn" class="btn-primary">Create New Department</button>
                    </div>

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
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activeDepts)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No active departments found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activeDepts as $dept): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                                <td><?php echo htmlspecialchars($dept['id']); ?></td>
                                                <td><?php echo htmlspecialchars($dept['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($dept['user_count']); ?></td>
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
                </section>

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
                        <div style="margin-top: 10px;">
                            <a href="dak_outgoing.php" class="btn-secondary">Outgoing Dak (New)</a>
                        </div>
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
                                    <strong>Dak Register</strong>
                                    <a href="dak_register.php" class="btn-small">Open Register</a>
                                </li>
                                <li>
                                    <strong>Governance Requests</strong>
                                    <a href="request_action.php" class="btn-small">Manage Requests</a>
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
                            <form method="POST" action="" class="inline-form" style="display: block;">
                                <input type="hidden" name="action" value="create_role">
                                <div class="form-group" style="margin-bottom: 0.5rem;">
                                    <label style="display:block; font-size: 0.8em; margin-bottom: 2px;">Role Name</label>
                                    <input type="text" id="new_role_name" name="role_name" placeholder="e.g. Chief Engineer" required oninput="generateRoleSlug()">
                                </div>
                                <div class="form-group" style="margin-bottom: 0.5rem;">
                                    <label style="display:block; font-size: 0.8em; margin-bottom: 2px;">Role ID (Slug)</label>
                                    <input type="text" id="new_role_slug" name="role_slug" placeholder="e.g. CE" required>
                                    <small style="color:#666; display:block; margin-top:2px;">Auto-filled (initials) but editable.</small>
                                </div>
                                <button type="submit" class="btn-secondary" style="margin-top: 5px;">Add Role</button>
                            </form>

                            <ul class="list-group" style="margin-top: 1rem;">
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
        // Role Slug Generation Logic
        function generateRoleSlug() {
            var name = document.getElementById('new_role_name').value;
            var slugField = document.getElementById('new_role_slug');

            if (name) {
                // Extract Initials
                var words = name.trim().split(/\s+/);
                var initials = '';
                words.forEach(function(word) {
                    if (word.length > 0) {
                        initials += word.charAt(0).toUpperCase();
                    }
                });

                // If the user hasn't heavily modified it, update it.
                // Or just update it. The prompt says "Auto-Fill Logic".
                // I will update it.
                if (document.activeElement !== slugField) {
                     slugField.value = initials;
                }
            }
        }

        // Modal Logic
        // Superadmin Modal
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
            // Hide all tab contents
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].style.display = 'none';
                contents[i].classList.remove('active');
            }
            // Show selected
            document.getElementById(tabId).style.display = 'block';
            document.getElementById(tabId).classList.add('active');

            // Reset tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            // Active current
            tabElement.classList.add('active');
        }
    </script>
</body>
</html>
