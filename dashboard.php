<?php
session_start();
require_once 'functions.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Handle Create Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_department') {
    $deptName = trim($_POST['dept_name'] ?? '');
    $deptId = trim($_POST['dept_id'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if (empty($deptName) || empty($deptId) || empty($adminPassword)) {
        $error = "All fields are required.";
    } else {
        $result = createDepartment($deptName, $deptId, $adminPassword);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$departments = getAllDepartments();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - Superadmin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Yojak Superadmin</h1>
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

        </main>
    </div>

    <script>
        // Simple Modal Logic
        var modal = document.getElementById("createDepartmentModal");
        var btn = document.getElementById("showCreateFormBtn");
        var span = document.getElementsByClassName("close-btn")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        }

        span.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
