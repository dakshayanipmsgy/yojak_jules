<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Access Control: Superadmin Only
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'superadmin') {
    die("Access Denied. Superadmin only.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetDept = $_POST['target_dept'] ?? '';
    $targetUser = $_POST['target_user'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($targetDept) || empty($targetUser) || empty($newPass) || empty($confirmPass)) {
        header("Location: admin_view_dept.php?dept_id=$targetDept&error=All fields required");
        exit;
    }

    if ($newPass !== $confirmPass) {
        header("Location: admin_view_dept.php?dept_id=$targetDept&error=Passwords do not match");
        exit;
    }

    // Load Users
    $usersPath = 'departments/' . $targetDept . '/users/users.json';
    $users = readJSON($usersPath);

    if (!$users || !isset($users[$targetUser])) {
        header("Location: admin_view_dept.php?dept_id=$targetDept&error=User not found");
        exit;
    }

    // Update Password
    $users[$targetUser]['password'] = password_hash($newPass, PASSWORD_DEFAULT);

    if (writeJSON($usersPath, $users)) {
        // Log Action
        appendMasterLog($targetDept, "[".date('Y-m-d H:i:s')."] Superadmin reset password for {$targetUser}.");
        header("Location: admin_view_dept.php?dept_id=$targetDept&msg=Password reset successfully");
        exit;
    } else {
        header("Location: admin_view_dept.php?dept_id=$targetDept&error=Failed to save changes");
        exit;
    }
} else {
    // Redirect if accessed directly
    header("Location: dashboard.php");
    exit;
}
?>
