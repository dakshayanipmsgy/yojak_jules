<?php
session_start();
require_once 'functions.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isSuperadmin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin');
$deptId = $_SESSION['dept_id'] ?? null;
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Superadmin Actions
    if ($isSuperadmin && $action === 'create_department') {
        $deptName = trim($_POST['dept_name'] ?? '');
        $newDeptId = trim($_POST['dept_id'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';

        if (empty($deptName) || empty($newDeptId) || empty($adminPassword)) {
            $error = "All fields are required.";
        } else {
            $result = createDepartment($deptName, $newDeptId, $adminPassword);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
    // Department Admin Actions
    elseif (!$isSuperadmin && $deptId) {
        // Verify user is admin of this department or has permission (for now assume Dept Admin role allows this)
        // The prompt says "Allow the Dept Admin to...".
        // We can check if role starts with 'admin.' or just assume Dashboard access implies it for now?
        // Prompt says: "If the user is a Department Admin (role: admin.{dept_id}), show the Department Dashboard."
        // We should double check role.

        $roleId = $_SESSION['role_id'];
        $isAdmin = ($roleId === 'admin.' . $deptId);

        if ($isAdmin) {
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
            } elseif ($action === 'create_user') {
                $fullName = trim($_POST['full_name'] ?? '');
                $password = $_POST['password'] ?? '';
                $targetRoleId = $_POST['role_id'] ?? '';

                if (empty($fullName) || empty($password) || empty($targetRoleId)) {
                    $error = "All fields are required.";
                } else {
                    $result = createUser($deptId, $fullName, $password, $targetRoleId);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        } else {
             $error = "Unauthorized action.";
        }
    }
}

// Data Fetching
$departments = [];
$deptData = null;
$deptRoles = [];
$deptUsers = [];

if ($isSuperadmin) {
    $departments = getAllDepartments();
} else {
    $deptData = getDepartment($deptId);
    $deptRoles = getRoles($deptId);
    $deptUsers = getUsers($deptId);
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

    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Yojak <?php echo $isSuperadmin ? 'Superadmin' : ($deptData['name'] ?? 'Department'); ?></h1>
            </div>
            <div class="header-right">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

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
                <!-- DEPARTMENT ADMIN VIEW -->

                <div class="dashboard-grid">
                    <!-- Manage Roles -->
                    <section class="card">
                        <div class="section-header">
                            <h2>Manage Roles</h2>
                        </div>
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="action" value="create_role">
                            <div class="form-group">
                                <input type="text" name="role_name" placeholder="New Role Name (e.g. Clerk)" required>
                            </div>
                            <button type="submit" class="btn-secondary">Add Role</button>
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
                            <button id="showCreateUserBtn" class="btn-primary">Add User</button>
                        </div>

                         <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>User ID</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deptUsers as $uId => $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($uId); ?></td>
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
                </div>

                <div id="createUserModal" class="modal">
                    <div class="modal-content">
                        <span class="close-btn" id="closeUserModal">&times;</span>
                        <h2>Create New User</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" placeholder="e.g. John Doe" required>
                            </div>
                            <div class="form-group">
                                <label for="role_id">Role</label>
                                <select id="role_id" name="role_id" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($deptRoles as $rId => $role): ?>
                                        <option value="<?php echo htmlspecialchars($rId); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="user_password">Password</label>
                                <input type="text" id="user_password" name="password" placeholder="Enter password" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // Modal Logic (Reused for both views)
        function setupModal(modalId, btnId, closeClass) {
            var modal = document.getElementById(modalId);
            var btn = document.getElementById(btnId);
            var span = document.querySelector(closeClass); // Might need specific selection

            if (modal && btn) {
                btn.onclick = function() {
                    modal.style.display = "block";
                }
            }

            // Assuming simplified global close logic or specific close buttons
        }

        // Superadmin Modal
        var saModal = document.getElementById("createDepartmentModal");
        var saBtn = document.getElementById("showCreateFormBtn");
        var saClose = document.getElementsByClassName("close-btn")[0];

        if (saModal && saBtn) {
            saBtn.onclick = function() { saModal.style.display = "block"; }
            if (saClose) saClose.onclick = function() { saModal.style.display = "none"; }
        }

        // Dept Admin Modal
        var daModal = document.getElementById("createUserModal");
        var daBtn = document.getElementById("showCreateUserBtn");
        var daClose = document.getElementById("closeUserModal");

        if (daModal && daBtn) {
            daBtn.onclick = function() { daModal.style.display = "block"; }
            if (daClose) daClose.onclick = function() { daModal.style.display = "none"; }
        }

        window.onclick = function(event) {
            if (event.target == saModal) {
                saModal.style.display = "none";
            }
            if (event.target == daModal) {
                daModal.style.display = "none";
            }
        }
    </script>
</body>
</html>
