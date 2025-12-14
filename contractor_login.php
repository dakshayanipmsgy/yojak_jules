<?php
session_start();
require_once 'functions.php';

$error = '';
$success = '';

if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($mobile) || empty($password)) {
        $error = "All fields are required.";
    } else {
        $contractors = readJSON('global/data/contractors.json');
        if ($contractors === null) {
             // If file doesn't exist, nobody registered yet
             $error = "Invalid Credentials.";
        } else {
            $found = null;
            foreach ($contractors as $id => $c) {
                if (isset($c['mobile']) && $c['mobile'] === $mobile) {
                    $found = $c;
                    break;
                }
            }

            if ($found) {
                if (password_verify($password, $found['password'] ?? '')) {
                    // Success
                    $_SESSION['role'] = 'contractor';
                    $_SESSION['is_contractor'] = true;
                    $_SESSION['yojak_id'] = $found['yojak_id'];
                    $_SESSION['contractor_mobile'] = $found['mobile'];
                    $_SESSION['user_id'] = $found['yojak_id']; // For compatibility
                    $_SESSION['user_name'] = $found['profile']['company_name'] ?? 'Contractor';
                    // We don't set dept_id yet as they are global now.

                    header("Location: contractor_dashboard.php");
                    exit;
                } else {
                    $error = "Invalid Password.";
                }
            } else {
                $error = "Mobile number not found.";
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
            font-family: 'Open Sans', sans-serif;
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
            box-sizing: border-box;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 1rem;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #004494;
        }
        .error-message {
            background-color: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: left;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: left;
        }
        .register-link {
            display: block;
            margin-top: 1.5rem;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        .register-link a {
            color: #0056b3;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .back-link {
            display: block;
            margin-top: 1rem;
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
        <h1>Universal Login</h1>
        <p>Access your Contractor Dashboard</p>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="contractor_login.php">
            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input type="tel" id="mobile" name="mobile" placeholder="Your 10-digit Mobile" required autocomplete="off">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="register-link">
            New here? <a href="contractor_register.php">Create an Account</a>
        </div>

        <a href="index.php" class="back-link">Back to Staff Login</a>
    </div>

</body>
</html>
