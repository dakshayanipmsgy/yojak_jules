<?php
session_start();
require_once 'functions.php';

// Security Check
$isSuperAdmin = isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin';

// Check if departments exist
$deptPath = STORAGE_PATH . '/departments';
$hasDepartments = false;
if (is_dir($deptPath)) {
    $items = scandir($deptPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitkeep') continue;
        if (is_dir($deptPath . '/' . $item)) {
            $hasDepartments = true;
            break;
        }
    }
}

// Allow if Superadmin OR System is fresh (no departments)
if (!$isSuperAdmin && $hasDepartments) {
    die("Access Denied: Only Superadmin can perform a factory reset on an active system.");
}

function recursiveDelete($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? recursiveDelete("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

// Perform Reset
if (is_dir($deptPath)) {
    $depts = scandir($deptPath);
    foreach ($depts as $dept) {
        if ($dept === '.' || $dept === '..' || $dept === '.gitkeep') continue;
        recursiveDelete($deptPath . '/' . $dept);
    }
}

echo "All Departments deleted. System is clean.";
?>
