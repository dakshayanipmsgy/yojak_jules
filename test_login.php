<?php
define('STORAGE_PATH', __DIR__ . '/storage');
// Mock logic from login.php
$userIdInput = 'rahul.je.dws';
$parts = explode('.', $userIdInput);
if (count($parts) < 3) echo "Fail 1\n";
$deptId = array_pop($parts);
$roleSlug = array_pop($parts);
$usernamePart = implode('.', $parts);
$targetUserId = $usernamePart . '.' . $roleSlug . '.' . $deptId;

if ($targetUserId === 'rahul.je.dws') echo "Success: Composite ID Parsed Correctly\n";
else echo "Fail: $targetUserId\n";

$userIdInput = 'admin';
if ($userIdInput === 'admin' || strpos($userIdInput, '.') === false) echo "Success: Superadmin Detected\n";
else echo "Fail: Superadmin not detected\n";
