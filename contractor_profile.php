<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor' || !isset($_SESSION['yojak_id'])) {
    header("Location: contractor_login.php");
    exit;
}

$yojakId = $_SESSION['yojak_id'];
$successMsg = '';
$errorMsg = '';

// Load Data
$contractors = readJSON('global/data/contractors.json') ?? [];
$contractor = $contractors[$yojakId] ?? null;

if (!$contractor) {
    session_destroy();
    header("Location: contractor_login.php");
    exit;
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gst_no = trim($_POST['gst_no'] ?? '');
    $pan_no = trim($_POST['pan_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $ac_no = trim($_POST['ac_no'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');

    // Validation
    // Simple regex
    $gstRegex = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    $panRegex = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

    if (empty($company_name) || empty($owner_name)) {
        $errorMsg = "Company Name and Owner Name are required.";
    } elseif (!empty($gst_no) && !preg_match($gstRegex, $gst_no)) {
        $errorMsg = "Invalid GST Number format.";
    } elseif (!empty($pan_no) && !preg_match($panRegex, $pan_no)) {
        $errorMsg = "Invalid PAN Number format.";
    } else {
        // Update Data
        $contractor['profile']['company_name'] = $company_name;
        $contractor['profile']['owner_name'] = $owner_name;
        $contractor['profile']['email'] = $email;
        $contractor['profile']['gst_no'] = $gst_no;
        $contractor['profile']['pan_no'] = $pan_no;
        $contractor['profile']['address'] = $address;
        $contractor['profile']['bank_details'] = [
            'ac_no' => $ac_no,
            'ifsc' => $ifsc
        ];

        $contractors[$yojakId] = $contractor;
        if (writeJSON('global/data/contractors.json', $contractors)) {
            $successMsg = "Profile updated successfully.";
            // Update session name if changed
            $_SESSION['user_name'] = $company_name;
        } else {
            $errorMsg = "System Error: Could not save profile.";
        }
    }
}

$profile = $contractor['profile'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        body {
            background-color: #f4f6f8;
            margin: 0;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: #fff;
            border-right: 1px solid #e0e0e0;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
        }
        .logo-area {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
        }
        .logo-area h2 { margin: 0; color: var(--primary-color); }
        .nav-links {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }
        .nav-links li a {
            display: block;
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s;
        }
        .nav-links li a:hover, .nav-links li a.active {
            background-color: #e3f2fd;
            color: var(--primary-color);
            border-right: 3px solid var(--primary-color);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            max-width: 800px;
        }
        .card h2 { margin-top: 0; margin-bottom: 20px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 15px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }

        .full-width { grid-column: 1 / -1; }

        .btn { padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 500; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-secondary { background-color: #6c757d; color: white; text-decoration: none; display: inline-block; }

    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-area">
            <h2>Yojak</h2>
            <div style="font-size: 12px; color: #777;">Contractor Platform</div>
        </div>
        <ul class="nav-links">
            <li><a href="contractor_dashboard.php">Home</a></li>
            <li><a href="contractor_profile.php" class="active">My Profile</a></li>
            <li><a href="#">Linked Depts</a></li>
            <li><a href="#">Documents</a></li>
            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">

        <div class="card">
            <h2>Company Profile</h2>

            <?php if ($successMsg): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($successMsg); ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($profile['owner_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>GST Number</label>
                        <input type="text" name="gst_no" class="form-control" value="<?php echo htmlspecialchars($profile['gst_no'] ?? ''); ?>" placeholder="22AAAAA0000A1Z5">
                    </div>
                    <div class="form-group">
                        <label>PAN Number</label>
                        <input type="text" name="pan_no" class="form-control" value="<?php echo htmlspecialchars($profile['pan_no'] ?? ''); ?>" placeholder="ABCDE1234F">
                    </div>

                    <div class="form-group full-width" style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 10px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #555;">Bank Details</h3>
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="ac_no" class="form-control" value="<?php echo htmlspecialchars($profile['bank_details']['ac_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="ifsc" class="form-control" value="<?php echo htmlspecialchars($profile['bank_details']['ifsc'] ?? ''); ?>">
                    </div>

                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <a href="contractor_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>

    </div>

</body>
</html>
