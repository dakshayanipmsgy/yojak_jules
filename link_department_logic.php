<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor' || !isset($_SESSION['yojak_id'])) {
    header("Location: contractor_login.php");
    exit;
}

$yojakId = $_SESSION['yojak_id'];
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptCode = trim($_POST['dept_code'] ?? '');
    $localId = trim($_POST['local_id'] ?? '');

    if (!empty($deptCode) && !empty($localId)) {
        // 1. Validate Department Exists
        $deptPath = STORAGE_PATH . '/departments/' . $deptCode;
        if (!is_dir($deptPath)) {
            $_SESSION['flash_error'] = "Invalid Department Code.";
            header("Location: contractor_dashboard.php");
            exit;
        }

        // 2. Load Global Contractor Data
        $globalContractors = readJSON('global/data/contractors.json') ?? [];
        $contractor = $globalContractors[$yojakId] ?? null;

        if (!$contractor) {
            $_SESSION['flash_error'] = "Global profile error.";
            header("Location: contractor_dashboard.php");
            exit;
        }

        // Ensure linked_departments exists
        if (!isset($contractor['linked_departments'])) {
            $contractor['linked_departments'] = [];
        }

        // Check if already linked
        foreach ($contractor['linked_departments'] as $link) {
            if ($link['dept_id'] === $deptCode && $link['status'] === 'Verified') {
                 $_SESSION['flash_error'] = "You are already verified with this department.";
                 header("Location: contractor_dashboard.php");
                 exit;
            }
        }

        // 3. Load Local Department Contractor Data
        $localContractorsFile = "departments/$deptCode/data/contractors.json";

        $localContractors = readJSON($localContractorsFile);

        if (!$localContractors) {
             $_SESSION['flash_error'] = "Department database unavailable or empty.";
             header("Location: contractor_dashboard.php");
             exit;
        }

        // Find the local contractor entry
        $targetLocal = null;
        if (isset($localContractors[$localId])) {
            $targetLocal = $localContractors[$localId];
        } else {
            // It might be a list, let's search just in case
            foreach ($localContractors as $k => $v) {
                if ($k === $localId || (isset($v['id']) && $v['id'] === $localId)) {
                    $targetLocal = $v;
                    break;
                }
            }
        }

        if (!$targetLocal) {
             $_SESSION['flash_error'] = "Contractor ID '$localId' not found in $deptCode records.";
             header("Location: contractor_dashboard.php");
             exit;
        }

        // 4. Verification (The Handshake)
        // Check Mobile Number
        $globalMobile = $contractor['mobile'];
        $localMobile = $targetLocal['mobile'] ?? '';

        // Normalize (remove spaces, +91, etc if needed, but assuming exact match for now as per simple system)
        if ($globalMobile === $localMobile) {
            // MATCH!

            // 5. Update Global
            // Remove existing pending link if any
            $newLinks = [];
            foreach ($contractor['linked_departments'] as $link) {
                if ($link['dept_id'] !== $deptCode) {
                    $newLinks[] = $link;
                }
            }
            $newLinks[] = [
                'dept_id' => $deptCode,
                'local_contractor_id' => $localId,
                'status' => 'Verified',
                'linked_at' => date('Y-m-d H:i:s')
            ];
            $contractor['linked_departments'] = $newLinks;
            $globalContractors[$yojakId] = $contractor;
            writeJSON('global/data/contractors.json', $globalContractors);

            // 6. Update Local
            // We need to update the specific contractor in the local file
            // Re-fetch to be safe (though we have it)
            if (isset($localContractors[$localId])) {
                $localContractors[$localId]['linked_yojak_id'] = $yojakId;
            } else {
                 // If it was found via loop
                 foreach ($localContractors as $k => $v) {
                    if ($k === $localId || (isset($v['id']) && $v['id'] === $localId)) {
                        $localContractors[$k]['linked_yojak_id'] = $yojakId;
                        break;
                    }
                }
            }
            writeJSON($localContractorsFile, $localContractors);

            $_SESSION['flash_success'] = "Success! Account verified and linked to $deptCode.";

        } else {
            // MISMATCH
            $_SESSION['flash_error'] = "Verification Failed: Mobile number mismatch. Local record has different mobile. Contact Department.";
        }

    } else {
        $_SESSION['flash_error'] = "Please fill all fields.";
    }
}

header("Location: contractor_dashboard.php");
exit;
