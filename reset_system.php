<?php
session_start();

// Security Check
$isSuperadmin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin');
$hardcodedPassword = "RESET_SECURE_PASSWORD"; // Change this in production
$providedPassword = $_REQUEST['password'] ?? '';

// Check CLI arguments if running from command line
if (php_sapi_name() === 'cli' && isset($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, 'password=') === 0) {
            $providedPassword = substr($arg, 9);
        }
    }
}

if (!$isSuperadmin && $providedPassword !== $hardcodedPassword) {
    die("Access Denied. You must be logged in as Superadmin or provide the reset password.");
}

define('STORAGE_PATH', __DIR__ . '/storage');
$departmentsDir = STORAGE_PATH . '/departments';

function recursiveDelete($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            recursiveDelete($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Perform Reset
if (is_dir($departmentsDir)) {
    $files = array_diff(scandir($departmentsDir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$departmentsDir/$file";
        if (is_dir($path)) {
            recursiveDelete($path);
        } else {
            unlink($path);
        }
    }

    // Check if departmentsDir still exists (we only deleted contents)
    if (!is_dir($departmentsDir)) {
        mkdir($departmentsDir, 0755, true);
    }
} else {
    // If it doesn't exist, create it
    mkdir($departmentsDir, 0755, true);
}

echo "All departments deleted. System clean.";
?>
