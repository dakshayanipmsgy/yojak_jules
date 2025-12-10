<?php
require_once 'functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_dept_roles') {
    $deptId = $_GET['dept_id'] ?? '';

    // Validation
    if (empty($deptId) || !preg_match('/^[a-zA-Z0-9_]+$/', $deptId)) {
        echo json_encode(['error' => 'Invalid Department ID']);
        exit;
    }

    $rolesFile = STORAGE_PATH . "/departments/$deptId/roles/roles.json";

    if (file_exists($rolesFile)) {
        $rolesData = json_decode(file_get_contents($rolesFile), true);
        if ($rolesData) {
            $output = [];
            foreach ($rolesData as $roleId => $role) {
                $output[] = [
                    'id' => $roleId,
                    'name' => $role['name']
                ];
            }
            echo json_encode($output);
        } else {
            echo json_encode(['error' => 'Roles file is empty or corrupt']);
        }
    } else {
        echo json_encode(['error' => 'Department not found']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
