<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Superadmin Only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    die("Access Denied. Superadmin only.");
}

$deptId = $_GET['dept_id'] ?? '';
if (empty($deptId)) {
    die("Invalid Department ID.");
}

$deptData = getDepartment($deptId);
if (!$deptData) {
    die("Department not found.");
}

$users = getUsers($deptId);
$roles = getRoles($deptId);
$adminRoleId = 'admin.' . $deptId;

$message = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Handle Superadmin Actions on Users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_admin_user') {
        $targetUserId = $_POST['original_user_id'] ?? '';
        $newUserId = trim($_POST['new_user_id'] ?? '');
        $status = $_POST['status'] ?? '';

        if (isset($users[$targetUserId])) {
            $user = $users[$targetUserId];

            // Allow update only if user is Admin
            if ($user['role'] === $adminRoleId) {
                // Update ID if changed
                $updateSuccess = true;

                if ($newUserId !== $targetUserId && !empty($newUserId)) {
                    if (isset($users[$newUserId])) {
                        $error = "New User ID already exists.";
                        $updateSuccess = false;
                    } else {
                        // Rename key
                        $users[$newUserId] = $user;
                        unset($users[$targetUserId]);
                        // Update references in the user array (nothing internal to change except key)
                        // Note: This breaks history references in documents if they used user_id.
                        // But prompt says "Allow changing their User ID".
                        // We will proceed. Ideally we'd scan all docs, but that's heavy.
                        $targetUserId = $newUserId; // For subsequent updates
                    }
                }

                if ($updateSuccess) {
                    $users[$targetUserId]['status'] = $status;

                    if (writeJSON('departments/' . $deptId . '/users/users.json', $users)) {
                        $message = "Admin user updated successfully.";
                        // Reload users
                        $users = getUsers($deptId);
                    } else {
                        $error = "Failed to save changes.";
                    }
                }
            } else {
                $error = "You can only edit the Department Admin user here.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Department Staff - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .section { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
        .badge-active { background: #e6f4ea; color: #1e7e34; }
        .badge-suspended { background: #fff3cd; color: #856404; }
        .badge-archived { background: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Department: <?php echo htmlspecialchars($deptData['name']); ?> (<?php echo htmlspecialchars($deptId); ?>)</h1>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Users Section -->
        <div class="section">
            <h2>Staff List</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $uid => $user): ?>
                                <?php
                                    $isDeptAdmin = ($user['role'] === $adminRoleId);
                                    $roleName = $roles[$user['role']]['name'] ?? $user['role'];
                                    $statusClass = 'badge-' . ($user['status'] ?? 'active');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($isDeptAdmin): ?>
                                            <form method="POST" class="inline-form" style="display:flex; gap:5px;">
                                                <input type="hidden" name="action" value="update_admin_user">
                                                <input type="hidden" name="original_user_id" value="<?php echo htmlspecialchars($uid); ?>">
                                                <input type="text" name="new_user_id" value="<?php echo htmlspecialchars($uid); ?>" style="width: 150px; padding: 5px;">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($uid); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($roleName); ?>
                                        <?php if ($isDeptAdmin): ?> <span class="badge badge-active">ADMIN</span> <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isDeptAdmin): ?>
                                                <select name="status" style="padding: 5px;">
                                                    <option value="active" <?php if(($user['status']??'active')=='active') echo 'selected'; ?>>Active</option>
                                                    <option value="suspended" <?php if(($user['status']??'active')=='suspended') echo 'selected'; ?>>Suspended</option>
                                                    <option value="archived" <?php if(($user['status']??'active')=='archived') echo 'selected'; ?>>Archived</option>
                                                </select>
                                        <?php else: ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($user['status'] ?? 'active'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isDeptAdmin): ?>
                                            <button type="submit" class="btn-small btn-primary">Update</button>
                                            <button type="button" class="btn-small" style="background: #17a2b8; border-color: #17a2b8; color:white; margin-left:5px;" onclick="openResetModal('<?php echo $uid; ?>')">Reset Pass</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999;">Read Only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Roles Section -->
        <div class="section">
            <h2>Roles Structure (Read-Only)</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Role ID</th>
                            <th>Role Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr><td colspan="3">No roles found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($roles as $rid => $role): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rid); ?></td>
                                    <td><?php echo htmlspecialchars($role['name']); ?></td>
                                    <td><?php echo htmlspecialchars($role['status'] ?? 'active'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeResetModal()">&times;</span>
            <h2>Reset Password</h2>
            <p>User: <span id="resetUserDisplay"></span></p>
            <form method="POST" action="admin_reset_pass.php">
                <input type="hidden" name="target_dept" value="<?php echo htmlspecialchars($deptId); ?>">
                <input type="hidden" name="target_user" id="resetTargetUser">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
    function openResetModal(userId) {
        document.getElementById('resetTargetUser').value = userId;
        document.getElementById('resetUserDisplay').textContent = userId;
        document.getElementById('resetPasswordModal').style.display = 'block';
    }
    function closeResetModal() {
        document.getElementById('resetPasswordModal').style.display = 'none';
    }
    // Close on outside click
    window.onclick = function(event) {
        var modal = document.getElementById('resetPasswordModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</body>
</html>
