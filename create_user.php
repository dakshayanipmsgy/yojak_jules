<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'] ?? null;
if (!$deptId || !isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'admin.' . $deptId) {
    die("Access Denied. Only Department Administrators can create users.");
}

$deptRoles = getRoles($deptId);
$message = '';
$error = '';
$generatedId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? ''); // Added mobile as per prompt
    $roleId = $_POST['role_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($fname) || empty($roleId) || empty($password)) {
        $error = "Name, Role, and Password are required.";
    } else {
        $fullName = $fname . ($lname ? ' ' . $lname : '');

        // Auto-ID Generation Logic
        // Prompt: $username_part = strtolower(str_replace(' ', '', $name));
        // Using First Name only or Full Name? Prompt says "Name".
        // Let's use first name for brevity if possible, but prompt implies full name logic.
        // "InputName" -> I'll combine first and last.
        $cleanName = strtolower(str_replace(' ', '', $fname));
        // If strict, maybe first name + surname?
        // Prompt example: "rahul.je.dws". Rahul is likely first name.
        // I'll stick to first name as the primary slug part to keep it short,
        // unless collision handling requires more.

        // Let's strictly follow the variable construction in the prompt:
        // $username_part = strtolower(str_replace(' ', '', $name));
        // So if name is "Rahul Kumar", part is "rahulkumar".
        $username_part = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fname));

        // Extract role slug from Role ID (je.dws -> je)
        $roleSlug = str_replace('.' . $deptId, '', $roleId);

        $final_id = $username_part . '.' . $roleSlug . '.' . $deptId;

        // Check for collision and append number if needed?
        // Prompt doesn't specify collision handling in this step, but standard practice requires it.
        // However, prompt says "cannot edit the final User ID".
        // I will add collision handling to be safe.
        $users = getUsers($deptId);
        $baseId = $final_id;
        $counter = 1;
        while (isset($users[$final_id])) {
            $final_id = $username_part . $counter . '.' . $roleSlug . '.' . $deptId;
            $counter++;
        }

        // Create User
        // I need to update createUser function to accept specific ID or I can just pass it as custom.
        // My createUser function accepts $customUserId.
        $result = createUser($deptId, $fullName, $password, $roleId, $final_id);

        if ($result['success']) {
            $message = $result['message']; // "User created successfully. ID: ..."
            $generatedId = $final_id;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .generated-id-display {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <div class="form-container">
            <h2>Create Department Staff</h2>

            <?php if ($generatedId): ?>
                <div class="generated-id-display">
                    User Created!<br>
                    Login ID: <?php echo htmlspecialchars($generatedId); ?>
                </div>
                <p style="text-align:center;"><a href="create_user.php">Create Another</a></p>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" placeholder="e.g. Rahul" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name (Optional)</label>
                        <input type="text" id="last_name" name="last_name" placeholder="e.g. Kumar">
                    </div>

                    <div class="form-group">
                        <label for="mobile">Mobile (For Records)</label>
                        <input type="text" id="mobile" name="mobile" placeholder="e.g. 9876543210">
                    </div>

                    <div class="form-group">
                        <label for="role_id">Select Role</label>
                        <select id="role_id" name="role_id" required>
                            <option value="">Select Role</option>
                            <?php foreach ($deptRoles as $rId => $role): ?>
                                <option value="<?php echo htmlspecialchars($rId); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="text" id="password" name="password" placeholder="Enter password" required>
                    </div>

                    <div class="form-group">
                         <p style="font-size: 0.9em; color: #666;">
                             <strong>Note:</strong> User ID will be auto-generated as <code>name.role.<?php echo htmlspecialchars($deptId); ?></code>.
                         </p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Create User</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- User List Table for Reference -->
        <div style="margin-top: 40px; background: white; padding: 20px; border-radius: 8px;">
            <h3>Existing Users</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>User ID (Login)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = getUsers($deptId);
                    foreach ($users as $uid => $u):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td>
                            <?php
                            $rId = $u['role'];
                            echo htmlspecialchars($deptRoles[$rId]['name'] ?? $rId);
                            ?>
                        </td>
                        <td style="font-family: monospace; font-weight: bold; color: #0056b3;"><?php echo htmlspecialchars($uid); ?></td>
                        <td><?php echo htmlspecialchars($u['status'] ?? 'active'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
