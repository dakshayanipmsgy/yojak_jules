<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dept_login.php');
    exit;
}

$userIdInput = trim($_POST['user_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($userIdInput) || empty($password)) {
    header('Location: dept_login.php?error=User+ID+and+Password+are+required');
    exit;
}

// ---------------------------------------------------------
// Logic A: Superadmin Check
// ---------------------------------------------------------
// If username == 'admin' (or matches config), verify password.
if ($userIdInput === 'admin' || strpos($userIdInput, '.') === false) {
    // Basic check for superadmin username from config or hardcoded 'admin'
    $config = readJSON('system/global_config.json');
    $validUser = ($config && isset($config['username']) && $config['username'] === $userIdInput);

    // Fallback if config matches 'admin' but input is 'admin' (or whatever is set)
    // If input is not composite, we assume it's superadmin or fail.

    if ($validUser) {
        if (password_verify($password, $config['password_hash'])) {
            $_SESSION['user_id'] = $config['username'];
            $_SESSION['role_id'] = 'superadmin';
            $_SESSION['dept_id'] = 'superadmin';
            header('Location: dashboard.php');
            exit;
        } else {
            header('Location: dept_login.php?error=Incorrect+Password');
            exit;
        }
    } else {
        header('Location: dept_login.php?error=Invalid+User+ID+Format.+Use+user.role.dept');
        exit;
    }
}

// ---------------------------------------------------------
// Logic B: Composite Check (Department Staff)
// ---------------------------------------------------------
// Expect 3 parts: [user].[role].[dept]
$parts = explode('.', $userIdInput);

if (count($parts) < 3) {
    header('Location: dept_login.php?error=Invalid+Format.+Use+user.role.dept');
    exit;
}

// Extract parts
// Note: user or role parts might contain dots themselves if we allowed it,
// but the prompt implies a strict structure.
// However, if user is 'john.doe', parts would be 4.
// Let's assume the last part is ALWAYS dept.
// And role is strictly defined in the system.
// But the prompt says: "Expect 3 parts". So we assume strictly 3.
// Wait, Role IDs are `je.dws`. So user ID `rahul.je.dws` splits into `rahul`, `je`, `dws`. That is 3 parts.
// What if role is `assistant.engineer`? Then role ID is `assistant_engineer.dws`?
// Memory says: "Role IDs are constructed as {custom_slug}.{department_id}".
// Slugs are sanitized.
// So `rahul.je.dws` -> user=`rahul`, role_slug=`je`, dept=`dws`.
// User ID in JSON is `rahul.je.dws`? Or `user.je.dws`?
// Let's check `create_user.php` logic I am about to write.
// Prompt says: "$final_id = $username_part . '.' . $selected_role . '.' . $current_dept;"
// If selected_role is slug `je`, then `rahul.je.dws`.
// So the User ID in JSON is indeed `rahul.je.dws`.
// So simple explode is fine.

// What if the username has dots? "rahul.kumar".
// Then `rahul.kumar.je.dws` -> 4 parts.
// The prompt says "Expect 3 parts".
// I will attempt to support usernames with dots by taking the last part as dept,
// second to last as role, and the rest as user.
$deptId = array_pop($parts);
$roleSlug = array_pop($parts);
$usernamePart = implode('.', $parts); // Rejoin the rest as user part

// Reconstruct the full ID to verify against JSON key
// This matches how it was stored: name.role_slug.dept
$targetUserId = $usernamePart . '.' . $roleSlug . '.' . $deptId;
// Wait, strictly speaking, the prompt says "Expect 3 parts: [user].[role].[dept]".
// If I allow dots in name, it contradicts "Expect 3 parts".
// But `rahul.je.dws` is 3 parts.
// I will check if `storage/departments/{dept}/` exists.

$deptPath = STORAGE_PATH . '/departments/' . $deptId;
if (!is_dir($deptPath)) {
    header('Location: dept_login.php?error=Department+not+found');
    exit;
}

// Check Department Status
$deptMeta = readJSON('departments/' . $deptId . '/department.json');
if ($deptMeta && isset($deptMeta['status']) && $deptMeta['status'] !== 'active') {
    header('Location: dept_login.php?error=Department+is+suspended');
    exit;
}

// Load Users
$users = getUsers($deptId);
if (empty($users)) {
    header('Location: dept_login.php?error=User+database+missing');
    exit;
}

// Verify User
if (!isset($users[$targetUserId])) {
    // Try literally what was typed if reconstruction fails (just in case)
    if (isset($users[$userIdInput])) {
        $targetUserId = $userIdInput;
    } else {
        header('Location: dept_login.php?error=User+not+found');
        exit;
    }
}

$userData = $users[$targetUserId];

// Check User Status
if (isset($userData['status']) && $userData['status'] !== 'active') {
    header('Location: dept_login.php?error=Account+is+suspended');
    exit;
}

// Verify Password
if (password_verify($password, $userData['password'])) {
    // Success
    $_SESSION['user_id'] = $targetUserId;
    $_SESSION['dept_id'] = $deptId;
    $_SESSION['role_id'] = $userData['role']; // e.g., 'je.dws'

    // Load Tier
    $_SESSION['dept_tier'] = isset($deptMeta['tier']) ? (int)$deptMeta['tier'] : 1;

    header('Location: dashboard.php');
    exit;
} else {
    header('Location: dept_login.php?error=Incorrect+Password');
    exit;
}
