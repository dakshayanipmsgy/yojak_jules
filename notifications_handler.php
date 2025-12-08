<?php
require_once 'auth_check.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['dept_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$notificationsPath = 'departments/' . $deptId . '/data/notifications.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($action === 'mark_read' && $id !== '') {
        $notifications = readJSON($notificationsPath) ?? [];
        $updated = false;

        foreach ($notifications as &$n) {
            // Check if notification belongs to user and matches ID (or timestamp if no ID)
            // We'll assume we use timestamp or unique ID.
            // Prompt suggested structure: { "user_id": "...", "message": "...", "link": "...", "read": false, "time": "..." }
            // To be safe, we should probably add a unique ID to notifications when creating them, but for now we might match by time + message or add an index.
            // Let's assume the frontend passes the index or we add IDs.
            // Plan: I'll use index for simplicity if the list is stable, but ID is better.
            // Let's generate a unique ID when creating.

            if (isset($n['id']) && $n['id'] === $id && $n['user_id'] === $userId) {
                $n['read'] = true;
                $updated = true;
                break;
            }
        }

        if ($updated) {
            writeJSON($notificationsPath, $notifications);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Notification not found or already read']);
        }
        exit;
    }
}

// Fetch (GET) is also useful for polling if needed, but navbar.php handles initial load.
// We can add a fetch action here if we want dynamic updates without page reload.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $notifications = readJSON($notificationsPath) ?? [];
    $myNotifications = [];
    foreach ($notifications as $n) {
        if ($n['user_id'] === $userId && empty($n['read'])) {
            $myNotifications[] = $n;
        }
    }
    // Sort by time desc
    usort($myNotifications, function($a, $b) {
        return strcmp($b['time'], $a['time']);
    });

    echo json_encode(['status' => 'success', 'data' => $myNotifications]);
    exit;
}
?>
