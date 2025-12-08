<?php
session_start();
require_once 'functions.php';

$error = '';

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = trim($_POST['dept_id'] ?? '');
    $userId = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // Superadmin Login Check
    if ($userId === 'admin' && empty($deptId)) {
        $config = readJSON('system/global_config.json');
        if ($config && isset($config['username']) && $config['username'] === 'admin') {
            if (password_verify($password, $config['password_hash'])) {
                $_SESSION['user_id'] = 'admin';
                $_SESSION['role_id'] = 'superadmin';
                $_SESSION['dept_id'] = null;
                header('Location: dashboard.php');
                exit;
            } else {
                die("Error: Incorrect Password.");
            }
        }
        die("Error: Superadmin configuration missing.");
    }

    // Department User Login Logic

    // Step A: Check Department
    if (empty($deptId)) {
        die("Error: Department ID is required.");
    }

    // Sanitize Dept ID to prevent traversal
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $deptId)) {
        die("Error: Invalid Department ID format.");
    }

    $deptPath = STORAGE_PATH . '/departments/' . $deptId;
    if (!is_dir($deptPath)) {
        die("Error: Department '$deptId' not found.");
    }

    // Step B: Load User Data
    $userFile = $deptPath . '/users/users.json';
    if (!file_exists($userFile)) {
        die("Error: User database missing for this department.");
    }

    // Step C: Find the User (Key-based lookup)
    $jsonContent = file_get_contents($userFile);
    $users_array = json_decode($jsonContent, true);

    if ($users_array === null) {
        die("Error: User database is corrupt.");
    }

    if (!isset($users_array[$userId])) {
        // User ID does not exist as a key
        die("Error: User ID '$userId' not found in department '$deptId'.");
    }
    $user_data = $users_array[$userId];

    // Step D: Check Status
    if (isset($user_data['status']) && $user_data['status'] !== 'active') {
        die("Error: This account is " . $user_data['status']);
    }

    // Step E: Verify Password
    if (password_verify($password, $user_data['password'])) {
        // Success: Start Session, set variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['dept_id'] = $deptId;
        $_SESSION['role_id'] = $user_data['role'];

        header('Location: dashboard.php');
        exit;
    } else {
        die("Error: Incorrect Password.");
    }
}

$logoutMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $logoutMsg = "You have successfully logged out.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="logo-placeholder">Y</div>
        <h1>Yojak</h1>

        <?php if ($logoutMsg): ?>
            <div class="success-message"><?php echo htmlspecialchars($logoutMsg); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="dept_id">Department ID</label>
                <input type="text" id="dept_id" name="dept_id" placeholder="Enter Department ID (Leave empty for Superadmin)">
            </div>

            <div class="form-group">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" placeholder="Enter User ID" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter Password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Government of Yojak
        </div>
    </div>

</body>
</html>
