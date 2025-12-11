<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$userId = $_SESSION['user_id'];
$fileId = $_GET['file_id'] ?? '';

if (!$fileId || !preg_match('/^[A-Za-z0-9_]+$/', $fileId)) {
    die("Invalid File ID.");
}

$fileDir = "storage/departments/$deptId/files/$fileId/";
if (!file_exists($fileDir . 'meta.json')) {
    die("File not found.");
}
$fileMeta = json_decode(file_get_contents($fileDir . 'meta.json'), true);

$message = '';
$error = '';
$generatedHtml = '';
$editDocId = $_GET['doc_id'] ?? '';

if ($editDocId && !preg_match('/^[A-Za-z0-9_]+$/', $editDocId)) {
    die("Invalid Document ID.");
}

$documentTitle = '';

// Load Data
$contractors = readJSON("departments/$deptId/data/contractors.json") ?? [];
$localTemplates = readJSON("departments/$deptId/templates/templates.json") ?? [];
$systemTemplates = readJSON("system/templates/templates.json") ?? [];
$deptUsers = getUsers($deptId);

// Prepare Templates
$templates = [];
$templates['blank'] = [
    'id' => 'blank',
    'title' => 'Create Blank Document',
    'display_title' => 'Create Blank Document',
    'is_global' => false,
    'filename' => ''
];
foreach ($systemTemplates as $t) {
    $t['is_global'] = true;
    $t['display_title'] = $t['title'] . ' [Global]';
    $templates[$t['id']] = $t;
}
foreach ($localTemplates as $t) {
    $t['is_global'] = false;
    $t['display_title'] = $t['title'];
    $templates[$t['id']] = $t;
}

// Load Existing Document if Edit
$isReadOnly = false;
if ($editDocId) {
    $docPath = $fileDir . "documents/$editDocId.json";
    if (file_exists($docPath)) {
        $docData = json_decode(file_get_contents($docPath), true);
        $generatedHtml = $docData['content'];
        $documentTitle = $docData['title'];
        if ($docData['status'] !== 'draft') {
            $isReadOnly = true;
        }
    }
}

// Handle Template Generation / AI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $templateId = $_POST['template_id'] ?? '';
    $customContent = $_POST['custom_content'] ?? '';
    $recipientMode = $_POST['recipient_mode'] ?? 'contractor';

    // Helper to resolve recipient name/address
    $recipientName = '';
    $recipientAddress = '';

    // ... (Recipient Logic similar to create_document.php) ...
    if ($recipientMode === 'contractor') {
        $cid = $_POST['contractor_id'] ?? '';
        if (isset($contractors[$cid])) {
            $recipientName = $contractors[$cid]['name'];
            $recipientAddress = $contractors[$cid]['address'];
        }
    } elseif ($recipientMode === 'internal') {
        $uid = $_POST['internal_user_id'] ?? '';
        if (isset($deptUsers[$uid])) {
            $recipientName = $deptUsers[$uid]['full_name'];
            $recipientAddress = "Department: $deptId";
        }
    } elseif ($recipientMode === 'ext_dept') {
        $eid = $_POST['ext_dept_id'] ?? '';
        $euid = $_POST['ext_user_id'] ?? '';
        if ($eid && $euid) {
            $eUsers = readJSON("departments/$eid/users/users.json");
            if (isset($eUsers[$euid])) {
                $recipientName = $eUsers[$euid]['full_name'];
                $deptInfo = getDepartment($eid);
                $recipientAddress = ($deptInfo['name'] ?? $eid);
            }
        }
    } elseif ($recipientMode === 'manual') {
        $recipientName = trim($_POST['manual_name'] ?? '');
        $recipientAddress = trim($_POST['manual_address'] ?? '');
        $des = trim($_POST['manual_designation'] ?? '');
        if ($des) $recipientName .= " ($des)";
    }

    // Content Loading
    $content = '';
    if (!empty($customContent)) {
        $content = $customContent;
    } elseif ($templateId === 'blank') {
        $content = '<html><body><p>Type your content here...</p></body></html>';
    } elseif (isset($templates[$templateId])) {
        $t = $templates[$templateId];
        $path = $t['is_global']
            ? "storage/system/templates/" . $t['filename']
            : "storage/departments/$deptId/templates/" . $t['filename'];
        if (file_exists($path)) $content = file_get_contents($path);
    }

    // Variable Replacement
    $replacements = [
        '{{recipient_name}}' => $recipientName,
        '{{recipient_address}}' => $recipientAddress,
        '{{contractor_name}}' => $recipientName,
        '{{contractor_address}}' => $recipientAddress,
        '{{current_date}}' => date('d-m-Y'),
        '{{department_name}}' => $fileMeta['dept_name'] ?? 'Department' // Assuming dept name available or fetch it
    ];
    // Fetch Dept Name if not in meta
    if (!isset($fileMeta['dept_name'])) {
        $d = getDepartment($deptId);
        $replacements['{{department_name}}'] = $d['name'] ?? 'Department';
    }

    $generatedHtml = str_replace(array_keys($replacements), array_values($replacements), $content);
    $documentTitle = ($templates[$templateId]['title'] ?? 'New Document') . ' - ' . $recipientName;
}

// Handle Attachments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachment']) && $editDocId) {
    // Only allow attachment if we have a docId context (or we must create one, but for now assuming attached to current doc)
    // If user is just starting, they need to save draft first? No, workbench should handle it.
    // But since attachment logic is stateless here, we need the docId.
    // If no docId, we can't link it easily without saving doc first.
    // I'll assume this runs when editing a draft.

    $uploadDir = $fileDir . 'attachments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $f = $_FILES['attachment'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (in_array($ext, $allowed)) {
            $newName = time() . '_' . preg_replace('/[^a-z0-9.]/i', '_', $f['name']);
            if (move_uploaded_file($f['tmp_name'], $uploadDir . $newName)) {
                $message = "Attachment uploaded.";

                // Link to Document
                $docPath = $fileDir . "documents/$editDocId.json";
                if (file_exists($docPath)) {
                    $docData = json_decode(file_get_contents($docPath), true);
                    if (!isset($docData['attachments'])) $docData['attachments'] = [];
                    $docData['attachments'][] = $newName;
                    file_put_contents($docPath, json_encode($docData, JSON_PRETTY_PRINT));
                }
            }
        } else {
            $error = "Invalid file type.";
        }
    }
}

// Handle Actions (Save Draft, Finalize, Dispatch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action = $_POST['action_type']; // save_draft, finalize, dispatch
    $title = $_POST['doc_title'] ?? 'Untitled';
    $html = $_POST['doc_content'] ?? '';
    $docId = $_POST['doc_id'] ?? ''; // Existing ID if editing

    if (empty($html)) {
        $error = "Content is empty.";
    } else {
        // Determine Doc ID
        if (!$docId) {
            $docId = 'doc_' . time(); // Simple timestamp based ID inside file
        }

        $newStatus = ($action === 'save_draft') ? 'draft' : 'final';

        $docData = [
            'id' => $docId,
            'title' => $title,
            'content' => $html,
            'status' => $newStatus,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'document' // Distinguish from other file items
        ];

        // Save Document
        $docPath = $fileDir . "documents/$docId.json";
        if (!is_dir(dirname($docPath))) mkdir(dirname($docPath), 0777, true);
        file_put_contents($docPath, json_encode($docData, JSON_PRETTY_PRINT));

        // Dispatch Logic
        if ($action === 'dispatch') {
            // Read dispatch params
            $targetType = $_POST['dispatch_target_type'];
            $targetUser = $_POST['dispatch_target_user'] ?? '';
            $targetDept = $_POST['dispatch_target_dept'] ?? '';
            $dakMode = $_POST['dispatch_mode'] ?? 'Hand';

            // Add to Dak Register
            $dakPath = "departments/$deptId/data/dak_register.json";
            $dakRegister = readJSON($dakPath) ?? [];

            $year = date('Y');
            $count = count($dakRegister) + 1;
            $refNo = 'DAK/OUT/' . $year . '/' . sprintf('%03d', $count);

            $dakEntry = [
                'ref_no' => $refNo,
                'type' => 'outgoing',
                'source' => 'Digital File',
                'file_id' => $fileId,
                'doc_id' => $docId,
                'sender' => $userId, // Or Dept Name
                'subject' => $title,
                'mode' => $dakMode,
                'target_type' => $targetType,
                'target_user' => $targetUser,
                'target_dept' => $targetDept,
                'received_date' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'status' => 'logged'
            ];

            $dakRegister[] = $dakEntry;
            writeJSON($dakPath, $dakRegister);

            // Notifications
            $notifTargets = [];
            if ($targetType === 'internal' && $targetUser) {
                $notifTargets[] = ['dept' => $deptId, 'user' => $targetUser];
            } elseif ($targetType === 'ext_dept' && $targetDept) {
                 // Notify Admin of that Dept
                 $tUsers = readJSON("departments/$targetDept/users/users.json") ?? [];
                 foreach ($tUsers as $uid => $u) {
                     if (($u['role'] ?? '') === "admin.$targetDept") {
                         $notifTargets[] = ['dept' => $targetDept, 'user' => $uid];
                         break;
                     }
                 }
            }

            foreach ($notifTargets as $t) {
                $nPath = "departments/{$t['dept']}/data/notifications.json";
                $nList = readJSON($nPath) ?? [];
                $nList[] = [
                    'id' => uniqid('NOTIF_'),
                    'user_id' => $t['user'],
                    'message' => "New Dak from File $fileId: $title",
                    'link' => 'dak_register.php',
                    'read' => false,
                    'time' => date('Y-m-d H:i:s')
                ];
                writeJSON($nPath, $nList);
            }

            $message = "Document Dispatched successfully. Ref: $refNo";
            // Redirect after dispatch
            header("Location: view_file.php?id=$fileId");
            exit;
        } elseif ($action === 'finalize') {
             // Redirect to the same page, which will now render as Read-Only / Print View
             header("Location: add_document.php?file_id=$fileId&doc_id=$docId");
             exit;
        } else {
             // Draft saved
             $editDocId = $docId; // Keep editing
             $message = "Draft saved.";
        }
    }
}

// AI Config Check
$gConfig = readJSON('system/global_config.json');
$aiConfig = $gConfig['ai_config'] ?? [];
$hasAI = !empty($aiConfig['openai']['key']) || !empty($aiConfig['gemini']['key']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Document to <?php echo htmlspecialchars($fileId); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="print.css">
    <style>
        .workbench-container { display: flex; flex-direction: column; height: 100vh; }
        .toolbar { padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ddd; display: flex; gap: 10px; align-items: center; }
        .editor-area { flex-grow: 1; overflow: auto; background: #e9ecef; padding: 20px; display: flex; justify-content: center; }
        .action-bar { padding: 15px; background: #fff; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }

        .page {
            width: 210mm; min-height: 297mm; padding: 20mm; background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1); color: black; font-family: 'Times New Roman', serif;
        }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; width: 500px; border-radius: 8px; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; }

        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="workbench-container">
        <!-- Top Toolbar -->
        <div class="toolbar no-print">
            <a href="view_file.php?id=<?php echo $fileId; ?>" class="btn-secondary">&larr; Back to File</a>
            <span style="font-weight:bold; margin-left:10px;">File: <?php echo htmlspecialchars($fileId); ?></span>

            <div style="flex-grow:1;"></div>

            <?php if (!$isReadOnly): ?>
                <button onclick="document.getElementById('setupModal').style.display='block'" class="btn-primary">
                    <?php echo $generatedHtml ? 'Change Template / Setup' : 'Start / Select Template'; ?>
                </button>

                <?php if($hasAI): ?>
                <button onclick="document.getElementById('aiModal').style.display='block'" class="btn-secondary" style="background:#6f42c1; color:white;">✨ AI Draft</button>
                <?php endif; ?>
            <?php else: ?>
                <span style="color:red; font-weight:bold; margin-right:10px;">READ ONLY</span>
                <button onclick="window.print()" class="btn-primary">Print</button>
            <?php endif; ?>
        </div>

        <!-- Editor Area -->
        <div class="editor-area">
            <?php if ($generatedHtml): ?>
                <div class="page" id="documentPage">
                    <div id="editableContent" contenteditable="<?php echo $isReadOnly ? 'false' : 'true'; ?>" style="outline:none; min-height: 200px;">
                        <?php echo $generatedHtml; ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align:center; margin-top:50px; color:#666;">
                    <h3>No Document Content Yet</h3>
                    <p>Click "Start / Select Template" or "AI Draft" to begin.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Bar -->
        <div class="action-bar no-print">
             <div>
                <?php if (!$isReadOnly && $editDocId): ?>
                    <form id="uploadForm" method="POST" enctype="multipart/form-data" style="display:inline;">
                        <input type="file" name="attachment" style="display:none;" id="attInput" onchange="this.form.submit()">
                        <button type="button" onclick="document.getElementById('attInput').click()" class="btn-secondary">📎 Upload Attachment</button>
                    </form>
                <?php endif; ?>
                <?php if($message) echo "<span style='color:green; margin-left:10px;'>$message</span>"; ?>
                <?php if($error) echo "<span style='color:red; margin-left:10px;'>$error</span>"; ?>
             </div>

             <?php if ($generatedHtml && !$isReadOnly): ?>
             <div style="display:flex; gap:10px;">
                 <button onclick="submitAction('save_draft')" class="btn-secondary">Save Draft</button>
                 <button onclick="submitAction('finalize')" class="btn-primary">Finalize as Letter</button>
                 <button onclick="openDispatchModal()" class="btn-primary" style="background:#28a745;">Dispatch as Dak</button>
             </div>
             <?php endif; ?>
        </div>
    </div>

    <!-- Hidden Form for Actions -->
    <form id="mainForm" method="POST" style="display:none;">
        <input type="hidden" name="action_type" id="action_type">
        <input type="hidden" name="doc_title" id="form_doc_title" value="<?php echo htmlspecialchars($documentTitle); ?>">
        <input type="hidden" name="doc_content" id="form_doc_content">
        <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($editDocId); ?>">

        <!-- Dispatch Fields -->
        <input type="hidden" name="dispatch_target_type" id="d_target_type">
        <input type="hidden" name="dispatch_target_user" id="d_target_user">
        <input type="hidden" name="dispatch_target_dept" id="d_target_dept">
        <input type="hidden" name="dispatch_mode" id="d_mode">
    </form>

    <!-- Setup / Template Modal -->
    <div id="setupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('setupModal').style.display='none'">&times;</span>
            <h2>Document Setup</h2>
            <form method="POST">
                <input type="hidden" name="generate" value="1">
                <input type="hidden" name="custom_content" id="setup_custom_content">

                <div class="form-group">
                    <label>Recipient Mode</label>
                    <select name="recipient_mode" onchange="toggleSetupMode(this.value)" style="width:100%">
                        <option value="contractor">Contractor</option>
                        <option value="internal">Internal Staff</option>
                        <option value="ext_dept">External Department</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>

                <div id="setup_contractor" class="setup-section">
                    <label>Select Contractor</label>
                    <select name="contractor_id" style="width:100%">
                        <?php foreach ($contractors as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="setup_internal" class="setup-section hidden">
                    <label>Select Staff</label>
                    <select name="internal_user_id" style="width:100%">
                        <?php foreach ($deptUsers as $uid => $u): ?>
                            <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="setup_ext_dept" class="setup-section hidden">
                    <!-- Simplified for brevity, would need AJAX for users -->
                    <label>Department ID</label>
                    <input type="text" name="ext_dept_id" placeholder="Dept ID" style="width:100%; margin-bottom:5px;">
                    <label>User ID</label>
                    <input type="text" name="ext_user_id" placeholder="User ID" style="width:100%">
                </div>

                <div id="setup_manual" class="setup-section hidden">
                    <input type="text" name="manual_name" placeholder="Name" style="width:100%; margin-bottom:5px;">
                    <textarea name="manual_address" placeholder="Address" style="width:100%"></textarea>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Template</label>
                    <select name="template_id" style="width:100%">
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['display_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top:15px;">Generate</button>
            </form>
        </div>
    </div>

    <!-- AI Modal -->
    <div id="aiModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('aiModal').style.display='none'">&times;</span>
            <h2>AI Assistant</h2>
            <textarea id="aiPrompt" rows="4" style="width:100%" placeholder="Describe your letter..."></textarea>
            <button onclick="runAI()" class="btn-primary" style="margin-top:10px;">Generate Draft</button>
            <div id="aiStatus"></div>
        </div>
    </div>

    <!-- Dispatch Modal -->
    <div id="dispatchModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('dispatchModal').style.display='none'">&times;</span>
            <h2>Dispatch as Dak</h2>
            <div class="form-group">
                <label>Target Type</label>
                <select id="modal_target_type" onchange="toggleDispatchTargets()" style="width:100%">
                    <option value="internal">Internal User</option>
                    <option value="ext_dept">External Department</option>
                    <option value="ext_other">External (Manual)</option>
                </select>
            </div>

            <div id="modal_target_internal" class="dispatch-section">
                <label>Select User</label>
                <select id="modal_int_user" style="width:100%">
                     <?php foreach ($deptUsers as $uid => $u): ?>
                        <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="modal_target_ext" class="dispatch-section hidden">
                <label>Target Dept ID</label>
                <input type="text" id="modal_ext_dept" style="width:100%">
            </div>

            <div class="form-group">
                <label>Mode</label>
                <select id="modal_mode" style="width:100%">
                    <option value="Hand">Hand</option>
                    <option value="Courier">Courier</option>
                    <option value="Speed Post">Speed Post</option>
                </select>
            </div>

            <button onclick="confirmDispatch()" class="btn-primary" style="width:100%; margin-top:15px; background:green;">Confirm Dispatch</button>
        </div>
    </div>

    <script>
        function toggleSetupMode(val) {
            document.querySelectorAll('.setup-section').forEach(el => el.classList.add('hidden'));
            document.getElementById('setup_' + val).classList.remove('hidden');
        }

        function runAI() {
            var p = document.getElementById('aiPrompt').value;
            var st = document.getElementById('aiStatus');
            if(!p) return;
            st.innerHTML = 'Generating...';

            var fd = new FormData();
            fd.append('prompt', p);
            fd.append('provider', 'openai'); // Defaulting, or add selector

            fetch('ai_helper.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') {
                    document.getElementById('setup_custom_content').value = d.data;
                    document.getElementById('aiModal').style.display = 'none';
                    // Trigger Setup Modal to choose template (likely Blank)
                    document.getElementById('setupModal').style.display = 'block';
                    alert("AI Content Generated. Select 'Create Blank Document' in setup to use it.");
                } else {
                    st.innerHTML = 'Error: ' + d.message;
                }
            });
        }

        function submitAction(action) {
            document.getElementById('action_type').value = action;
            document.getElementById('form_doc_content').value = document.getElementById('editableContent').innerHTML;

            // Allow user to edit title if needed - simplified here
            // document.getElementById('form_doc_title').value = ...

            document.getElementById('mainForm').submit();
        }

        function openDispatchModal() {
            document.getElementById('dispatchModal').style.display = 'block';
        }

        function toggleDispatchTargets() {
            var val = document.getElementById('modal_target_type').value;
            document.getElementById('modal_target_internal').classList.add('hidden');
            document.getElementById('modal_target_ext').classList.add('hidden');

            if(val === 'internal') document.getElementById('modal_target_internal').classList.remove('hidden');
            if(val === 'ext_dept') document.getElementById('modal_target_ext').classList.remove('hidden');
        }

        function confirmDispatch() {
            var tType = document.getElementById('modal_target_type').value;
            var tUser = document.getElementById('modal_int_user').value;
            var tDept = document.getElementById('modal_ext_dept').value;
            var mode = document.getElementById('modal_mode').value;

            document.getElementById('d_target_type').value = tType;
            document.getElementById('d_target_user').value = tUser;
            document.getElementById('d_target_dept').value = tDept;
            document.getElementById('d_mode').value = mode;

            submitAction('dispatch');
        }
    </script>
</body>
</html>
