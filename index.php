<?php
session_start();
require_once 'functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = trim($_POST['dept_id'] ?? '');
    $userId = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // For Superadmin, we might assume a specific Dept ID or handle it specially.
    // The prompt says: initialize global_config.json with Superadmin user (User: admin, Pass: pass123).
    // It doesn't specify a Dept ID for superadmin, but the login form requires it.
    // I will assume "system" or empty dept ID for superadmin, or maybe the "User ID" 'admin' is unique enough.
    // Let's implement a simple check: if user is 'admin', check against global config.

    if ($userId === 'admin') {
        if (validateLogin($userId, $password)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = 'superadmin';
            // Redirect to dashboard (to be created later, for now just reload or show success)
            // header('Location: dashboard.php');
            // Since dashboard doesn't exist, we'll just show a logged in message.
        } else {
            $error = "Invalid credentials.";
        }
    } else {
        // Here we would check department users
        // For now, only superadmin exists.
        $error = "Department login not yet implemented.";
    }
}

// If logged in
if (isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Yojak - Dashboard</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="login-container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_id']); ?></h1>
            <p>You are logged in.</p>
            <a href="logout.php" style="color: var(--primary-color);">Logout</a>
        </div>
    </body>
    </html>
    <?php
    exit;
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
<body>

    <div class="login-container">
        <div class="logo-placeholder">Y</div>
        <h1>Yojak</h1>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="dept_id">Department ID</label>
                <input type="text" id="dept_id" name="dept_id" placeholder="Enter Department ID">
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
