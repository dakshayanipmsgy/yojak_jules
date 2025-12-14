<?php
session_start();
require_once 'functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptId = trim($_POST['dept_id'] ?? '');
    $contractorId = trim($_POST['contractor_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($deptId) || empty($contractorId) || empty($password)) {
        $error = "All fields are required.";
    } else {
        // Path to contractors JSON
        $contractorsPath = 'departments/' . $deptId . '/data/contractors.json';

        // Check if department exists by trying to read the file
        // readJSON handles the full path via STORAGE_PATH, so we need to be careful.
        // functions.php readJSON prepends STORAGE_PATH.

        // First verify department exists loosely
        if (!is_dir(STORAGE_PATH . "/departments/$deptId")) {
             $error = "Invalid Department ID.";
        } else {
            $contractors = readJSON($contractorsPath);
            if ($contractors === null) {
                // If null, it might be that the file doesn't exist or permissions error
                 $error = "Department data unavailable.";
            } else {
                if (isset($contractors[$contractorId])) {
                    $contractor = $contractors[$contractorId];
                    // Verify password
                    if (password_verify($password, $contractor['password'] ?? '')) {
                        // Success
                        $_SESSION['role'] = 'contractor';
                        $_SESSION['user_id'] = $contractorId;
                        $_SESSION['user_name'] = $contractor['name'];
                        $_SESSION['dept_id'] = $deptId; // We need this to filter data later

                        header("Location: contractor_dashboard.php");
                        exit;
                    } else {
                        $error = "Invalid Password.";
                    }
                } else {
                    $error = "Contractor ID not found.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractor Login - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-card h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .login-card p {
            color: #666;
            margin-bottom: 2rem;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box; /* Ensure padding doesn't affect width */
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 1rem;
        }
        .error-message {
            background-color: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: left;
        }
        .back-link {
            display: block;
            margin-top: 1.5rem;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1>Contractor Portal</h1>
        <p>Login to view your Tenders and Work Orders</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="contractor_login.php">
            <div class="form-group">
                <label for="dept_id">Department ID</label>
                <input type="text" id="dept_id" name="dept_id" placeholder="e.g. test_dept" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="contractor_id">Contractor ID</label>
                <input type="text" id="contractor_id" name="contractor_id" placeholder="e.g. CON-2025-0001" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>

        <a href="index.php" class="back-link">Back to Staff Login</a>
    </div>

</body>
</html>
