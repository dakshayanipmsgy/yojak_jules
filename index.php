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

    $user = validateLogin($deptId, $userId, $password);

    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];

        if (isset($user['role']) && $user['role'] === 'superadmin') {
            $_SESSION['role_id'] = 'superadmin'; // Standardizing key
            $_SESSION['dept_id'] = null;
        } else {
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['dept_id'] = $user['dept_id'];
        }

        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Invalid credentials or Department ID.";
    }
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
