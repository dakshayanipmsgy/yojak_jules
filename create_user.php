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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['first_name'] ?? '');
    $lname = trim($_POST['last_name'] ?? '');
    $roleId = $_POST['role_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $customUserId = trim($_POST['custom_user_id'] ?? '');

    if (empty($fname) || empty($lname) || empty($roleId) || empty($password)) {
        $error = "All fields except User ID are required.";
    } else {
        $fullName = $fname . ' ' . $lname;
        $result = createUser($deptId, $fullName, $password, $roleId, $customUserId);

        if ($result['success']) {
            $message = $result['message'];
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
    <title>Create User - Yojak</title>
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
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <div class="form-container">
            <h2>Create New User</h2>

            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
                <p><a href="dashboard.php">Back to Dashboard</a> | <a href="create_user.php">Create Another</a></p>
            <?php else: ?>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="e.g. John" required oninput="generateUserID()">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="e.g. Doe" required oninput="generateUserID()">
                </div>

                <div class="form-group">
                    <label for="role_id">Role</label>
                    <select id="role_id" name="role_id" required>
                        <option value="">Select Role</option>
                        <?php foreach ($deptRoles as $rId => $role): ?>
                            <option value="<?php echo htmlspecialchars($rId); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="custom_user_id">User ID (Custom)</label>
                    <input type="text" id="custom_user_id" name="custom_user_id" placeholder="Auto-generated if empty">
                    <small>Default: First letter of First Name + Surname (lowercase).</small>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="text" id="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Create User</button>
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function generateUserID() {
            const fname = document.getElementById('first_name').value.trim();
            const lname = document.getElementById('last_name').value.trim();
            const userIdField = document.getElementById('custom_user_id');

            if (fname && lname) {
                // First Letter of First Name + Surname
                const suggestion = (fname.charAt(0) + lname).toLowerCase();
                // We only auto-fill if the user hasn't manually edited it drastically,
                // but since it's a suggestion, let's just update it.
                // Or maybe only if it's empty or matches previous suggestion?
                // The prompt says: "When 'First Name' and 'Last Name' are typed, use JavaScript to auto-fill this field"
                // "Editable: The Admin can manually change this value before submitting."
                // So I will just overwrite it as they type unless they focus on it?
                // A common pattern is to only update if it was not manually touched.
                // For simplicity as per prompt, I will just update it.
                // But this might be annoying if they edited it.
                // Let's check if the field is currently focused.
                if (document.activeElement !== userIdField) {
                     userIdField.value = suggestion.replace(/[^a-z0-9_]/g, '');
                }
            }
        }
    </script>
</body>
</html>
