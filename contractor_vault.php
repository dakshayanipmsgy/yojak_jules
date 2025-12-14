<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor' || !isset($_SESSION['yojak_id'])) {
    header("Location: contractor_login.php");
    exit;
}

$yojakId = $_SESSION['yojak_id'];
$uploadDir = STORAGE_PATH . '/global/uploads/' . $yojakId . '/';

// Ensure directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Load Contractor Data
$contractors = readJSON('global/data/contractors.json') ?? [];
$contractor = $contractors[$yojakId] ?? null;

if (!$contractor) {
    die("Contractor data not found.");
}

// Ensure vault array exists
if (!isset($contractor['vault'])) {
    $contractor['vault'] = [];
}

$categories = [
    'Identity' => ['PAN Card', 'Aadhaar Card', 'Voter ID'],
    'Financial' => ['GST Certificate', 'ITR (Last 3 Years)', 'Bank Statement'],
    'Technical' => ['Experience Certificates', 'Work Order Copies', 'License'],
    'Other' => ['Other Documents']
];

$successMsg = '';
$errorMsg = '';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Other';
    $desc = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $errorMsg = "Please provide a document title.";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = "Please select a valid file.";
    } else {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validation
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($fileExt, $allowedExts)) {
            $errorMsg = "Only PDF and Image (JPG, PNG) files are allowed.";
        } elseif ($_FILES['file']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errorMsg = "File size must be less than 5MB.";
        } else {
            // Save File
            $newFileName = time() . '_' . createSlug($title) . '.' . $fileExt;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $destPath)) {
                // Update JSON
                $contractor['vault'][] = [
                    'id' => uniqid('doc_'),
                    'title' => $title,
                    'category' => $category,
                    'description' => $desc,
                    'filename' => $newFileName,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                $contractors[$yojakId] = $contractor;
                if (writeJSON('global/data/contractors.json', $contractors)) {
                    $successMsg = "Document uploaded successfully.";
                } else {
                    $errorMsg = "File saved but failed to update record.";
                }
            } else {
                $errorMsg = "Failed to move uploaded file.";
            }
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $docId = $_POST['doc_id'] ?? '';
    $foundIndex = -1;
    $fileToDelete = '';

    foreach ($contractor['vault'] as $index => $doc) {
        if ($doc['id'] === $docId) {
            $foundIndex = $index;
            $fileToDelete = $doc['filename'];
            break;
        }
    }

    if ($foundIndex !== -1) {
        // Remove file
        $filePath = $uploadDir . $fileToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Update JSON
        array_splice($contractor['vault'], $foundIndex, 1);
        $contractors[$yojakId] = $contractor;
        if (writeJSON('global/data/contractors.json', $contractors)) {
            $successMsg = "Document deleted successfully.";
        } else {
            $errorMsg = "Failed to update record.";
        }
    } else {
        $errorMsg = "Document not found.";
    }
}

// Calculate Completion Score (Based on uploaded categories)
$uploadedCategories = [];
foreach ($contractor['vault'] as $doc) {
    $uploadedCategories[$doc['category']] = true;
}
// Give points for categories present.
// Identity: 30%, Financial: 40%, Technical: 30%
$score = 0;
if (isset($uploadedCategories['Identity'])) $score += 30;
if (isset($uploadedCategories['Financial'])) $score += 40;
if (isset($uploadedCategories['Technical'])) $score += 30;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Vault - Yojak</title>
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
        .logo-area h2 {
            margin: 0;
            color: var(--primary-color);
        }
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
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-danger { background-color: #dc3545; color: white; }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .doc-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            background: #fff;
            position: relative;
        }
        .doc-icon {
            font-size: 32px;
            color: #666;
            margin-bottom: 10px;
            display: block;
            text-align: center;
        }
        .doc-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .doc-meta {
            font-size: 12px;
            color: #888;
            margin-bottom: 15px;
        }
        .doc-actions {
            display: flex;
            gap: 10px;
        }
        .score-box {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .score-val { font-size: 24px; font-weight: bold; }
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
            <li><a href="contractor_profile.php">My Profile</a></li>
            <li><a href="#">Linked Depts</a></li>
            <li><a href="contractor_vault.php" class="active">Documents</a></li>
            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="score-box">
            <span class="score-val"><?php echo $score; ?>%</span>
            <div>Vault Completion Score</div>
        </div>

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

        <div class="card">
            <h3>Upload New Document</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <label>Document Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. GST Certificate 2024">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <?php foreach ($categories as $cat => $examples): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?> (e.g. <?php echo implode(', ', $examples); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>File (PDF, JPG, PNG)</label>
                    <input type="file" name="file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <button type="submit" class="btn btn-primary">Upload to Vault</button>
            </form>
        </div>

        <div class="card">
            <h3>My Vault Gallery</h3>
            <?php if (empty($contractor['vault'])): ?>
                <p style="color: #777; text-align: center; padding: 20px;">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="doc-grid">
                    <?php foreach ($contractor['vault'] as $doc): ?>
                        <div class="doc-card">
                            <div class="doc-icon">📄</div>
                            <span class="doc-title" title="<?php echo htmlspecialchars($doc['title']); ?>">
                                <?php echo htmlspecialchars($doc['title']); ?>
                            </span>
                            <div class="doc-meta">
                                <?php echo htmlspecialchars($doc['category']); ?><br>
                                <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>
                            </div>
                            <div class="doc-actions">
                                <a href="view_attachment.php?path=global/uploads/<?php echo $yojakId . '/' . $doc['filename']; ?>" target="_blank" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px; text-decoration: none;">View</a>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this document?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
