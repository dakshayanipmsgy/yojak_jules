<?php
session_start();
require_once 'auth_check.php';
require_once 'functions.php';

enforceFeature('file_system');

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$userName = $userId; // Ideally we should fetch full name, but ID is fine for now

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("File ID not provided.");
}

$fileId = $_GET['id'];
// Security check: ensure fileId only contains alphanumeric and underscores to prevent directory traversal
if (!preg_match('/^[A-Za-z0-9_]+$/', $fileId)) {
    die("Invalid File ID.");
}

$fileDir = "storage/departments/$deptId/files/$fileId/";
$metaPath = $fileDir . 'meta.json';
$notesPath = $fileDir . 'notes.json';
$documentsDir = $fileDir . 'documents/';

if (!file_exists($metaPath)) {
    die("File not found.");
}

// Read Meta
$meta = json_decode(file_get_contents($metaPath), true);

// Handle Note Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $noteContent = trim($_POST['note_content']);
    if (!empty($noteContent)) {
        $currentNotes = [];
        if (file_exists($notesPath)) {
            $currentNotes = json_decode(file_get_contents($notesPath), true);
            if (!is_array($currentNotes)) $currentNotes = [];
        }

        $newNote = [
            'content' => $noteContent,
            'author' => $userId, // using user ID as author
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $currentNotes[] = $newNote;
        file_put_contents($notesPath, json_encode($currentNotes, JSON_PRETTY_PRINT));

        // Refresh to see new note
        header("Location: view_file.php?id=" . $fileId);
        exit;
    }
}

// Read Notes
$notes = [];
if (file_exists($notesPath)) {
    $notes = json_decode(file_get_contents($notesPath), true);
    if (!is_array($notes)) $notes = [];
}

// List Documents
// In "Case File" model, documents are inside the documents/ folder.
// Assuming documents are JSON files similar to how they were stored before.
$documents = [];
$scan = glob($documentsDir . '*.json');
if ($scan) {
    foreach ($scan as $path) {
        $doc = json_decode(file_get_contents($path), true);
        $doc['filename'] = basename($path);
        // If it's a legacy doc structure, it might have 'id' inside.
        // We will assume filename is the ID for now or use internal ID.
        $documents[] = $doc;
    }
}

// Sort documents by creation date or ID.
// Assuming doc ID contains date or we check 'created_at' inside doc.
usort($documents, function($a, $b) {
    $t1 = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    $t2 = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    return $t1 - $t2; // Chronological order
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View File - <?php echo htmlspecialchars($meta['file_number']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .split-container {
            display: flex;
            height: calc(100vh - 80px); /* Adjust based on navbar height */
            border-top: 1px solid #ccc;
        }
        .left-panel {
            width: 60%;
            border-right: 1px solid #ccc;
            padding: 20px;
            overflow-y: auto;
            background-color: #f9f9f9;
        }
        .right-panel {
            width: 40%;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        /* File Header */
        .file-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }
        .file-number {
            font-size: 1.2rem;
            color: #555;
            font-weight: bold;
        }
        .file-subject {
            font-size: 1.5rem;
            margin: 5px 0;
            color: #333;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 20px;
            border-left: 2px solid #ccc;
        }
        .timeline-item {
            margin-bottom: 20px;
            position: relative;
            padding-left: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px; /* Adjust based on border and padding */
            top: 5px;
            width: 10px;
            height: 10px;
            background-color: #007bff;
            border-radius: 50%;
        }
        .doc-card {
            background: white;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .doc-meta {
            font-size: 0.85rem;
            color: #777;
            margin-bottom: 5px;
        }
        .doc-title {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .view-doc-btn {
            display: inline-block;
            margin-top: 5px;
            font-size: 0.9rem;
            color: #007bff;
            text-decoration: none;
        }

        /* Green Sheet */
        .green-sheet-title {
            color: green;
            border-bottom: 2px solid green;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .notes-list {
            flex-grow: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #eee; /* Visual boundary */
            padding: 10px;
            background-color: #fafafa;
        }
        .note-item {
            background-color: #e8f5e9; /* Light green */
            border: 1px solid #c8e6c9;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .note-meta {
            font-size: 0.8rem;
            color: #555;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .note-content {
            white-space: pre-wrap; /* Preserve line breaks */
        }

        .add-note-form textarea {
            width: 100%;
            height: 80px;
            padding: 10px;
            margin-bottom: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .add-note-btn {
            background-color: green;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .add-doc-wrapper {
            margin-bottom: 20px;
        }
        .add-doc-btn {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="split-container">
    <div class="left-panel">
        <div class="file-header">
            <div class="file-number"><?php echo htmlspecialchars($meta['file_number']); ?></div>
            <div class="file-subject"><?php echo htmlspecialchars($meta['subject']); ?></div>
            <div style="font-size: 0.9rem; color: #666;">
                Status: <?php echo ucfirst($meta['status']); ?> | Created by: <?php echo htmlspecialchars($meta['created_by']); ?>
            </div>
        </div>

        <div class="add-doc-wrapper">
            <a href="add_document.php?file_id=<?php echo $fileId; ?>" class="add-doc-btn">+ Add New Document</a>
        </div>

        <h3>Documents Timeline</h3>
        <div class="timeline">
            <?php if (empty($documents)): ?>
                <p style="color: #777; font-style: italic;">No documents in this file yet.</p>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="timeline-item">
                        <div class="doc-card">
                            <div class="doc-meta">
                                <?php echo htmlspecialchars($doc['created_at'] ?? 'Unknown Date'); ?> |
                                <?php echo htmlspecialchars($doc['type'] ?? 'Document'); ?>
                            </div>
                            <div class="doc-title">
                                <?php echo htmlspecialchars($doc['title'] ?? 'Untitled'); ?>
                            </div>
                            <?php if (($doc['status'] ?? 'draft') === 'draft'): ?>
                                <a href="add_document.php?file_id=<?php echo $fileId; ?>&doc_id=<?php echo basename($doc['filename'], '.json'); ?>" class="view-doc-btn">Edit Draft</a>
                            <?php else: ?>
                                <a href="add_document.php?file_id=<?php echo $fileId; ?>&doc_id=<?php echo basename($doc['filename'], '.json'); ?>" class="view-doc-btn">View Document</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="right-panel">
        <h3 class="green-sheet-title">Departmental Notes (Green Sheet)</h3>

        <div class="notes-list">
            <?php if (empty($notes)): ?>
                <p style="text-align: center; color: #888;">No notes added yet.</p>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note-item">
                        <div class="note-meta">
                            #<?php echo array_search($note, $notes) + 1; ?> |
                            <?php echo htmlspecialchars($note['author']); ?> |
                            <?php echo htmlspecialchars($note['timestamp']); ?>
                        </div>
                        <div class="note-content"><?php echo nl2br(htmlspecialchars($note['content'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form class="add-note-form" method="POST">
            <input type="hidden" name="action" value="add_note">
            <textarea name="note_content" placeholder="Write a note..." required></textarea>
            <button type="submit" class="add-note-btn">Add Note</button>
        </form>
    </div>
</div>

</body>
</html>
