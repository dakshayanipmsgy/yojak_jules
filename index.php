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

        <form id="loginForm" method="POST" action="login.php">
            <!-- Box 1: Department ID -->
            <div class="form-group">
                <label for="dept_id_search">Department ID</label>
                <!-- Anti-Autofill Hack: readonly initially, removed on focus -->
                <input type="text" id="dept_id_search" name="dept_id"
                       placeholder="Enter Department ID (e.g. dws)"
                       required autocomplete="off" readonly onfocus="this.removeAttribute('readonly');">
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
                <label for="user_id">User ID (Prefix Only)</label>
                <input type="text" id="user_id" name="user_id" placeholder="e.g., For 'anish.admin.dws', just type 'anish'" required>
            </div>

            <!-- Box 4: Password (Initially Hidden) -->
            <div class="form-group hidden" id="password_group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter Password" required>
                <button type="submit" style="margin-top: 15px;">Login</button>
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
            const deptInput = document.getElementById('dept_id_search');
            const roleSelect = document.getElementById('role_id');
            const roleGroup = document.getElementById('role_group');
            const userGroup = document.getElementById('user_group');
            const passwordGroup = document.getElementById('password_group');
            const superadminNotice = document.getElementById('superadmin_notice');

            function resetFields() {
                roleGroup.classList.add('hidden');
                userGroup.classList.add('hidden');
                passwordGroup.classList.add('hidden');
                roleSelect.innerHTML = '<option value="">Select Role</option>';
                superadminNotice.classList.add('hidden');
            }

            deptInput.addEventListener('blur', function() {
                const deptId = this.value.trim();

                // Reset subsequent fields when dept changes
                resetFields();

                if (deptId === '') {
                     // Show User ID and Password for potential Superadmin login
                     // (Though mostly we expect deptId 'superadmin' which returns a role)
                     return;
                }

                if (deptId) {
                    fetch('get_login_roles.php?dept_id=' + encodeURIComponent(deptId))
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                // If error (e.g. dept not found), show message
                                alert(data.error);
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
                                alert("No roles found for this department.");
                            }
                        })
                        .catch(err => {
                            console.error('Error fetching roles:', err);
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

            const userInput = document.getElementById('user_id');
            userInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    passwordGroup.classList.remove('hidden');
                }
            });
        });
    </script>

</body>
</html>
