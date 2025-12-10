<?php
require_once 'auth_check.php';
require_once 'functions.php';

$deptId = $_SESSION['dept_id'];
$message = '';
$error = '';
$generatedHtml = '';

// Load Data for Dropdowns
$contractors = readJSON('departments/' . $deptId . '/data/contractors.json') ?? [];
$localTemplates = readJSON('departments/' . $deptId . '/templates/templates.json') ?? [];
$systemTemplates = readJSON('system/templates/templates.json') ?? [];
$deptUsers = getUsers($deptId);

// Merge Templates
// We mark system templates with [Global] tag in UI
$templates = [];

// Hardcoded "Blank" Template
$templates['blank'] = [
    'id' => 'blank',
    'title' => 'Create Blank Document',
    'display_title' => 'Create Blank Document',
    'is_global' => false,
    'filename' => ''
];

// System first
foreach ($systemTemplates as $t) {
    $t['is_global'] = true;
    $t['display_title'] = $t['title'] . ' [Global]';
    $templates[$t['id']] = $t;
}
// Local second (can overwrite if IDs conflict, but IDs should be unique enough)
foreach ($localTemplates as $t) {
    $t['is_global'] = false;
    $t['display_title'] = $t['title'];
    $templates[$t['id']] = $t;
}

// Handle Edit Mode
$editDocId = $_GET['edit_doc_id'] ?? '';
$editDoc = null;
if ($editDocId) {
    $editDoc = getDocument($deptId, $editDocId);
    if ($editDoc && $editDoc['status'] === 'Draft' && $editDoc['created_by'] === $_SESSION['user_id']) {
        // Valid edit
    } else {
        $editDoc = null; // Invalid or not allowed
        $error = "Cannot edit this document.";
    }
}

// Handle Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $templateId = $_POST['template_id'] ?? '';
    $recipientMode = $_POST['recipient_mode'] ?? 'contractor';

    // Resolve Recipient Info
    $recipientName = '';
    $recipientAddress = '';

    // Validation
    $validSelection = false;

    if ($recipientMode === 'contractor') {
        $contractorId = $_POST['contractor_id'] ?? '';
        if (isset($contractors[$contractorId])) {
            $c = $contractors[$contractorId];
            $recipientName = $c['name'];
            $recipientAddress = $c['address'];
            $validSelection = true;
        }
    } elseif ($recipientMode === 'internal') {
        $userId = $_POST['internal_user_id'] ?? '';
        if (isset($deptUsers[$userId])) {
            $u = $deptUsers[$userId];
            $recipientName = $u['full_name'];
            $recipientAddress = 'Department: ' . $deptId; // Internal address
            $validSelection = true;
        }
    } elseif ($recipientMode === 'ext_dept') {
        $extDeptId = $_POST['ext_dept_id'] ?? '';
        $extUserId = $_POST['ext_user_id'] ?? '';

        // We need to fetch the user name from that dept
        if ($extDeptId && $extUserId) {
            $extUsers = readJSON('departments/' . $extDeptId . '/users/users.json');
            if (isset($extUsers[$extUserId])) {
                $recipientName = $extUsers[$extUserId]['full_name'];
                $deptInfo = getDepartment($extDeptId);
                $recipientAddress = $deptInfo['name'] . ' (' . $extDeptId . ')';
                $validSelection = true;
            }
        }
    } elseif ($recipientMode === 'manual') {
        $recipientName = trim($_POST['manual_name'] ?? '');
        $recipientAddress = trim($_POST['manual_address'] ?? '');
        $designation = trim($_POST['manual_designation'] ?? '');
        if ($recipientName) {
            $recipientName .= $designation ? " ($designation)" : '';
            $validSelection = true;
        }
    }

    // Handle AI Generated Content Injection
    $customContent = $_POST['custom_content'] ?? '';

    if (empty($templateId) || !$validSelection) {
        $error = "Please select a template and valid recipient.";
    } else {
        if (!isset($templates[$templateId])) {
            $error = "Invalid template selection.";
        } else {
            $templateData = $templates[$templateId];

            // Load Template Content
            $content = '';
            if (!empty($customContent)) {
                // Use AI Content if provided
                $content = $customContent;
            } elseif ($templateId === 'blank') {
                $content = '<html><body><p>Type your content here...</p></body></html>';
            } else {
                $templatePath = '';
                if (isset($templateData['is_global']) && $templateData['is_global']) {
                    $templatePath = STORAGE_PATH . '/system/templates/' . $templateData['filename'];
                } else {
                    $templatePath = STORAGE_PATH . '/departments/' . $deptId . '/templates/' . $templateData['filename'];
                }

                if (file_exists($templatePath)) {
                    $content = file_get_contents($templatePath);
                } else {
                    $error = "Template file not found.";
                }
            }

            if (!$error) {
                // Replacements
                $replacements = [
                    '{{recipient_name}}' => $recipientName,
                    '{{recipient_address}}' => $recipientAddress,
                    '{{contractor_name}}' => $recipientName, // Backward compat
                    '{{contractor_address}}' => $recipientAddress, // Backward compat
                    '{{current_date}}' => date('d-m-Y')
                ];

                // Department name replacement
                $deptMeta = getDepartment($deptId);
                $replacements['{{department_name}}'] = $deptMeta['name'] ?? 'Department';

                // Add Dak info if present
                $dakRef = $_GET['dak_ref'] ?? '';
                $dakSender = $_GET['sender'] ?? '';
                $dakSubject = $_GET['subject'] ?? '';

                if ($dakRef) {
                    $replacements['{{dak_ref}}'] = htmlspecialchars($dakRef);
                    $replacements['{{dak_sender}}'] = htmlspecialchars($dakSender);
                    $replacements['{{dak_subject}}'] = htmlspecialchars($dakSubject);
                }

                $generatedHtml = str_replace(array_keys($replacements), array_values($replacements), $content);

                // Keep the titles to pass to save logic
                $documentTitle = ($dakRef ? "[$dakRef] " : "") . $templateData['title'] . ' - ' . $recipientName;
            }
        }
    }
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_document'])) {
    $title = $_POST['title'] ?? 'Untitled Document';
    $htmlContent = $_POST['html_content'] ?? '';
    $templateIdSaved = $_POST['template_id'] ?? '';
    $existingDocId = $_POST['existing_doc_id'] ?? '';

    if (empty($htmlContent)) {
        $error = "No content to save.";
    } else {
        $userId = $_SESSION['user_id'];

        if ($existingDocId) {
            // Update existing
            $docId = $existingDocId;
            $docData = getDocument($deptId, $docId);
            if (!$docData) {
                die("Error: Document not found.");
            }
            // Update fields
            $docData['title'] = $title;
            $docData['content'] = $htmlContent;
            $docData['template_id'] = $templateIdSaved;

            $docData['history'][] = [
                'action' => 'updated',
                'from' => $userId,
                'to' => $userId,
                'time' => date('Y-m-d H:i:s')
            ];

        } else {
            // Create New
            $docId = generateDocumentID();
            $docData = [
                'id' => $docId,
                'title' => $title,
                'content' => $htmlContent,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'current_owner' => $userId,
                'status' => 'Draft',
                'history' => [],
                'template_id' => $templateIdSaved
            ];

            // Initial Log
            $docData['history'][] = [
                'action' => 'created',
                'from' => $userId,
                'to' => $userId,
                'time' => date('Y-m-d H:i:s')
            ];
        }

        if (saveDocument($deptId, $docId, $docData)) {
            $message = "Document saved as Draft. ID: $docId";
            // Redirect to dashboard or view page
            header("Location: view_document.php?id=$docId");
            exit;
        } else {
            $error = "Failed to save document.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Document - Yojak</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="print.css">
    <style>
        .selection-panel {
            background: white;
            padding: 1.5rem;
            margin: 2rem auto;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preview-actions {
            text-align: center;
            margin-bottom: 2rem;
        }

        /* Screen View for Page */
        .page-preview {
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            margin: 20px auto;
            border: 1px solid #ccc;
        }

        .mode-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .mode-selector label {
            cursor: pointer;
            font-weight: normal;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php include 'navbar.php'; ?>
    </div>

    <div class="dashboard-header no-print">
        <div class="header-left">
            <h1>Create Document</h1>
        </div>
    </div>

    <?php if (!$generatedHtml): ?>
        <div class="selection-panel no-print">
            <h2><?php echo $editDoc ? 'Edit Document: ' . htmlspecialchars($editDoc['title']) : 'Generate New Document'; ?></h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="generateForm">
                <input type="hidden" name="generate" value="1">
                <input type="hidden" name="custom_content" id="custom_content_field" value="">
                <?php if ($editDocId): ?>
                    <input type="hidden" name="existing_doc_id" value="<?php echo htmlspecialchars($editDocId); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Recipient Mode</label>
                    <div class="mode-selector">
                        <label><input type="radio" name="recipient_mode" value="contractor" checked onclick="toggleMode()"> Contractor</label>
                        <label><input type="radio" name="recipient_mode" value="internal" onclick="toggleMode()"> Internal Staff</label>
                        <label><input type="radio" name="recipient_mode" value="ext_dept" onclick="toggleMode()"> External Dept</label>
                        <label><input type="radio" name="recipient_mode" value="manual" onclick="toggleMode()"> Manual / Open</label>
                    </div>
                </div>

                <!-- Recipient Containers -->
                <div id="container_contractor" class="mode-container">
                    <div class="form-group">
                        <label>Select Contractor</label>
                        <select name="contractor_id">
                            <option value="">-- Choose Contractor --</option>
                            <?php foreach ($contractors as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['mobile']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="container_internal" class="mode-container hidden">
                    <div class="form-group">
                        <label>Select Internal Staff</label>
                        <select name="internal_user_id">
                            <option value="">-- Choose Staff --</option>
                            <?php foreach ($deptUsers as $uid => $u): ?>
                                <option value="<?php echo $uid; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="container_ext_dept" class="mode-container hidden">
                    <div class="form-group">
                        <label>Select Department</label>
                        <select name="ext_dept_id" id="ext_dept_id" onchange="fetchRoles()">
                            <option value="">-- Choose Department --</option>
                            <?php
                            $allDepts = getAllDepartments();
                            foreach ($allDepts as $d) {
                                if ($d['id'] === $deptId) continue;
                                echo '<option value="' . $d['id'] . '">' . htmlspecialchars($d['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Role</label>
                        <select name="ext_role_id" id="ext_role_id" disabled onchange="fetchUsers()">
                            <option value="">-- First Select Dept --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select User</label>
                        <select name="ext_user_id" id="ext_user_id" disabled>
                            <option value="">-- First Select Role --</option>
                        </select>
                    </div>
                </div>

                <div id="container_manual" class="mode-container hidden">
                    <div class="form-group">
                        <label>Recipient Name</label>
                        <input type="text" name="manual_name" placeholder="Name">
                    </div>
                    <div class="form-group">
                        <label>Designation (Optional)</label>
                        <input type="text" name="manual_designation" placeholder="e.g. Director">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="manual_address" rows="3" placeholder="Full Address"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Template</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <select name="template_id" id="template_id_select" required style="flex-grow:1;">
                            <option value="">-- Choose Template --</option>
                            <option value="blank" style="font-weight:bold;">Create Blank Document</option>
                            <?php foreach ($templates as $t): ?>
                                <?php if ($t['id'] === 'blank') continue; ?>
                            <?php
                                $selected = '';
                                if (isset($_POST['template_id']) && $_POST['template_id'] == $t['id']) $selected = 'selected';
                                elseif ($editDoc && isset($editDoc['template_id']) && $editDoc['template_id'] == $t['id']) $selected = 'selected';
                            ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($t['display_title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-secondary" style="background:#6f42c1; color:white; border:none;" onclick="openAiModal()">✨ Draft with AI</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="include_header" value="1" <?php if(isset($_POST['include_header'])) echo 'checked'; ?>>
                        Include Official Header?
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><?php echo $editDoc ? 'Update Preview' : 'Generate Document'; ?></button>
                </div>
            </form>
        </div>

        <!-- AI Modal -->
        <div id="aiModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeAiModal()">&times;</span>
                <h2>✨ AI Drafting Assistant</h2>
                <div class="form-group">
                    <label>Describe the letter you want to write:</label>
                    <textarea id="aiPrompt" rows="4" placeholder="e.g. Write a strict letter to Contractor XYZ regarding the delay in road construction project..."></textarea>
                </div>
                <div id="aiStatus" style="margin-bottom:10px; color:#666;"></div>
                <div class="form-actions">
                    <button type="button" class="btn-primary" onclick="generateWithAi()">Generate Draft</button>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="preview-actions no-print">
            <button onclick="window.print()" class="btn-primary">Print</button>

            <form method="POST" action="" style="display:inline-block;" id="saveForm">
                <input type="hidden" name="save_document" value="1">
                <input type="hidden" name="html_content" id="final_html_content" value="<?php echo htmlspecialchars($generatedHtml); ?>">
                <input type="hidden" name="title" value="<?php echo htmlspecialchars($documentTitle ?? 'New Document'); ?>">
                <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($templateId); ?>">
                <?php if ($editDocId): ?>
                    <input type="hidden" name="existing_doc_id" value="<?php echo htmlspecialchars($editDocId); ?>">
                <?php endif; ?>

                <button type="button" onclick="submitSave()" class="btn-secondary"><?php echo $editDocId ? 'Update Draft' : 'Save as Draft'; ?></button>
            </form>

            <a href="create_document.php" class="btn-secondary">Start Over</a>
        </div>

        <!-- Wrapper for Print Preview Logic -->
        <div class="page-preview page-a4 print-area">
            <?php if (isset($_POST['include_header'])): ?>
                <div class="official-header">
                    <div class="header-left">
                        <img src="https://via.placeholder.com/80" alt="Logo">
                    </div>
                    <div class="header-center">
                        <h1>Government of Yojak</h1>
                        <?php
                            $headerDept = getDepartment($deptId);
                            $headerDeptName = $headerDept['name'] ?? 'Department';
                        ?>
                        <h2><?php echo htmlspecialchars($headerDeptName); ?></h2>
                    </div>
                    <div class="header-right">
                        <p><strong>Date:</strong> <?php echo date('d-m-Y'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="document-content" id="editableContent" contenteditable="true" style="outline:none; min-height: 200px;">
                <?php echo $generatedHtml; ?>
            </div>
        </div>
    <?php endif; ?>

    <script>
    // Make content editable logic
    function submitSave() {
        var content = document.getElementById('editableContent').innerHTML;
        document.getElementById('final_html_content').value = content;
        document.getElementById('saveForm').submit();
    }

    // AI Modal Logic
    var aiModal = document.getElementById('aiModal');
    function openAiModal() {
        aiModal.style.display = 'block';
    }
    function closeAiModal() {
        aiModal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == aiModal) {
            closeAiModal();
        }
    }

    function generateWithAi() {
        var prompt = document.getElementById('aiPrompt').value;
        var status = document.getElementById('aiStatus');

        if(!prompt.trim()) {
            alert("Please enter a description.");
            return;
        }

        status.innerHTML = "Generating... Please wait...";

        var formData = new FormData();
        formData.append('prompt', prompt);

        fetch('ai_helper.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                status.innerHTML = "";
                // Set the custom content hidden field
                document.getElementById('custom_content_field').value = data.data;
                // Set template to 'blank' (as a carrier)
                document.getElementById('template_id_select').value = 'blank';
                // Close modal
                closeAiModal();
                // Submit main form
                document.getElementById('generateForm').submit();
            } else {
                status.innerHTML = "<span style='color:red'>Error: " + data.message + "</span>";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            status.innerHTML = "<span style='color:red'>An error occurred.</span>";
        });
    }

    function toggleMode() {
        var modes = ['contractor', 'internal', 'ext_dept', 'manual'];
        var selected = document.querySelector('input[name="recipient_mode"]:checked').value;

        modes.forEach(function(m) {
            var el = document.getElementById('container_' + m);
            if (m === selected) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    // AJAX for External Dept Flow
    function fetchRoles() {
        var deptId = document.getElementById('ext_dept_id').value;
        var roleSelect = document.getElementById('ext_role_id');
        var userSelect = document.getElementById('ext_user_id');

        roleSelect.innerHTML = '<option value="">Loading...</option>';
        roleSelect.disabled = true;
        userSelect.innerHTML = '<option value="">-- First Select Role --</option>';
        userSelect.disabled = true;

        if (deptId) {
            fetch('ajax_get_data.php?action=get_roles&dept_id=' + deptId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    roleSelect.innerHTML = '<option value="">-- Select Role --</option>';
                    data.data.forEach(role => {
                        var opt = document.createElement('option');
                        opt.value = role.id;
                        opt.textContent = role.name;
                        roleSelect.appendChild(opt);
                    });
                    roleSelect.disabled = false;
                }
            });
        } else {
            roleSelect.innerHTML = '<option value="">-- First Select Dept --</option>';
        }
    }

    function fetchUsers() {
        var deptId = document.getElementById('ext_dept_id').value;
        var roleId = document.getElementById('ext_role_id').value;
        var userSelect = document.getElementById('ext_user_id');

        userSelect.innerHTML = '<option value="">Loading...</option>';
        userSelect.disabled = true;

        if (deptId && roleId) {
            fetch('ajax_get_data.php?action=get_users&dept_id=' + deptId + '&role_id=' + roleId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    userSelect.innerHTML = '<option value="">-- Select User --</option>';
                    data.data.forEach(user => {
                        var opt = document.createElement('option');
                        opt.value = user.id;
                        opt.textContent = user.full_name;
                        userSelect.appendChild(opt);
                    });
                    userSelect.disabled = false;
                }
            });
        } else {
            userSelect.innerHTML = '<option value="">-- First Select Role --</option>';
        }
    }
    </script>
</body>
</html>
