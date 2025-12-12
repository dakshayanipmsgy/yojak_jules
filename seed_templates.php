<?php

$templatesDir = __DIR__ . '/storage/system/templates/';

if (!is_dir($templatesDir)) {
    mkdir($templatesDir, 0777, true);
    echo "Created directory: $templatesDir\n";
}

$templates = [
    'work_order.html' => <<<HTML
<p style="text-align: center; font-weight: bold;">GOVERNMENT OF JHARKHAND</p>
<p style="text-align: center; font-weight: bold;">OFFICE OF THE EXECUTIVE ENGINEER</p>
<p style="text-align: center; font-weight: bold;">{{department_name}}</p>
<hr>
<p><strong>Letter No:</strong> {{ref_number}} <span style="float: right;"><strong>Date:</strong> {{current_date}}</span></p>
<p><strong>To,</strong><br>{{recipient_name}}<br>{{recipient_address}}<br><strong>Contractor ID:</strong> {{recipient_id}}</p>
<p><strong>Subject: Work Order for the work "{{work_name}}"</strong></p>
<p><strong>Ref:</strong> Tender Notice No. {{tender_id}} dated {{tender_date}}</p>
<p>Sir,</p>
<p>With reference to the subject cited above, your tender for the work mentioned below has been accepted by the competent authority.</p>
<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
<tr><td style="width: 40%;"><strong>Name of Work</strong></td><td>{{work_name}}</td></tr>
<tr><td><strong>Estimated Cost</strong></td><td>Rs. {{estimated_cost}}</td></tr>
<tr><td><strong>Agreed Amount</strong></td><td>Rs. {{agreed_amount}}</td></tr>
<tr><td><strong>Time of Completion</strong></td><td>{{completion_time}} Months/Days</td></tr>
</table>
<p>You are hereby directed to attend this office within 7 days to sign the formal agreement and commence the work immediately under the supervision of the Assistant Engineer / Junior Engineer.</p>
<p>Yours faithfully,</p>
<br><br><p><strong>Executive Engineer</strong><br>{{department_name}}</p>
HTML,

    'show_cause.html' => <<<HTML
<p style="text-align: center; font-weight: bold;">GOVERNMENT OF JHARKHAND</p>
<p style="text-align: center; font-weight: bold;">{{department_name}}</p>
<hr>
<p><strong>Memo No:</strong> {{ref_number}} <span style="float: right;"><strong>Date:</strong> {{current_date}}</span></p>
<p><strong>To,</strong><br>{{recipient_name}}<br>{{recipient_designation}}</p>
<p><strong>Subject: SHOW CAUSE NOTICE for delay in execution of work.</strong></p>
<p>Sir/Madam,</p>
<p>It has come to the notice of the undersigned that the progress of the work <strong>"{{work_name}}"</strong> is highly unsatisfactory. Despite repeated verbal instructions, no significant improvement has been observed.</p>
<p>You are hereby directed to explain within <strong>3 days</strong> why action should not be taken against you.</p>
<br><br><p><strong>Competent Authority</strong><br>{{department_name}}</p>
HTML,

    'sanction_letter.html' => <<<HTML
<p style="text-align: center; font-weight: bold;">OFFICE ORDER</p>
<p style="text-align: center; font-weight: bold;">{{department_name}}</p>
<hr>
<p><strong>Order No:</strong> {{ref_number}} <span style="float: right;"><strong>Date:</strong> {{current_date}}</span></p>
<p><strong>Subject: Administrative Approval for "{{work_name}}"</strong></p>
<p>Sanction is hereby accorded for the execution of the scheme mentioned below under the Head of Account <strong>{{head_of_account}}</strong> for the financial year {{financial_year}}.</p>
<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
<tr><td><strong>1. Name of Scheme</strong></td><td>{{work_name}}</td></tr>
<tr><td><strong>2. Sanctioned Amount</strong></td><td>Rs. {{sanction_amount}} ({{amount_in_words}})</td></tr>
</table>
<br><br><p><strong>Secretary</strong><br>{{department_name}}</p>
HTML,

    'meeting_notice.html' => <<<HTML
<p style="text-align: center; font-weight: bold;">NOTICE OF MEETING</p>
<p style="text-align: center; font-weight: bold;">{{department_name}}</p>
<hr>
<p><strong>Memo No:</strong> {{ref_number}} <span style="float: right;"><strong>Date:</strong> {{current_date}}</span></p>
<p><strong>To,</strong><br>All Concerned Officials</p>
<p><strong>Subject: Review Meeting regarding {{meeting_subject}}</strong></p>
<p>Sir/Madam, A meeting has been scheduled regarding the subject above.</p>
<ul><li><strong>Date:</strong> {{meeting_date}}</li><li><strong>Time:</strong> {{meeting_time}}</li><li><strong>Venue:</strong> {{meeting_venue}}</li></ul>
<p>Please attend on time with relevant reports.</p>
<br><br><p><strong>Authorized Signatory</strong><br>{{department_name}}</p>
HTML,

    'noc_certificate.html' => <<<HTML
<p style="text-align: center; font-weight: bold;">TO WHOM IT MAY CONCERN</p>
<p style="text-align: center; font-weight: bold;">{{department_name}}</p>
<hr>
<p><strong>Ref No:</strong> {{ref_number}} <span style="float: right;"><strong>Date:</strong> {{current_date}}</span></p>
<p><strong>Subject: No Objection Certificate (NOC)</strong></p>
<p>This is to certify that this Department has <strong>No Objection</strong> to the proposal submitted by <strong>{{recipient_name}}</strong> for the purpose of <strong>{{noc_purpose}}</strong>.</p>
<p>Valid for: <strong>{{validity_period}}</strong> months.</p>
<br><br><p><strong>Executive Engineer</strong><br>{{department_name}}</p>
HTML
];

foreach ($templates as $filename => $content) {
    file_put_contents($templatesDir . $filename, $content);
    echo "Created template: $filename\n";
}

// Also update templates.json index if needed?
// The prompt doesn't explicitly ask for it, but `add_document.php` reads `system/templates/templates.json`.
// I should update that too so they appear in the list.

$indexFile = $templatesDir . 'templates.json';
$index = [];
if (file_exists($indexFile)) {
    $index = json_decode(file_get_contents($indexFile), true) ?? [];
}

$newEntries = [
    'work_order' => ['id' => 'work_order', 'title' => 'Work Order', 'filename' => 'work_order.html'],
    'show_cause' => ['id' => 'show_cause', 'title' => 'Show Cause Notice', 'filename' => 'show_cause.html'],
    'sanction_letter' => ['id' => 'sanction_letter', 'title' => 'Sanction Letter', 'filename' => 'sanction_letter.html'],
    'meeting_notice' => ['id' => 'meeting_notice', 'title' => 'Meeting Notice', 'filename' => 'meeting_notice.html'],
    'noc_certificate' => ['id' => 'noc_certificate', 'title' => 'NOC Certificate', 'filename' => 'noc_certificate.html']
];

foreach ($newEntries as $id => $entry) {
    // Check if exists by ID or filename to avoid duplicates
    $exists = false;
    foreach ($index as $existing) {
        if ($existing['id'] === $id) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $index[] = $entry;
    }
}

file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
echo "Updated templates.json\n";

?>
