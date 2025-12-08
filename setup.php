<?php
// System Health Check Script
// This script checks the file-based "database" health.

echo "<h1>System Health Check</h1>";

$storagePath = __DIR__ . '/storage';
$configPath = $storagePath . '/system/global_config.json';
$errors = [];

// 1. Check if storage/ exists
if (!is_dir($storagePath)) {
    $errors[] = "CRITICAL: 'storage' folder does not exist.";
} else {
    // 2. Check if PHP can write to storage/
    $testFile = $storagePath . '/test_write_permission.tmp';
    $handle = @fopen($testFile, 'w');
    if ($handle) {
        fwrite($handle, 'test');
        fclose($handle);
        unlink($testFile); // Clean up
    } else {
        $errors[] = "Error: PHP cannot write to the storage folder. Please set Linux permissions to 775 or 777.";
    }
}

// 3. Check if global_config.json exists
if (!file_exists($configPath)) {
    $errors[] = "WARNING: 'storage/system/global_config.json' is missing.";
}

// Output Results
if (empty($errors)) {
    echo '<p style="color: green; font-weight: bold; font-size: 1.2rem;">System Status: Ready. Storage is Writable.</p>';
} else {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 1rem; border: 1px solid #f5c6cb; border-radius: 4px;">';
    foreach ($errors as $error) {
        echo "<p>❌ $error</p>";
    }
    echo '</div>';
}
?>
