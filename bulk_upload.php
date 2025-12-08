<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'] ?? null;
if (!$deptId || !isset($_SESSION['role_id']) || $_SESSION['role_id'] !== 'admin.' . $deptId) {
    die("Access Denied. Only Department Administrators can access this page.");
}

$message = '';
$error = '';
$importType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importType = $_POST['import_type'] ?? '';

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileSize = $_FILES['csv_file']['size'];
        $fileType = $_FILES['csv_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileExtension === 'csv') {
            $handle = fopen($fileTmpPath, 'r');
            if ($handle !== FALSE) {
                // Header row
                $header = fgetcsv($handle);

                $successCount = 0;
                $failCount = 0;
                $errors = [];

                if ($importType === 'users') {
                    // Users Import
                    // Expected Columns: First Name, Last Name, Role, Password, Custom_ID (Optional)
                    // We assume order or check header? Let's assume order as per prompt:
                    // [First Name, Last Name, Role, Password, Custom_ID (Optional)]

                    while (($data = fgetcsv($handle)) !== FALSE) {
                        // Skip empty rows
                        if (empty(implode('', $data))) continue;

                        $fname = trim($data[0] ?? '');
                        $lname = trim($data[1] ?? '');
                        $roleId = trim($data[2] ?? '');
                        $password = trim($data[3] ?? '');
                        $customId = trim($data[4] ?? '');

                        if (empty($fname) || empty($lname) || empty($roleId) || empty($password)) {
                            $failCount++;
                            $errors[] = "Row " . ($successCount + $failCount + 1) . ": Missing required fields.";
                            continue;
                        }

                        $fullName = $fname . ' ' . $lname;
                        $result = createUser($deptId, $fullName, $password, $roleId, $customId);

                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $failCount++;
                            $errors[] = "Row " . ($successCount + $failCount + 1) . ": " . $result['message'];
                        }
                    }
                    $message = "Users Import Complete. Success: $successCount, Failed: $failCount.";
                    if (!empty($errors)) {
                         $error = implode('<br>', $errors);
                    }

                } elseif ($importType === 'contractors') {
                    // Contractors Import
                    // Expected Columns: Name, Address, Pan, GST, Mobile

                    $contractorsPath = 'departments/' . $deptId . '/data/contractors.json';
                    $contractors = readJSON($contractorsPath) ?? [];

                    while (($data = fgetcsv($handle)) !== FALSE) {
                         // Skip empty rows
                        if (empty(implode('', $data))) continue;

                        $name = trim($data[0] ?? '');
                        $address = trim($data[1] ?? '');
                        $pan = trim($data[2] ?? '');
                        $gst = trim($data[3] ?? '');
                        $mobile = trim($data[4] ?? '');

                        if (empty($name) || empty($mobile)) {
                            $failCount++;
                            $errors[] = "Row " . ($successCount + $failCount + 1) . ": Name and Mobile are required.";
                            continue;
                        }

                        $id = uniqid('con_');
                        $contractors[$id] = [
                            'id' => $id,
                            'name' => $name,
                            'address' => $address,
                            'pan' => $pan,
                            'gst' => $gst,
                            'mobile' => $mobile
                        ];
                        $successCount++;
                    }

                    if (writeJSON($contractorsPath, $contractors)) {
                        $message = "Contractors Import Complete. Added: $successCount.";
                    } else {
                        $error = "Failed to save contractors file.";
                    }

                    if ($failCount > 0) {
                        $error .= "<br>Failed rows: " . implode('<br>', $errors);
                    }

                } else {
                    $error = "Invalid import type.";
                }

                fclose($handle);
            } else {
                $error = "Could not open file.";
            }
        } else {
            $error = "Invalid file extension. Please upload a .csv file.";
        }
    } else {
        $error = "No file uploaded or upload error.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .tab-nav { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 1.5rem; }
        .tab-nav button {
            background: none; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; color: #666; font-size: 1rem;
            border-bottom: 3px solid transparent; margin-bottom: -2px;
        }
        .tab-nav button.active { border-bottom-color: #0056b3; color: #0056b3; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .info-text { background: #f9f9f9; padding: 1rem; border-left: 4px solid #0056b3; margin-bottom: 1rem; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Bulk Import</h1>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; // Allow HTML for line breaks ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="openTab('users')">Import Users</button>
                <button class="tab-btn" onclick="openTab('contractors')">Import Contractors</button>
            </div>

            <!-- USERS IMPORT -->
            <div id="users" class="tab-content active">
                <div class="info-text">
                    <strong>CSV Format for Users:</strong><br>
                    Column 1: First Name (Required)<br>
                    Column 2: Last Name (Required)<br>
                    Column 3: Role ID (Required, e.g., <code>clerk.<?php echo htmlspecialchars($deptId); ?></code>)<br>
                    Column 4: Password (Required)<br>
                    Column 5: Custom User ID (Optional)<br>
                    <small><i>Note: The first row of the CSV is treated as headers and ignored.</i></small>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="import_type" value="users">
                    <div class="form-group">
                        <label for="csv_users">Select CSV File</label>
                        <input type="file" id="csv_users" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Import Users</button>
                    </div>
                </form>
            </div>

            <!-- CONTRACTORS IMPORT -->
            <div id="contractors" class="tab-content">
                <div class="info-text">
                    <strong>CSV Format for Contractors:</strong><br>
                    Column 1: Name (Required)<br>
                    Column 2: Address<br>
                    Column 3: PAN Number<br>
                    Column 4: GST Number<br>
                    Column 5: Mobile (Required)<br>
                    <small><i>Note: The first row of the CSV is treated as headers and ignored.</i></small>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="import_type" value="contractors">
                    <div class="form-group">
                        <label for="csv_contractors">Select CSV File</label>
                        <input type="file" id="csv_contractors" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Import Contractors</button>
                    </div>
                </form>
            </div>

            <div style="margin-top: 2rem; text-align: center;">
                <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            var i, tabContent, tabBtn;
            tabContent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabContent.length; i++) {
                tabContent[i].classList.remove("active");
            }
            tabBtn = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tabBtn.length; i++) {
                tabBtn[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            // Find the button that called this and add active class
            // But we don't have reference easily unless we pass 'this'.
            // Simple workaround loop:
            var buttons = document.getElementsByTagName("button");
            for (i=0; i<buttons.length; i++) {
                if(buttons[i].textContent.toLowerCase().includes(tabName)) {
                     buttons[i].classList.add("active");
                }
            }
        }
    </script>
</body>
</html>
