<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Check if user is Department Admin
$deptId = $_SESSION['dept_id'] ?? '';
$roleId = $_SESSION['role_id'] ?? '';

if (empty($deptId) || $roleId !== 'admin.' . $deptId) {
    die("Access Denied. Department Administrators only.");
}

$sourcePath = STORAGE_PATH . '/departments/' . $deptId;
if (!is_dir($sourcePath)) {
    die("Department data not found.");
}

$zipFilename = 'backup_' . $deptId . '_' . date('Ymd_His') . '.zip';
$tempZipPath = sys_get_temp_dir() . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Failed to create zip file.");
}

/**
 * Recursive function to add files to zip
 */
function addFolderToZip($dir, $zipArchive, $zipDir = '') {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            // Add empty directory
            if (!empty($zipDir)) {
                 $zipArchive->addEmptyDir($zipDir);
            }

            while (($file = readdir($dh)) !== false) {
                if ($file === '.' || $file === '..') continue;

                $fullPath = $dir . '/' . $file;
                $relativePath = $zipDir ? $zipDir . '/' . $file : $file;

                if (is_dir($fullPath)) {
                    addFolderToZip($fullPath, $zipArchive, $relativePath);
                } else {
                    $zipArchive->addFile($fullPath, $relativePath);
                }
            }
            closedir($dh);
        }
    }
}

addFolderToZip($sourcePath, $zip);
$zip->close();

if (file_exists($tempZipPath)) {
    // Clean output buffer to prevent file corruption
    if (ob_get_level()) ob_end_clean();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($tempZipPath));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tempZipPath);

    // Cleanup
    unlink($tempZipPath);
    exit;
} else {
    die("Failed to generate backup file.");
}
?>
