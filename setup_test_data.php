<?php
require_once 'functions.php';

// Create a test department if not exists
$deptId = 'test_dept';
if (!is_dir(STORAGE_PATH . '/departments/' . $deptId)) {
    createDepartment('Test Department', $deptId, 'password123');
}

// Create Role
createRole($deptId, 'Clerk');
// Create a regular user
createUser($deptId, 'Test User', 'password123', 'clerk.' . $deptId);

// Create another user for forwarding
createRole($deptId, 'Officer');
createUser($deptId, 'Test Officer', 'password123', 'officer.' . $deptId);

// Ensure templates exist
if (!is_dir(STORAGE_PATH . '/departments/' . $deptId . '/templates')) {
    mkdir(STORAGE_PATH . '/departments/' . $deptId . '/templates', 0755, true);
}
$templateData = [
    'id' => 'temp_01',
    'title' => 'Test Template',
    'filename' => 'test_template.html',
    'created_at' => date('Y-m-d')
];
// Write template metadata
$templates = ['temp_01' => $templateData];
writeJSON('departments/' . $deptId . '/templates/templates.json', $templates);
// Write template file
file_put_contents(STORAGE_PATH . '/departments/' . $deptId . '/templates/test_template.html', '<h1>Contract for {{contractor_name}}</h1><p>Address: {{contractor_address}}</p>');

// Ensure contractors exist
if (!is_dir(STORAGE_PATH . '/departments/' . $deptId . '/data')) {
    mkdir(STORAGE_PATH . '/departments/' . $deptId . '/data', 0755, true);
}
$contractorData = [
    'id' => 'cont_01',
    'name' => 'Acme Corp',
    'address' => '123 Acme Way',
    'mobile' => '555-0199',
    'pan' => 'ABCDE1234F',
    'gst' => '22AAAAA0000A1Z5'
];
$contractors = ['cont_01' => $contractorData];
writeJSON('departments/' . $deptId . '/data/contractors.json', $contractors);

echo "Test data setup complete.";
?>
