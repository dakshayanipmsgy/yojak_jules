<?php
// Ensure this is included only after session start, which auth_check.php handles or dashboard does.
// However, to be safe, we check if session is started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine Role and Department
$isSuperadmin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] === 'superadmin');
$deptId = $_SESSION['dept_id'] ?? null;
$userId = $_SESSION['user_id'] ?? 'Guest';
$roleId = $_SESSION['role_id'] ?? '';

$isAdmin = false;
if (!$isSuperadmin && $deptId) {
    $isAdmin = ($roleId === 'admin.' . $deptId);
}
?>
<nav class="navbar">
    <div class="navbar-left">
        <span class="app-name">Yojak</span>
        <?php if ($deptId): ?>
            <span class="dept-name"> | <?php echo htmlspecialchars($deptId); ?></span>
        <?php elseif ($isSuperadmin): ?>
            <span class="dept-name"> | Superadmin</span>
        <?php endif; ?>
    </div>

    <div class="navbar-center">
        <?php if ($isSuperadmin): ?>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <!-- Superadmin specific links could go here if any -->
        <?php else: ?>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>

            <?php if ($isAdmin): ?>
                <a href="manage_contractors.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_contractors.php' ? 'active' : ''; ?>">Contractors</a>
                <a href="manage_templates.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_templates.php' ? 'active' : ''; ?>">Templates</a>
            <?php else: ?>
                <!-- Regular User Links -->
                <!-- Ideally these link to dashboard with filters or specific pages if they exist.
                     For now pointing to dashboard sections or create_document -->
                <a href="create_document.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create_document.php' ? 'active' : ''; ?>">Create New Document</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="navbar-right">
        <span class="user-greeting">Welcome, <?php echo htmlspecialchars($userId); ?></span>
        <a href="profile.php" class="profile-link">My Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<style>
    /* Basic Navbar Styling - Inline for simplicity or moved to style.css */
    .navbar {
        background-color: #333;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        margin-bottom: 20px;
    }
    .navbar a {
        color: white;
        text-decoration: none;
        margin: 0 10px;
        padding: 5px 10px;
        border-radius: 4px;
    }
    .navbar a:hover, .navbar a.active {
        background-color: #555;
    }
    .navbar-left .app-name {
        font-weight: bold;
        font-size: 1.2em;
    }
    .navbar-right {
        display: flex;
        align-items: center;
    }
    .logout-btn {
        background-color: #d9534f;
    }
    .logout-btn:hover {
        background-color: #c9302c;
    }
    .profile-link {
        margin-right: 15px;
    }
    .user-greeting {
        margin-right: 15px;
    }
</style>
