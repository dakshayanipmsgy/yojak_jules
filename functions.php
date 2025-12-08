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
 * Handles both Superadmin and Department users.
 *
 * @param string $deptId (Optional for Superadmin)
 * @param string $userId
 * @param string $password
 * @return array|bool Returns user data array on success, false on failure.
 */
function validateLogin($deptId, $userId, $password) {
    // 1. Check Superadmin
    if ($userId === 'admin' && empty($deptId)) {
        $config = readJSON('system/global_config.json');
        if ($config && isset($config['username']) && $config['username'] === 'admin') {
            if (password_verify($password, $config['password_hash'])) {
                 return ['role' => 'superadmin', 'user_id' => 'admin'];
            }
        }
        return false;
    }

    // 2. Check Department User
    if (empty($deptId)) {
        return false; // Department ID required for non-superadmin
    }

    $deptPath = 'departments/' . $deptId;
    // Check if department exists (via metadata or directory)
    if (!is_dir(STORAGE_PATH . '/' . $deptPath)) {
        return false;
    }

    $users = readJSON($deptPath . '/users/users.json');
    if (!$users || !isset($users[$userId])) {
        return false;
    }

    $userData = $users[$userId];
    if (password_verify($password, $userData['password'])) {
        return [
            'role_id' => $userData['role'],
            'user_id' => $userId,
            'dept_id' => $deptId
        ];
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
            'permissions' => 'all' // Full permissions for dept admin
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
            'role' => $roleId,
            'full_name' => 'Administrator'
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

/**
 * Checks if the current user has the required permission/role.
 *
 * @param string $requiredRole The role ID required.
 * @return bool
 */
function checkPermission($requiredRole) {
    if (!isset($_SESSION['role_id'])) {
        // If it's superadmin, they might have access, but contexts are usually separate.
        // If the check is for a department specific thing, superadmin shouldn't be here mostly unless specific override.
        // Prompt says "Sealed Room". Superadmin manages departments but is strictly prohibited from viewing or accessing internal department files.
        return false;
    }

    // Strict equality for now as per simple role requirement.
    // Ideally we might check permissions inside the role object, but prompt says "checkPermission($required_role)".
    return $_SESSION['role_id'] === $requiredRole;
}

/**
 * Converts a string to a clean slug.
 *
 * @param string $string
 * @return string
 */
function createSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    return $slug;
}

/**
 * Creates a new role in a department.
 *
 * @param string $deptId
 * @param string $roleName
 * @return array ['success' => bool, 'message' => string]
 */
function createRole($deptId, $roleName) {
    $roleSlug = createSlug($roleName);
    if (empty($roleSlug)) {
        return ['success' => false, 'message' => 'Invalid role name.'];
    }

    $roleId = $roleSlug . '.' . $deptId;
    $rolesPath = 'departments/' . $deptId . '/roles/roles.json';

    $roles = readJSON($rolesPath);
    if ($roles === null) {
        $roles = []; // Should exist if department exists
    }

    if (isset($roles[$roleId])) {
        return ['success' => false, 'message' => 'Role already exists.'];
    }

    $roles[$roleId] = [
        'name' => $roleName,
        'permissions' => [] // Default permissions
    ];

    if (writeJSON($rolesPath, $roles)) {
        return ['success' => true, 'message' => 'Role created successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to save role.'];
}

/**
 * Creates a new user in a department.
 *
 * @param string $deptId
 * @param string $fullName
 * @param string $password
 * @param string $roleId
 * @return array ['success' => bool, 'message' => string]
 */
function createUser($deptId, $fullName, $password, $roleId) {
    $usersPath = 'departments/' . $deptId . '/users/users.json';
    $users = readJSON($usersPath);
    if ($users === null) {
        $users = [];
    }

    // Extract role slug from role ID. Role ID format: {slug}.{dept_id}
    // We can just explode by '.' and take the first part? No, slug might have dots? No, slug uses underscores.
    // The format is strictly {slug}.{dept_id}.
    // However, if dept_id contains dots? No, dept_id is alphanumeric + underscores.

    // Let's rely on the roleId passed.
    // ID Generation Logic: user.{role_slug}.{dept_id}
    // We need role_slug.
    // roleId = {slug}.{deptId}
    // So role_slug = str_replace('.' . $deptId, '', $roleId);

    $roleSlug = str_replace('.' . $deptId, '', $roleId);

    $baseUserId = 'user.' . $roleSlug . '.' . $deptId;
    $userId = $baseUserId;

    // Handle Duplicates: append number (e.g., _01)
    $counter = 1;
    while (isset($users[$userId])) {
        $userId = $baseUserId . '_' . sprintf('%02d', $counter);
        $counter++;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $users[$userId] = [
        'full_name' => $fullName,
        'password' => $hashedPassword,
        'role' => $roleId
    ];

    if (writeJSON($usersPath, $users)) {
        return ['success' => true, 'message' => 'User created successfully. ID: ' . $userId];
    }

    return ['success' => false, 'message' => 'Failed to save user.'];
}

/**
 * Get department details.
 */
function getDepartment($deptId) {
    return readJSON('departments/' . $deptId . '/department.json');
}

/**
 * Get roles for a department.
 */
function getRoles($deptId) {
    return readJSON('departments/' . $deptId . '/roles/roles.json') ?? [];
}

/**
 * Get users for a department.
 */
function getUsers($deptId) {
    return readJSON('departments/' . $deptId . '/users/users.json') ?? [];
}

?>
