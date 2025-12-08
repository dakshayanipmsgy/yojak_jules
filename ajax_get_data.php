<?php
require_once 'auth_check.php';
require_once 'functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_depts') {
    $depts = [];
    $path = 'storage/departments/';
    if (is_dir($path)) {
        $dirs = scandir($path);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (is_dir($path . $dir)) {
                $deptJsonPath = 'departments/' . $dir . '/department.json'; // readJSON expects relative to storage
                $deptData = readJSON($deptJsonPath);
                if (isset($deptData['id']) && isset($deptData['name'])) {
                    $depts[] = [
                        'id' => $deptData['id'],
                        'name' => $deptData['name']
                    ];
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'data' => $depts]);
    exit;
}

if ($action === 'get_roles') {
    $deptId = $_GET['dept_id'] ?? '';
    if (!$deptId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing dept_id']);
        exit;
    }

    // Sanitize
    $deptId = basename($deptId);

    $rolesPath = 'departments/' . $deptId . '/roles/roles.json';
    $roles = readJSON($rolesPath);

    $outputRoles = [];
    if ($roles) {
        foreach ($roles as $id => $role) {
            if (isset($role['status']) && $role['status'] !== 'active') continue;
            $outputRoles[] = [
                'id' => $id,
                'name' => $role['name']
            ];
        }
    }

    echo json_encode(['status' => 'success', 'data' => $outputRoles]);
    exit;
}

if ($action === 'get_users') {
    $deptId = $_GET['dept_id'] ?? '';
    $roleId = $_GET['role_id'] ?? '';

    if (!$deptId || !$roleId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit;
    }

    // Sanitize
    $deptId = basename($deptId);

    $usersPath = 'departments/' . $deptId . '/users/users.json';
    $users = readJSON($usersPath);

    $outputUsers = [];
    if ($users) {
        foreach ($users as $id => $user) {
            if (isset($user['status']) && $user['status'] !== 'active') continue;
            if (isset($user['role']) && $user['role'] === $roleId) {
                $outputUsers[] = [
                    'id' => $id,
                    'full_name' => $user['full_name']
                ];
            }
        }
    }

    echo json_encode(['status' => 'success', 'data' => $outputUsers]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
