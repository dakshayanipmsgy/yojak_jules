<?php
session_start();
require_once 'functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');

    if (empty($mobile) || empty($password) || empty($company_name) || empty($owner_name)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Load Global Contractors
        // readJSON path is relative to STORAGE_PATH
        $contractors = readJSON('global/data/contractors.json') ?? [];

        // Check if mobile exists
        $mobileExists = false;
        foreach ($contractors as $c) {
            if (isset($c['mobile']) && $c['mobile'] === $mobile) {
                $mobileExists = true;
                break;
            }
        }

        if ($mobileExists) {
            $error = "A contractor with this mobile number already exists.";
        } else {
            // Generate ID
            // Simple counter based on count
            $count = count($contractors) + 1;
            $yojakId = 'YJK-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            // Hash Password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Create Record
            $newContractor = [
                'yojak_id' => $yojakId,
                'mobile' => $mobile,
                'password' => $hashedPassword,
                'tier' => 'Free',
                'profile' => [
                    'company_name' => $company_name,
                    'owner_name' => $owner_name,
                    'email' => '',
                    'gst_no' => '',
                    'pan_no' => '',
                    'address' => '',
                    'bank_details' => ['ac_no' => '', 'ifsc' => '']
                ],
                'linked_departments' => [],
                'created_at' => date('Y-m-d')
            ];

            // Save
            $contractors[$yojakId] = $newContractor;
            if (writeJSON('global/data/contractors.json', $contractors)) {
                $_SESSION['register_success'] = "Registration successful! Your ID is $yojakId. Please login.";
                header("Location: contractor_login.php");
                exit;
            } else {
                $error = "System Error: Could not save registration.";
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
    <title>Contractor Registration - Yojak</title>
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
        .register-card {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .register-card h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .register-card p {
            color: #666;
            margin-bottom: 2rem;
            text-align: center;
        }
        .form-group {
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
            text-align: center;
        }
        .login-link {
            display: block;
            margin-top: 1.5rem;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
        }
        .login-link a {
            color: #0056b3;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <h1>Register as Contractor</h1>
        <p>Join the Universal Contractor Platform</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="contractor_register.php">
            <div class="form-group">
                <label for="company_name">Company Name</label>
                <input type="text" id="company_name" name="company_name" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="owner_name">Owner Name</label>
                <input type="text" id="owner_name" name="owner_name" required value="<?php echo htmlspecialchars($_POST['owner_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number (Login ID)</label>
                <input type="tel" id="mobile" name="mobile" required pattern="[0-9]{10}" title="10 digit mobile number" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-primary">Register</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="contractor_login.php">Login here</a>
        </div>
    </div>

</body>
</html>
