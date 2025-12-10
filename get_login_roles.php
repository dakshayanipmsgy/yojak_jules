<?php
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_GET['dept_id'])) {
    echo json_encode(['error' => 'Department ID is required']);
    exit;
}

$deptId = $_GET['dept_id'];

// Sanitize Dept ID
if (!preg_match('/^[a-zA-Z0-9_]+$/', $deptId)) {
    echo json_encode(['error' => 'Invalid Department ID format']);
    exit;
}

// Special case for Superadmin
if ($deptId === 'superadmin') {
    echo json_encode([
        [
            'id' => 'super_role',
            'name' => '⚡ System Superadmin'
        ]
    ]);
    exit;
}

$deptPath = STORAGE_PATH . '/departments/' . $deptId;
if (!is_dir($deptPath)) {
    echo json_encode(['error' => 'Department not found']);
    exit;
}

$roles = getRoles($deptId);
if (empty($roles)) {
    echo json_encode([]);
    exit;
}

$roleList = [];
foreach ($roles as $id => $roleData) {
    if (isset($roleData['status']) && $roleData['status'] !== 'active') {
        continue;
    }
    $roleList[] = [
        'id' => $id,
        'name' => $roleData['name']
    ];
}

echo json_encode($roleList);
