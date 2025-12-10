<?php
session_start();
require_once 'functions.php';

$error = '';

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = $_POST['dept_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $roleId = $_POST['role_id'] ?? '';

    // Superadmin Login Check (Bypass Dept/Role check if Dept is empty)
    if ($userId === 'admin' && empty($deptId)) {
        $config = readJSON('system/global_config.json');
        if ($config && isset($config['username']) && $config['username'] === 'admin') {
            if (password_verify($password, $config['password_hash'])) {
                $_SESSION['user_id'] = 'admin';
                $_SESSION['role_id'] = 'superadmin';
                $_SESSION['dept_id'] = null;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Error: Incorrect Password.";
            }
        } else {
            $error = "Error: Superadmin configuration missing.";
        }
    }
    // Department User Login Logic
    else {
        // Step A: Check Department
        if (empty($deptId)) {
            $error = "Error: Department ID is required.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $deptId)) {
            $error = "Error: Invalid Department ID format.";
        } else {
            $deptPath = STORAGE_PATH . '/departments/' . $deptId;
            if (!is_dir($deptPath)) {
                $error = "Error: Department '$deptId' not found.";
            } else {
                // Step B: Load User Data
                $userFile = $deptPath . '/users/users.json';
                if (!file_exists($userFile)) {
                    $error = "Error: User database missing for this department.";
                } else {
                    $jsonContent = file_get_contents($userFile);
                    $users_array = json_decode($jsonContent, true);

                    if ($users_array === null) {
                        $error = "Error: User database is corrupt.";
                    } elseif (!isset($users_array[$userId])) {
                        // Step C: Validate User Existence (Strict Key Lookup)
                        $error = "Error: User ID '$userId' not found in department '$deptId'.";
                    } else {
                        $user_data = $users_array[$userId];

                        // Step 3 (New): Validate Role Match
                        if (empty($roleId)) {
                            $error = "Error: Please select a Role.";
                        } elseif (!isset($user_data['role']) || $user_data['role'] !== $roleId) {
                            $error = "Error: This user does not belong to the selected Role.";
                        } else {
                            // Step D: Check Status
                            if (isset($user_data['status']) && $user_data['status'] !== 'active') {
                                $error = "Error: This account is " . $user_data['status'];
                            } else {
                                // Step E: Verify Password
                                if (password_verify($password, $user_data['password'])) {
                                    // Success: Start Session, set variables
                                    $_SESSION['user_id'] = $userId;
                                    $_SESSION['dept_id'] = $deptId;
                                    $_SESSION['role_id'] = $user_data['role'];

                                    header('Location: dashboard.php');
                                    exit;
                                } else {
                                    $error = "Error: Incorrect Password.";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$logoutMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $logoutMsg = "You have successfully logged out.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojak - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-step { margin-bottom: 15px; }
        .hidden { display: none; }
        .validation-msg { font-size: 0.9em; margin-top: 5px; }
        .text-green { color: green; }
        .text-red { color: red; }
    </style>
</head>
<body class="login-page">

    <div class="login-container">
        <div class="logo-placeholder">Y</div>
        <h1>Yojak Login</h1>

        <?php if ($logoutMsg): ?>
            <div class="success-message"><?php echo htmlspecialchars($logoutMsg); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <!-- Field 1: Department ID -->
            <div class="form-group">
                <label for="dept_id">Department ID</label>
                <input type="text" id="dept_id" name="dept_id" placeholder="Enter Department ID (Leave empty for Superadmin)" autocomplete="off">
                <div id="dept_msg" class="validation-msg"></div>
            </div>

            <!-- Field 2: Role (Hidden Initially) -->
            <div class="form-group hidden" id="role_container">
                <label for="role_id">Select Role</label>
                <select id="role_id" name="role_id">
                    <option value="">Select Role</option>
                </select>
            </div>

            <!-- Field 3: User ID -->
            <div class="form-group">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" placeholder="Enter User ID" required>
            </div>

            <!-- Field 4: Password -->
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter Password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Government of Yojak
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deptInput = document.getElementById('dept_id');
            const roleSelect = document.getElementById('role_id');
            const roleContainer = document.getElementById('role_container');
            const deptMsg = document.getElementById('dept_msg');

            function resetRoles() {
                roleContainer.classList.add('hidden');
                roleSelect.innerHTML = '<option value="">Select Role</option>';
                deptMsg.textContent = '';
                deptMsg.className = 'validation-msg';
            }

            deptInput.addEventListener('blur', function() {
                const deptId = this.value.trim();

                if (deptId === '') {
                    // Assume Superadmin login intent
                    resetRoles();
                    return;
                }

                // Call Public API
                fetch('ajax_public.php?action=get_dept_roles&dept_id=' + encodeURIComponent(deptId))
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            deptMsg.textContent = data.error;
                            deptMsg.className = 'validation-msg text-red';
                            roleContainer.classList.add('hidden');
                        } else {
                            deptMsg.textContent = 'Department Found';
                            deptMsg.className = 'validation-msg text-green';

                            // Populate Dropdown
                            roleSelect.innerHTML = '<option value="">Select Role</option>';
                            data.forEach(role => {
                                const option = document.createElement('option');
                                option.value = role.id;
                                option.textContent = role.name;
                                roleSelect.appendChild(option);
                            });

                            roleContainer.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        deptMsg.textContent = 'Error connecting to server.';
                        deptMsg.className = 'validation-msg text-red';
                    });
            });
        });
    </script>
</body>
</html>
