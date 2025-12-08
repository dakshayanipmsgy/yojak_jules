<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$roleId = $_SESSION['role_id'];
$isDeptAdmin = ($roleId === 'admin.' . $deptId);

if (!$isDeptAdmin) {
    die("Access Denied. Only Department Administrators can manage contractors.");
}

$contractorsPath = 'departments/' . $deptId . '/data/contractors.json';
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $contractors = readJSON($contractorsPath) ?? [];

    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? uniqid('con_');
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $pan = trim($_POST['pan']);
        $gst = trim($_POST['gst']);
        $mobile = trim($_POST['mobile']);

        if (empty($name) || empty($mobile)) {
            $error = "Name and Mobile are required.";
        } else {
            $contractors[$id] = [
                'id' => $id,
                'name' => $name,
                'address' => $address,
                'pan' => $pan,
                'gst' => $gst,
                'mobile' => $mobile
            ];

            if (writeJSON($contractorsPath, $contractors)) {
                $message = "Contractor saved successfully.";
            } else {
                $error = "Failed to save contractor.";
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (isset($contractors[$id])) {
            unset($contractors[$id]);
            if (writeJSON($contractorsPath, $contractors)) {
                $message = "Contractor deleted.";
            } else {
                $error = "Failed to delete contractor.";
            }
        }
    }
}

$contractors = readJSON($contractorsPath) ?? [];

// Edit Mode
$editContractor = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    if (isset($contractors[$editId])) {
        $editContractor = $contractors[$editId];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contractors - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .form-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .contractor-list { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="header-left">
            <h1>Manage Contractors</h1>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2><?php echo $editContractor ? 'Edit Contractor' : 'Add New Contractor'; ?></h2>
            <form method="POST" action="manage_contractors.php">
                <input type="hidden" name="action" value="<?php echo $editContractor ? 'update' : 'create'; ?>">
                <?php if ($editContractor): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editContractor['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Contractor Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editContractor['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($editContractor['address'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>PAN Number</label>
                    <input type="text" name="pan" value="<?php echo htmlspecialchars($editContractor['pan'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" name="gst" value="<?php echo htmlspecialchars($editContractor['gst'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mobile</label>
                    <input type="text" name="mobile" value="<?php echo htmlspecialchars($editContractor['mobile'] ?? ''); ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editContractor ? 'Update' : 'Save'; ?></button>
                    <?php if ($editContractor): ?>
                        <a href="manage_contractors.php" class="btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="contractor-list">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>GST / PAN</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contractors)): ?>
                        <tr><td colspan="5" class="text-center">No contractors found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contractors as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo htmlspecialchars($c['mobile']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($c['gst'] ?: '-'); ?> /
                                    <?php echo htmlspecialchars($c['pan'] ?: '-'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['address']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $c['id']; ?>" class="btn-small">Edit</a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn-small btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
