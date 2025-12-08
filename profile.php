<?php
require_once 'auth_check.php';
require_once 'functions.php';

$message = '';
$error = '';
$deptId = $_SESSION['dept_id'] ?? null;
$userId = $_SESSION['user_id'];
$isSuperadmin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        // Change Password Logic
        $success = false;

        if ($isSuperadmin) {
             // Handle Superadmin Password Change
             // Assuming superadmin is in system/global_config.json
             $configPath = 'system/global_config.json';
             $config = readJSON($configPath);

             if ($config && isset($config['username']) && $config['username'] === $userId) {
                 if (password_verify($oldPassword, $config['password_hash'])) {
                     $config['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                     if (writeJSON($configPath, $config)) {
                         $success = true;
                     } else {
                         $error = "Failed to update configuration.";
                     }
                 } else {
                     $error = "Incorrect old password.";
                 }
             } else {
                 $error = "Superadmin configuration not found.";
             }

        } elseif ($deptId) {
            // Handle Department User Password Change
            $usersPath = 'departments/' . $deptId . '/users/users.json';
            $users = readJSON($usersPath);

            if ($users && isset($users[$userId])) {
                if (password_verify($oldPassword, $users[$userId]['password'])) {
                    $users[$userId]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);

                    if (writeJSON($usersPath, $users)) {
                        $success = true;
                        // Audit Log
                        appendMasterLog($deptId, "[".date('Y-m-d H:i:s')."] User {$userId} changed password.");
                    } else {
                        $error = "Failed to update user record.";
                    }
                } else {
                    $error = "Incorrect old password.";
                }
            } else {
                $error = "User not found.";
            }
        } else {
            $error = "Invalid session state.";
        }

        if ($success) {
            $message = "Password changed successfully.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container" style="max-width: 600px; margin: 40px auto; padding: 20px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h1>My Profile</h1>

        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="old_password">Old Password</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-primary">Change Password</button>
        </form>
    </div>

</body>
</html>
