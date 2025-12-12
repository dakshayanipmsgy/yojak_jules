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
            <?php if (checkFeature('file_system')): ?>
                <a href="file_manager.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'file_manager.php' ? 'active' : ''; ?>">File Manager</a>
            <?php endif; ?>
            <?php if (checkFeature('dak_register')): ?>
                <a href="dak_register.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dak_register.php' ? 'active' : ''; ?>">Dak Register</a>
            <?php endif; ?>

            <a href="tenders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tenders.php' ? 'active' : ''; ?>">Tenders</a>
            <?php if ($isAdmin): ?>
                <a href="manage_contractors.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_contractors.php' ? 'active' : ''; ?>">Contractors</a>
                <a href="manage_templates.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_templates.php' ? 'active' : ''; ?>">Templates</a>
                <a href="master_register.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'master_register.php' ? 'active' : ''; ?>">Master Register</a>
            <?php else: ?>
                <!-- Regular User Links -->
                <!-- Ideally these link to dashboard with filters or specific pages if they exist.
                     For now pointing to dashboard sections or create_document -->
                <a href="create_document.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create_document.php' ? 'active' : ''; ?>">Create New Document</a>
            <?php endif; ?>

            <!-- Search Bar for Department Users -->
            <form action="search.php" method="GET" class="search-form" style="display: inline-block; margin-left: 15px;">
                <input type="text" name="q" placeholder="Search ID or Title..." required style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;">
                <button type="submit" style="padding: 5px 10px; border-radius: 4px; border: none; background-color: #5bc0de; color: white; cursor: pointer;">Search</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="navbar-right">
        <!-- Notification Bell -->
        <?php
        $unreadCount = 0;
        $myNotifications = [];
        if ($deptId && $userId) {
            $notifPath = 'departments/' . $deptId . '/data/notifications.json';
            $allNotifs = readJSON($notifPath) ?? [];
            foreach ($allNotifs as $n) {
                if (isset($n['user_id']) && $n['user_id'] === $userId && empty($n['read'])) {
                    $myNotifications[] = $n;
                    $unreadCount++;
                }
            }
            // Sort by time desc
            usort($myNotifications, function($a, $b) {
                return strcmp($b['time'], $a['time']);
            });
        }
        ?>
        <div class="notification-wrapper" onclick="toggleNotifications()">
            <span class="bell-icon">🔔</span>
            <?php if ($unreadCount > 0): ?>
                <span class="badge"><?php echo $unreadCount; ?></span>
            <?php endif; ?>

            <div id="notification-dropdown" class="notification-dropdown">
                <?php if (empty($myNotifications)): ?>
                    <div class="notif-item">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($myNotifications as $notif): ?>
                        <div class="notif-item" onclick="markRead('<?php echo $notif['id'] ?? ''; ?>', '<?php echo $notif['link'] ?? '#'; ?>', event)">
                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                            <span class="notif-time"><?php echo htmlspecialchars($notif['time']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <span class="user-greeting">Welcome, <?php echo htmlspecialchars($userId); ?></span>
        <a href="profile.php" class="profile-link">My Profile</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<script>
function toggleNotifications() {
    var dropdown = document.getElementById('notification-dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function markRead(id, link, event) {
    event.stopPropagation(); // Prevent closing dropdown immediately if we want to keep it open, but we usually want to navigate.

    // Call API to mark as read
    if (id) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "notifications_handler.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (this.readyState === 4 && this.status === 200) {
                if (link && link !== '#') {
                    window.location.href = link;
                } else {
                    location.reload(); // Reload to update badge
                }
            }
        };
        xhr.send("action=mark_read&id=" + id);
    } else if (link && link !== '#') {
        window.location.href = link;
    }
}

// Close dropdown when clicking outside
window.onclick = function(event) {
    if (!event.target.matches('.bell-icon') && !event.target.matches('.badge') && !event.target.closest('.notification-wrapper')) {
        var dropdown = document.getElementById('notification-dropdown');
        if (dropdown && dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        }
    }
}
</script>

<style>
    /* Basic Navbar Styling */
    .navbar {
        background-color: #333;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        margin-bottom: 20px;
        position: relative;
        z-index: 1000;
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

    /* Notification Styles */
    .notification-wrapper {
        position: relative;
        margin-right: 20px;
        cursor: pointer;
    }
    .bell-icon {
        font-size: 1.2rem;
    }
    .badge {
        position: absolute;
        top: -5px;
        right: -10px;
        background-color: red;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 0.7rem;
    }
    .notification-dropdown {
        display: none;
        position: absolute;
        top: 30px;
        right: 0;
        background-color: white;
        color: black;
        width: 300px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 4px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1001;
        border: 1px solid #ddd;
    }
    .notif-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    .notif-item:hover {
        background-color: #f5f5f5;
    }
    .notif-item p {
        margin: 0;
        font-size: 0.9rem;
    }
    .notif-time {
        font-size: 0.75rem;
        color: #888;
    }
</style>
