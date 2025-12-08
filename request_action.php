<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Verify Admin Access
if (!isset($_SESSION['dept_id']) || !isset($_SESSION['role_id'])) {
    header('Location: index.php');
    exit;
}

$deptId = $_SESSION['dept_id'];
$isAdmin = ($_SESSION['role_id'] === 'admin.' . $deptId);

if (!$isAdmin) {
    echo "Access Denied. Only Department Admins can access this page.";
    exit;
}

$message = '';
$error = '';

$deptUsers = getUsers($deptId);
$deptRoles = getRoles($deptId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetType = $_POST['target_type'] ?? ''; // 'user' or 'role'
    $targetId = $_POST['target_id'] ?? '';
    $actionType = $_POST['action_type'] ?? ''; // 'suspend' or 'archive'
    $reason = trim($_POST['reason'] ?? '');

    if (empty($targetType) || empty($targetId) || empty($actionType) || empty($reason)) {
        $error = "All fields are required.";
    } else {
        // Validate Target
        $targetName = '';
        if ($targetType === 'user') {
            if (!isset($deptUsers[$targetId])) {
                $error = "Invalid User ID.";
            } else {
                // Prevent Self-Sabotage: Dept Admin cannot request action on themselves
                // The Admin user has role 'admin.{deptId}'
                $adminRoleId = 'admin.' . $deptId;
                if ($deptUsers[$targetId]['role'] === $adminRoleId) {
                    $error = "You cannot request actions on the Department Administrator.";
                } else {
                    $targetName = $deptUsers[$targetId]['full_name'];
                }
            }
        } elseif ($targetType === 'role') {
            if (!isset($deptRoles[$targetId])) {
                $error = "Invalid Role ID.";
            } else {
                // Prevent requesting action on the Admin Role itself
                if ($targetId === 'admin.' . $deptId) {
                    $error = "You cannot request actions on the Administrator Role.";
                } else {
                    $targetName = $deptRoles[$targetId]['name'];
                }
            }
        } else {
            $error = "Invalid target type.";
        }

        if (empty($error)) {
            // Create Request Entry
            $requests = readJSON('system/requests.json');
            if ($requests === null) $requests = [];

            $requestId = generateID('REQ');
            $request = [
                'id' => $requestId,
                'dept_id' => $deptId,
                'requester_id' => $_SESSION['user_id'],
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'action_type' => $actionType,
                'reason' => $reason,
                'status' => 'pending', // pending, approved, rejected
                'created_at' => date('Y-m-d H:i:s')
            ];

            $requests[] = $request;

            if (writeJSON('system/requests.json', $requests)) {
                $message = "Request submitted successfully to Superadmin.";
            } else {
                $error = "Failed to save request.";
            }
        }
    }
}

// Get Previous Requests
$allRequests = readJSON('system/requests.json') ?? [];
$myRequests = [];
foreach ($allRequests as $req) {
    if ($req['dept_id'] === $deptId) {
        $myRequests[] = $req;
    }
}
// Sort by newest
usort($myRequests, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Action - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <main class="dashboard-content">
            <div class="section-header">
                <h2>Request Governance Action</h2>
                <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
            </div>

            <?php if ($message): ?>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Target Type</label>
                        <select name="target_type" id="target_type" onchange="updateTargets()" required>
                            <option value="">-- Select Type --</option>
                            <option value="user">User</option>
                            <option value="role">Role</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Select Target</label>
                        <select name="target_id" id="target_id" required>
                            <option value="">-- Select Target --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Action</label>
                        <select name="action_type" required>
                            <option value="">-- Select Action --</option>
                            <option value="suspend">Suspend (Prevent Login/Use)</option>
                            <option value="archive">Archive (Hide)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" rows="3" required placeholder="Why is this action needed?"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Request History</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Target</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myRequests)): ?>
                                <tr><td colspan="5">No requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myRequests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($req['target_name']); ?> (<?php echo htmlspecialchars($req['target_type']); ?>)</td>
                                        <td><?php echo htmlspecialchars(ucfirst($req['action_type'])); ?></td>
                                        <td>
                                            <?php
                                            $status = $req['status'];
                                            $color = ($status === 'approved') ? 'green' : (($status === 'rejected') ? 'red' : 'orange');
                                            echo "<span style='color:$color; font-weight:bold;'>" . ucfirst($status) . "</span>";
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
        var users = <?php echo json_encode($deptUsers); ?>;
        var roles = <?php echo json_encode($deptRoles); ?>;

        function updateTargets() {
            var type = document.getElementById('target_type').value;
            var select = document.getElementById('target_id');
            select.innerHTML = '<option value="">-- Select Target --</option>';

            if (type === 'user') {
                for (var id in users) {
                    var option = document.createElement('option');
                    option.value = id;
                    option.textContent = users[id].full_name + ' (' + id + ')';
                    select.appendChild(option);
                }
            } else if (type === 'role') {
                for (var id in roles) {
                    var option = document.createElement('option');
                    option.value = id;
                    option.textContent = roles[id].name + ' (' + id + ')';
                    select.appendChild(option);
                }
            }
        }
    </script>
</body>
</html>
