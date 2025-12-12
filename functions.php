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

    // Check Department Status
    $deptMeta = readJSON('departments/' . $deptId . '/department.json');
    if ($deptMeta && isset($deptMeta['status']) && $deptMeta['status'] !== 'active') {
        return false; // Department is suspended or archived
    }

    $users = readJSON($deptPath . '/users/users.json');
    if (!$users || !isset($users[$userId])) {
        return false;
    }

    $userData = $users[$userId];

    // Check User Status
    if (isset($userData['status']) && $userData['status'] !== 'active') {
        return false; // User is suspended or archived
    }

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
function createDepartment($name, $id, $password, $adminUserId = null) {
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
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'tier' => '1' // Default Tier 1
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
    // Enforce Strict Naming for Department Admin: {custom_id}.admin.{dept_id}
    if ($adminUserId) {
        $userId = $adminUserId . '.admin.' . $id;
    } else {
        $userId = 'user.admin.' . $id;
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $users = [
        $userId => [
            'password' => $hashedPassword,
            'role' => $roleId,
            'full_name' => 'Administrator',
            'status' => 'active'
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
 * @param string $customSlug (Optional)
 * @return array ['success' => bool, 'message' => string]
 */
function createRole($deptId, $roleName, $customSlug = null) {
    if ($customSlug) {
        $roleSlug = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '', $customSlug));
    } else {
        $roleSlug = createSlug($roleName);
    }

    if (empty($roleSlug)) {
        return ['success' => false, 'message' => 'Invalid role ID/name.'];
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
        'permissions' => [], // Default permissions
        'status' => 'active'
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
function createUser($deptId, $fullName, $password, $roleId, $customUserId = null) {
    $usersPath = 'departments/' . $deptId . '/users/users.json';
    $users = readJSON($usersPath);
    if ($users === null) {
        $users = [];
    }

    // Single Admin Constraint
    // Check if role is admin.{deptId}
    if ($roleId === 'admin.' . $deptId) {
        foreach ($users as $u) {
            if (isset($u['role']) && $u['role'] === $roleId) {
                // Check status. If status is missing, assume active.
                $status = $u['status'] ?? 'active';
                if ($status === 'active') {
                    return ['success' => false, 'message' => 'Critical: This department already has an active Administrator. Please archive or suspend the existing Admin before creating a new one.'];
                }
            }
        }
    }

    $userId = '';

    if (!empty($customUserId)) {
        // Custom ID Logic
        if (isset($users[$customUserId])) {
            return ['success' => false, 'message' => 'ID already taken.'];
        }
        $userId = $customUserId;
    } else {
        // Auto-Generate Logic
        $roleSlug = str_replace('.' . $deptId, '', $roleId);
        $baseUserId = 'user.' . $roleSlug . '.' . $deptId;
        $userId = $baseUserId;

        // Handle Duplicates: append number (e.g., _01)
        $counter = 1;
        while (isset($users[$userId])) {
            $userId = $baseUserId . '_' . sprintf('%02d', $counter);
            $counter++;
        }
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $users[$userId] = [
        'full_name' => $fullName,
        'password' => $hashedPassword,
        'role' => $roleId,
        'status' => 'active'
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

/**
 * Checks if a feature is allowed for the current department based on its Tier.
 *
 * @param string $featureName
 * @return bool
 */
function checkFeature($featureName) {
    if (!isset($_SESSION['dept_id'])) {
        return false;
    }

    // Priority: Session (cached) -> File (fallback)
    if (isset($_SESSION['dept_tier'])) {
        $tier = (int)$_SESSION['dept_tier'];
    } else {
        $deptId = $_SESSION['dept_id'];
        $deptMeta = readJSON('departments/' . $deptId . '/department.json');
        if (!$deptMeta) {
            return false;
        }
        $tier = isset($deptMeta['tier']) ? (int)$deptMeta['tier'] : 1; // Default to Tier 1 if missing
    }

    // Feature Requirements Mapping
    $requirements = [
        'ai_writer' => 2,
        'dak_register' => 2,
        'file_system' => 3,
        'note_sheets' => 3,
        'public_tracking' => 4,
        'backup' => 4
    ];

    if (isset($requirements[$featureName])) {
        return $tier >= $requirements[$featureName];
    }

    // Default: Allow if feature not listed (e.g. Tenders, Contractors)
    return true;
}

/**
 * Enforces feature access by stopping execution if the feature is not allowed.
 *
 * @param string $featureName
 */
function enforceFeature($featureName) {
    if (!checkFeature($featureName)) {
        // Fetch tier for the error message
        $current_tier = isset($_SESSION['dept_tier']) ? $_SESSION['dept_tier'] : 'Unknown';

        // If not in session, try to read from file to be helpful
        if ($current_tier === 'Unknown' && isset($_SESSION['dept_id'])) {
             $deptMeta = readJSON('departments/' . $_SESSION['dept_id'] . '/department.json');
             if ($deptMeta) {
                 $current_tier = isset($deptMeta['tier']) ? $deptMeta['tier'] : 1;
             }
        }

        http_response_code(403);
        die("<h1>Access Denied</h1><p>Your current plan (Tier " . $current_tier . ") does not include the <b>$featureName</b> feature. Please contact Superadmin to upgrade.</p>");
    }
}

/* ==========================================
   DOCUMENT MANAGEMENT FUNCTIONS
   ========================================== */

/**
 * Generates the next unique ID based on department settings.
 *
 * @param string $deptId
 * @param string $type 'doc' or 'wo'
 * @return string
 */
function getNextID($deptId, $type = 'doc') {
    $settingsPath = 'departments/' . $deptId . '/data/settings.json';
    $settings = readJSON($settingsPath);

    // Fallback if settings not configured
    if (!$settings) {
        if ($type === 'doc') return generateDocumentID(null); // Fallback to random
        return strtoupper($type) . '_' . date('Y') . '_' . rand(1000, 9999);
    }

    $prefixKey = $type . '_prefix';
    $counterKey = $type . '_counter';

    if (!isset($settings[$prefixKey]) || !isset($settings[$counterKey])) {
        if ($type === 'doc') return generateDocumentID(null);
        return strtoupper($type) . '_' . date('Y') . '_' . rand(1000, 9999);
    }

    $prefix = $settings[$prefixKey];
    $counter = (int)$settings[$counterKey];

    // Format: Documents 3 digits (001), Work Orders 2 digits (05) as per requirements
    $padding = ($type === 'doc') ? 3 : 2;
    $id = $prefix . str_pad($counter, $padding, '0', STR_PAD_LEFT);

    // Increment and Save
    $settings[$counterKey] = $counter + 1;
    writeJSON($settingsPath, $settings);

    return $id;
}

/**
 * Generates a unique Document ID.
 * Format: DOC_YYYY_RANDOM (Legacy) or Custom Sequence if Dept ID provided.
 *
 * @param string|null $deptId
 * @return string
 */
function generateDocumentID($deptId = null) {
    if ($deptId) {
        return getNextID($deptId, 'doc');
    }
    return 'DOC_' . date('Y') . '_' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Saves a document to the storage.
 *
 * @param string $deptId
 * @param string $docId
 * @param array $data Document data structure
 * @return bool
 */
function saveDocument($deptId, $docId, $data) {
    $path = 'departments/' . $deptId . '/documents/' . $docId . '.json';
    return writeJSON($path, $data);
}

/**
 * Retrieves a single document.
 *
 * @param string $deptId
 * @param string $docId
 * @return array|null
 */
function getDocument($deptId, $docId) {
    $path = 'departments/' . $deptId . '/documents/' . $docId . '.json';
    return readJSON($path);
}

/**
 * Appends an entry to the Master Department Log.
 *
 * @param string $deptId
 * @param string $entry Log entry string
 * @return bool
 */
function appendMasterLog($deptId, $entry) {
    $logPath = STORAGE_PATH . '/departments/' . $deptId . '/logs/master_log.txt';
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Moves a document to a new owner (Workflow).
 *
 * @param string $deptId
 * @param string $docId
 * @param string $targetUserId
 * @param string $currentUserId (The user performing the action)
 * @param string $newStatus (e.g., 'Pending Approval')
 * @return array ['success' => bool, 'message' => string]
 */
function moveDocument($deptId, $docId, $targetUserId, $currentUserId, $newStatus = 'Pending Approval', $dueDate = null) {
    // 1. Validate Target User
    $users = getUsers($deptId);
    if (!isset($users[$targetUserId])) {
        return ['success' => false, 'message' => 'Target user does not exist.'];
    }

    // 2. Load Document
    $doc = getDocument($deptId, $docId);
    if (!$doc) {
        return ['success' => false, 'message' => 'Document not found.'];
    }

    // 3. Update Document Data
    $oldOwner = $doc['current_owner'];
    $doc['current_owner'] = $targetUserId;
    $doc['status'] = $newStatus;
    if ($dueDate) {
        $doc['due_date'] = $dueDate;
    }

    // 4. Audit Log Layer A: Internal Document Log
    $logEntry = [
        'action' => 'moved',
        'from' => $currentUserId, // Using current user (actor) instead of old owner, though often they are same.
        'to' => $targetUserId,
        'time' => date('Y-m-d H:i:s')
    ];
    $doc['history'][] = $logEntry;

    // 5. Save Document
    if (saveDocument($deptId, $docId, $doc)) {
        // 6. Audit Log Layer B: Master Department Log
        $timestamp = date('Y-m-d H:i:s');
        $masterLogEntry = "[{$timestamp}] {$docId} moved from {$currentUserId} to {$targetUserId}. Status: {$newStatus}.";
        appendMasterLog($deptId, $masterLogEntry);

        return ['success' => true, 'message' => 'Document moved successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to save document changes.'];
}

/**
 * Gets all documents for a department.
 * Helper function for filtering.
 *
 * @param string $deptId
 * @return array Array of documents
 */
function getAllDocuments($deptId) {
    $dir = STORAGE_PATH . '/departments/' . $deptId . '/documents';
    if (!is_dir($dir)) {
        return [];
    }

    $documents = [];
    $files = scandir($dir);
    foreach ($files as $file) {
        if (strpos($file, '.json') !== false) {
            $doc = readJSON('departments/' . $deptId . '/documents/' . $file);
            if ($doc) {
                $documents[] = $doc;
            }
        }
    }
    return $documents;
}

/**
 * Get documents currently owned by the user (Inbox).
 */
function getInbox($deptId, $userId) {
    $allDocs = getAllDocuments($deptId);
    $inbox = [];
    foreach ($allDocs as $doc) {
        if (isset($doc['current_owner']) && $doc['current_owner'] === $userId) {
            $inbox[] = $doc;
        }
    }
    // Sort by created_at desc (optional)
    usort($inbox, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    return $inbox;
}

/**
 * Get documents created by the user but NOT owned by them (Outbox).
 */
function getOutbox($deptId, $userId) {
    $allDocs = getAllDocuments($deptId);
    $outbox = [];
    foreach ($allDocs as $doc) {
        if (isset($doc['created_by']) && $doc['created_by'] === $userId) {
            if (isset($doc['current_owner']) && $doc['current_owner'] !== $userId) {
                $outbox[] = $doc;
            }
        }
    }
    // Sort by created_at desc
    usort($outbox, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    return $outbox;
}

?>
