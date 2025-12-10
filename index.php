<?php
session_start();
require_once 'functions.php';

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
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
        .hidden { display: none; }
        .text-red-500 { color: #ef4444; }
        .text-sm { font-size: 0.875rem; }
        .reset-btn {
            background-color: #6b7280;
            margin-top: 15px;
            margin-left: 10px;
        }
        .reset-btn:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body class="login-page">

    <div class="login-container">
        <div class="logo-placeholder">Y</div>
        <h1>Yojak</h1>

        <?php if ($logoutMsg): ?>
            <div class="success-message"><?php echo htmlspecialchars($logoutMsg); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars(urldecode($error)); ?></div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php" autocomplete="off">
            <!-- Box 1: Department ID -->
            <div class="form-group">
                <label for="dept_id">Department ID</label>
                <!-- Anti-Autofill Strategy: Unique name, readonly/onfocus hack -->
                <input type="text" id="dept_id" name="yojak_dept_id_search" placeholder="Enter Department ID (e.g. dws)"
                       required autocomplete="off" readonly onfocus="this.removeAttribute('readonly');">
                <div id="dept_error" class="text-red-500 text-sm hidden" style="margin-top: 5px;"></div>
            </div>

            <!-- Box 2: Role (Initially Hidden) -->
            <div class="form-group hidden" id="role_group">
                <label for="role_id">Role</label>
                <select id="role_id" name="role_id">
                    <option value="">Select Role</option>
                </select>
            </div>

            <!-- Box 3: User ID (Initially Hidden) -->
            <div class="form-group hidden" id="user_group">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" placeholder="Enter User ID (e.g. anish)" required>
            </div>

            <!-- Box 4: Password (Initially Hidden) -->
            <div class="form-group hidden" id="password_group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter Password" required>
                <div style="display: flex; align-items: center;">
                    <button type="submit" style="margin-top: 15px;">Login</button>
                    <button type="button" id="reset_btn" class="reset-btn">Reset</button>
                </div>
            </div>

            <!-- Superadmin override helper (hidden logic handled by JS/Backend) -->
            <div id="superadmin_notice" class="hidden" style="margin-top:10px; font-size: 0.9em; color: #666;">
                Superadmin Login detected.
            </div>

        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Government of Yojak
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deptInput = document.getElementById('dept_id');
            const deptError = document.getElementById('dept_error');
            const roleSelect = document.getElementById('role_id');
            const roleGroup = document.getElementById('role_group');
            const userGroup = document.getElementById('user_group');
            const passwordGroup = document.getElementById('password_group');
            const superadminNotice = document.getElementById('superadmin_notice');
            const resetBtn = document.getElementById('reset_btn');
            const userInput = document.getElementById('user_id');
            const passwordInput = document.getElementById('password');

            function resetFields() {
                roleGroup.classList.add('hidden');
                userGroup.classList.add('hidden');
                passwordGroup.classList.add('hidden');
                roleSelect.innerHTML = '<option value="">Select Role</option>';
                superadminNotice.classList.add('hidden');
                // Don't clear dept input here as user might be typing
            }

            function clearForm() {
                deptInput.value = '';
                // Re-apply readonly to prevent immediate autofill if they click back in
                deptInput.setAttribute('readonly', 'readonly');
                userInput.value = '';
                passwordInput.value = '';
                roleSelect.value = '';
                deptError.textContent = '';
                deptError.classList.add('hidden');
                resetFields();
            }

            resetBtn.addEventListener('click', clearForm);

            deptInput.addEventListener('blur', function() {
                const deptId = this.value.trim();

                // Clear previous error
                deptError.textContent = '';
                deptError.classList.add('hidden');

                // Reset subsequent fields when dept changes
                resetFields();

                if (deptId === '') {
                    // Just return, do nothing if empty
                    return;
                }

                if (deptId) {
                    fetch('get_login_roles.php?dept_id=' + encodeURIComponent(deptId))
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                // Show soft validation error
                                deptError.textContent = data.error;
                                deptError.classList.remove('hidden');
                                // Do not clear the field, let user edit
                            } else if (data.length > 0) {
                                roleSelect.innerHTML = '<option value="">Select Role</option>';
                                data.forEach(role => {
                                    const option = document.createElement('option');
                                    option.value = role.id;
                                    option.textContent = role.name;
                                    roleSelect.appendChild(option);
                                });
                                roleGroup.classList.remove('hidden');
                            } else {
                                // Valid format maybe, but no roles found? Treated as error.
                                deptError.textContent = "No roles found for this department.";
                                deptError.classList.remove('hidden');
                            }
                        })
                        .catch(err => {
                            console.error('Error fetching roles:', err);
                            deptError.textContent = "Error connecting to server.";
                            deptError.classList.remove('hidden');
                        });
                }
            });

            roleSelect.addEventListener('change', function() {
                if (this.value) {
                    userGroup.classList.remove('hidden');
                } else {
                    userGroup.classList.add('hidden');
                    passwordGroup.classList.add('hidden');
                }
            });

            userInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    passwordGroup.classList.remove('hidden');
                }
            });
        });
    </script>

</body>
</html>
