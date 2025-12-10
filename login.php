<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$deptId = $_POST['dept_id'] ?? '';
$roleId = $_POST['role_id'] ?? '';
$userIdInput = $_POST['user_id'] ?? ''; // Renamed to userIdInput to distinguish from resolved userId
$password = $_POST['password'] ?? '';

// Superadmin Login Check
if ($deptId === 'superadmin') {
    $config = readJSON('system/global_config.json');

    // Check if configuration is valid and user ID matches
    // Use input directly for superadmin
    $validUser = ($config && isset($config['username']) && $config['username'] === $userIdInput);

    if (!$validUser) {
        header('Location: index.php?error=Invalid+Superadmin+User');
        exit;
    }

    if (password_verify($password, $config['password_hash'])) {
        $_SESSION['user_id'] = $config['username'];
        $_SESSION['role_id'] = 'superadmin';
        $_SESSION['dept_id'] = 'superadmin';

        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: index.php?error=Incorrect+Password');
        exit;
    }
}

// Department User Login Logic

// Step A: Check Department
if (empty($deptId)) {
    header('Location: index.php?error=Department+ID+is+required');
    exit;
}

// Sanitize Dept ID
if (!preg_match('/^[a-zA-Z0-9_]+$/', $deptId)) {
    header('Location: index.php?error=Invalid+Department+ID+format');
    exit;
}

$deptPath = STORAGE_PATH . '/departments/' . $deptId;
if (!is_dir($deptPath)) {
    header('Location: index.php?error=Department+not+found');
    exit;
}

// Step B: Load User Data
$users = getUsers($deptId);
if (empty($users)) {
    header('Location: index.php?error=User+database+missing');
    exit;
}

// Step C: Find the User (Smart Reconstruction Logic)
$finalUserId = null;
$userData = null;

// Construct potential ID (Prefix + Role)
$constructedId = $userIdInput . '.' . $roleId;

if (isset($users[$constructedId])) {
    // Attempt 2: Constructed ID exists
    $finalUserId = $constructedId;
    $userData = $users[$constructedId];
} elseif (isset($users[$userIdInput])) {
    // Attempt 1: Input matches existing ID directly (e.g. custom ID)
    $finalUserId = $userIdInput;
    $userData = $users[$userIdInput];
} else {
    // Neither found
    header('Location: index.php?error=User+ID+not+found');
    exit;
}

// Check Role Match
// Note: If we found via constructed ID, role match is implicit but good to verify.
// If found via direct ID, we must check if the user actually holds the selected role.
if ($userData['role'] !== $roleId) {
    header('Location: index.php?error=User+exists+but+does+not+hold+the+selected+Role');
    exit;
}

// Step D: Check Status
if (isset($userData['status']) && $userData['status'] !== 'active') {
    $status = $userData['status'];
    header("Location: index.php?error=Account+is+$status");
    exit;
}

// Step E: Verify Password
if (password_verify($password, $userData['password'])) {
    // Success: Start Session, set variables
    $_SESSION['user_id'] = $finalUserId;
    $_SESSION['dept_id'] = $deptId;
    $_SESSION['role_id'] = $userData['role'];

    header('Location: dashboard.php');
    exit;
} else {
    header('Location: index.php?error=Incorrect+Password');
    exit;
}
