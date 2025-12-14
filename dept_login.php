<?php
session_start();
require_once 'functions.php';

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
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
        <p style="text-align:center; color: #666; font-size: 0.9em; margin-bottom: 20px;">Secure Access Portal</p>

        <?php if ($logoutMsg): ?>
            <div class="success-message"><?php echo htmlspecialchars($logoutMsg); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars(urldecode($error)); ?></div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <!-- Box 1: User ID -->
            <div class="form-group">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id"
                       placeholder="e.g. rahul.je.dws or admin"
                       required autocomplete="username">
            </div>

            <!-- Box 2: Password -->
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter Password" required autocomplete="current-password">
            </div>

            <button type="submit" style="margin-top: 15px;">Login</button>
        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Government of Yojak
        </div>
    </div>

</body>
</html>
