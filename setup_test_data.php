<?php
require_once 'functions.php';

// Create dws department if not exists
if (!is_dir(STORAGE_PATH . '/departments/dws')) {
    $res = createDepartment("DWS Dept", "dws", "password123");
    echo "Create Dept: " . json_encode($res) . "\n";
} else {
    echo "Dept dws exists.\n";
}

// Create role admin.dws (should be created by createDepartment)
// But let's check roles
$roles = getRoles('dws');
$adminRole = 'admin.dws';
if (!isset($roles[$adminRole])) {
    echo "Admin role missing??\n";
} else {
    echo "Admin role exists.\n";
}

// Create user anish.admin.dws
// createUser function logic:
// if customUserId provided, use it.
// prompt wants "anish.admin.dws"
// createUser(deptId, fullName, password, roleId, customUserId)
$targetUser = 'anish.admin.dws';
$users = getUsers('dws');
if (!isset($users[$targetUser])) {
    $res = createUser('dws', 'Anish Admin', 'password123', 'admin.dws', 'anish.admin.dws');
    echo "Create User: " . json_encode($res) . "\n";
} else {
    echo "User anish.admin.dws exists.\n";
}
?>
