<?php
// debug_inspector.php
// Debug tool to inspect department users and diagnose login issues.
// WARNING: Unprotected for debugging purposes. Delete after use.

$dept_id = $_GET['dept_id'] ?? '';
$results = [];

if ($dept_id) {
    // Sanitization: Ensure dept_id only contains alphanumeric chars to prevent traversal
    $dept_id = basename($dept_id);

    $base_dir = __DIR__ . '/storage/departments/' . $dept_id;

    // Step 1: Folder Check
    if (is_dir($base_dir)) {
        $results['folder'] = "✅ Department Folder Found: " . htmlspecialchars($base_dir);

        // Step 2: File Check
        $users_file = $base_dir . '/users/users.json';
        if (file_exists($users_file)) {
            $results['file'] = "✅ Users JSON Found: " . htmlspecialchars($users_file);

            // Step 3: Data Dump
            $json_content = file_get_contents($users_file);
            $users = json_decode($json_content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($users)) {
                $results['users'] = $users;
            } else {
                $results['error'] = "❌ Error decoding JSON: " . json_last_error_msg();
            }
        } else {
            $results['file'] = "❌ JSON Missing: " . htmlspecialchars($users_file);
        }
    } else {
        $results['folder'] = "❌ Folder Missing: " . htmlspecialchars($base_dir);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Inspector (Debug)</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .result-box { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px; }
        .status { margin-bottom: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        code { background: #eee; padding: 2px 4px; border-radius: 3px; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Inspector (Debug)</h1>
        <p>Enter a Department ID to inspect its user data.</p>

        <form method="GET">
            <div class="form-group">
                <label for="dept_id">Department ID:</label>
                <input type="text" id="dept_id" name="dept_id" value="<?php echo htmlspecialchars($dept_id); ?>" placeholder="e.g., dws">
            </div>
            <button type="submit">Inspect</button>
        </form>

        <?php if ($dept_id): ?>
            <div class="result-box">
                <div class="status"><?php echo isset($results['folder']) ? $results['folder'] : ''; ?></div>
                <div class="status"><?php echo isset($results['file']) ? $results['file'] : ''; ?></div>
                <div class="status error"><?php echo isset($results['error']) ? $results['error'] : ''; ?></div>

                <?php if (isset($results['users'])): ?>
                    <h3>User List</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Role ID</th>
                                <th>Status</th>
                                <th>Password Hash (Prefix)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['users'] as $user_id => $user_data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user_id); ?></td>
                                    <td><?php echo htmlspecialchars($user_data['role'] ?? $user_data['role_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user_data['status'] ?? 'N/A'); ?></td>
                                    <td>
                                        <code>
                                            <?php
                                            $hash = $user_data['password_hash'] ?? $user_data['password'] ?? '';
                                            echo htmlspecialchars(substr($hash, 0, 10)) . '...';
                                            ?>
                                        </code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
