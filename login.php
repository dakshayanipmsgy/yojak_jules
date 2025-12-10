<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$deptId = $_POST['dept_id'] ?? '';
$roleId = $_POST['role_id'] ?? '';
$userId = $_POST['user_id'] ?? '';
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
            // Use header with query param for error display on index.php
            header('Location: index.php?error=Incorrect+Password');
            exit;
        }
    }
    header('Location: index.php?error=Superadmin+configuration+missing');
    exit;
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

// Step C: Find the User (Key-based lookup)
if (!isset($users[$userId])) {
    header('Location: index.php?error=User+ID+not+found');
    exit;
}
$userData = $users[$userId];

// Check Role Match
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
    $_SESSION['user_id'] = $userId;
    $_SESSION['dept_id'] = $deptId;
    $_SESSION['role_id'] = $userData['role'];

    header('Location: dashboard.php');
    exit;
} else {
    header('Location: index.php?error=Incorrect+Password');
    exit;
}
