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

?>
