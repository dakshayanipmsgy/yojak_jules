<?php

// Define base storage path
define('STORAGE_PATH', __DIR__ . '/storage');

/**
 * Reads JSON from a file and returns as an associative array.
 *
 * @param string $filepath Relative path within storage folder (e.g., 'system/global_config.json')
 * @return array|null Returns array on success, null on failure.
 */
function readJSON($filepath) {
    // Prevent directory traversal
    if (strpos($filepath, '..') !== false) {
        return null;
    }

    $fullPath = STORAGE_PATH . '/' . $filepath;

    if (!file_exists($fullPath)) {
        return null;
    }

    $content = file_get_contents($fullPath);
    if ($content === false) {
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

/**
 * Writes data to a JSON file safely using file locking.
 *
 * @param string $filepath Relative path within storage folder.
 * @param array $data Data to write.
 * @return bool True on success, False on failure.
 */
function writeJSON($filepath, $data) {
    // Prevent directory traversal
    if (strpos($filepath, '..') !== false) {
        return false;
    }

    $fullPath = STORAGE_PATH . '/' . $filepath;
    $dir = dirname($fullPath);

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }

    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    // Write with exclusive lock
    return file_put_contents($fullPath, $json, LOCK_EX) !== false;
}

/**
 * Generates a unique ID with a prefix.
 *
 * @param string $prefix
 * @return string
 */
function generateID($prefix = '') {
    return uniqid($prefix . '_', true);
}

/**
 * Validates user credentials.
 * Currently only supports Superadmin.
 *
 * @param string $username
 * @param string $password
 * @return bool
 */
function validateLogin($username, $password) {
    // For now, only check system admin
    // In future, this would check department users too

    $config = readJSON('system/global_config.json');
    if ($config && $username === $config['username']) {
        return password_verify($password, $config['password_hash']);
    }

    return false;
}

/**
 * Creates a new department with the specified structure and initial admin user.
 *
 * @param string $name Department Name
 * @param string $id Department ID (unique, alphanumeric)
 * @param string $password Initial Admin Password
 * @return array ['success' => bool, 'message' => string]
 */
function createDepartment($name, $id, $password) {
    // Basic validation
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $id)) {
        return ['success' => false, 'message' => 'Invalid Department ID. Use alphanumeric and underscores only.'];
    }

    $deptPath = STORAGE_PATH . '/departments/' . $id;
    if (is_dir($deptPath)) {
        return ['success' => false, 'message' => 'Department ID already exists.'];
    }

    // Create directories
    $subdirs = ['users', 'roles', 'documents', 'templates', 'logs'];
    if (!mkdir($deptPath, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create department directory.'];
    }

    foreach ($subdirs as $subdir) {
        if (!mkdir($deptPath . '/' . $subdir, 0755, true)) {
             return ['success' => false, 'message' => "Failed to create subdirectory: $subdir"];
        }
    }

    // Create department.json (metadata)
    $metadata = [
        'name' => $name,
        'id' => $id,
        'created_at' => date('Y-m-d H:i:s')
    ];
    if (!writeJSON('departments/' . $id . '/department.json', $metadata)) {
        return ['success' => false, 'message' => 'Failed to create department metadata.'];
    }

    // Create roles/roles.json
    $roleId = 'admin.' . $id;
    $roles = [
        $roleId => [
            'name' => 'Department Administrator',
            'permissions' => 'all'
        ]
    ];
    if (!writeJSON('departments/' . $id . '/roles/roles.json', $roles)) {
        return ['success' => false, 'message' => 'Failed to create roles file.'];
    }

    // Create users/users.json
    $userId = 'user.admin.' . $id;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $users = [
        $userId => [
            'password' => $hashedPassword,
            'role' => $roleId
        ]
    ];
    if (!writeJSON('departments/' . $id . '/users/users.json', $users)) {
        return ['success' => false, 'message' => 'Failed to create users file.'];
    }

    return ['success' => true, 'message' => 'Department created successfully.'];
}

/**
 * Retrieves a list of all departments with metadata and user count.
 *
 * @return array List of departments
 */
function getAllDepartments() {
    $departments = [];
    $path = STORAGE_PATH . '/departments';
    if (!is_dir($path)) {
         return [];
    }
    $dirs = scandir($path);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        if (is_dir($path . '/' . $dir)) {
            $meta = readJSON('departments/' . $dir . '/department.json');
            if ($meta) {
                 $userCount = 0;
                 $users = readJSON('departments/' . $dir . '/users/users.json');
                 if ($users) {
                     $userCount = count($users);
                 }
                 $meta['user_count'] = $userCount;
                 $departments[] = $meta;
            }
        }
    }
    return $departments;
}

?>
