<?php
session_start();
require_once 'functions.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor' || !isset($_SESSION['yojak_id'])) {
    header("Location: contractor_login.php");
    exit;
}

$yojakId = $_SESSION['yojak_id'];
$successMsg = $_SESSION['flash_success'] ?? '';
$errorMsg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Load Contractor Data
$contractors = readJSON('global/data/contractors.json') ?? [];
$contractor = $contractors[$yojakId] ?? null;

if (!$contractor) {
    session_destroy();
    header("Location: contractor_login.php");
    exit;
}

// Calculate Profile Strength
$profile = $contractor['profile'];
$fields = ['company_name', 'owner_name', 'email', 'gst_no', 'pan_no', 'address'];
$filled = 0;
foreach ($fields as $f) {
    if (!empty($profile[$f])) $filled++;
}
if (!empty($profile['bank_details']['ac_no']) && !empty($profile['bank_details']['ifsc'])) {
    $filled++;
}

// Add Vault Score to Profile Strength
// Calculate Vault Score
$vaultScore = 0;
$vaultDocs = $contractor['vault'] ?? [];
$cats = [];
foreach ($vaultDocs as $d) $cats[$d['category']] = true;
if (isset($cats['Identity'])) $vaultScore += 1;
if (isset($cats['Financial'])) $vaultScore += 1;
if (isset($cats['Technical'])) $vaultScore += 1;

// Total points: 7 profile fields + 3 vault categories = 10 total points roughly?
// Or just weight it.
// Original: 7 fields. New: +3 points.
$totalPoints = count($fields) + 1 + 3;
$currentPoints = $filled + $vaultScore;

$strength = round(($currentPoints / $totalPoints) * 100);

// Get Linked Departments
$linkedDepts = $contractor['linked_departments'] ?? [];

// Get Departments List for Dropdown
$allDepts = getAllDepartments();

// Calculate Quick Stats
$stats = [
    'work_orders' => 0,
    'tenders' => 0
];
foreach ($linkedDepts as $link) {
    if (($link['status'] ?? '') === 'Verified') {
        $dId = $link['dept_id'];
        $localCId = $link['local_contractor_id'];

        $tenders = readJSON("departments/$dId/data/tenders.json") ?? [];
        foreach ($tenders as $t) {
            if (isset($t['participants']) && is_array($t['participants'])) {
                foreach ($t['participants'] as $p) {
                    if ((is_array($p) && ($p['id']??'') === $localCId) || $p === $localCId) {
                        $stats['tenders']++;
                        break;
                    }
                }
            }
        }

        $wos = readJSON("departments/$dId/data/work_orders.json") ?? [];
        foreach ($wos as $wo) {
            if (($wo['contractor_id'] ?? '') === $localCId && ($wo['status'] ?? '') === 'Issued') {
                $stats['work_orders']++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contractor Dashboard - Yojak</title>
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
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .welcome-card h1 { margin: 0 0 10px 0; }
        .welcome-card p { margin: 0; opacity: 0.9; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .card h3 { margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }

        .progress-container {
            background-color: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            width: 100%;
            margin: 15px 0;
            overflow: hidden;
        }
        .progress-bar {
            background-color: #28a745;
            height: 100%;
            text-align: center;
            line-height: 20px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: width 0.5s;
        }

        .stats-grid {
            display: flex;
            gap: 15px;
        }
        .stat-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-val { font-size: 24px; font-weight: bold; color: var(--primary-color); display: block; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { opacity: 0.9; }

        .dept-list { list-style: none; padding: 0; margin: 0; }
        .dept-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .dept-item:last-child { border-bottom: none; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .badge-verified { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-area">
            <h2>Yojak</h2>
            <div style="font-size: 12px; color: #777;">Contractor Platform</div>
        </div>
        <ul class="nav-links">
            <li><a href="contractor_dashboard.php" class="active">Home</a></li>
            <li><a href="contractor_profile.php">My Profile</a></li>
            <li><a href="#">Linked Depts</a></li>
            <li><a href="contractor_vault.php">Documents</a></li>
            <li style="margin-top: auto; border-top: 1px solid #eee;"><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
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

        <div class="welcome-card">
            <h1>Welcome, <?php echo htmlspecialchars($contractor['profile']['company_name']); ?></h1>
            <p>Yojak ID: <?php echo htmlspecialchars($yojakId); ?> | Tier: <?php echo htmlspecialchars($contractor['tier']); ?></p>
        </div>

        <div class="dashboard-grid">
            <!-- Profile Strength -->
            <div class="card">
                <h3>Profile Strength</h3>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $strength; ?>%"><?php echo $strength; ?>%</div>
                </div>
                <p style="font-size: 14px; color: #666;">
                    <?php if ($strength < 80): ?>
                        Upload Vault documents and complete profile to reach 80%.
                    <?php else: ?>
                        Your profile is robust!
                    <?php endif; ?>
                </p>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <a href="contractor_profile.php" style="color: var(--primary-color); font-size: 14px; text-decoration: none; font-weight: 500;">Edit Profile</a>
                    <span style="color: #ddd;">|</span>
                    <a href="contractor_vault.php" style="color: var(--primary-color); font-size: 14px; text-decoration: none; font-weight: 500;">Go to Vault</a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <h3>Quick Stats</h3>
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="stat-val"><?php echo $stats['work_orders']; ?></span>
                        <span class="stat-label">Active W.O.</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-val"><?php echo $stats['tenders']; ?></span>
                        <span class="stat-label">Tenders</span>
                    </div>
                </div>
            </div>

            <!-- Link Department -->
            <div class="card">
                <h3>Link Department</h3>
                <form method="POST" action="link_department_logic.php">
                    <div class="form-group">
                        <label>Department Code</label>
                        <select name="dept_code" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($allDepts as $dept): ?>
                                <?php if (isset($dept['id']) && isset($dept['name'])): ?>
                                    <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['id']); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Your Local ID (e.g. CON-DWS-005)</label>
                        <input type="text" name="local_id" class="form-control" required placeholder="Found in your contract documents">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Verify & Link</button>
                </form>
            </div>

            <!-- Linked Depts List -->
            <div class="card" style="grid-column: 1 / -1;">
                <h3>Linked Departments</h3>
                <?php if (empty($linkedDepts)): ?>
                    <p style="color: #777;">No departments linked yet.</p>
                <?php else: ?>
                    <ul class="dept-list">
                        <?php foreach ($linkedDepts as $link): ?>
                            <li class="dept-item">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="background: #e3f2fd; color: var(--primary-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                        <?php echo strtoupper(substr($link['dept_id'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: bold;"><?php echo htmlspecialchars($link['dept_id']); ?></div>
                                        <div style="color: #666; font-size: 13px;">Local ID: <?php echo htmlspecialchars($link['local_contractor_id']); ?></div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="badge <?php echo ($link['status'] === 'Verified') ? 'badge-verified' : 'badge-pending'; ?>">
                                        <?php echo htmlspecialchars($link['status']); ?>
                                    </span>
                                    <?php if ($link['status'] === 'Verified'): ?>
                                        <button class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" disabled title="Sync feature coming soon">Sync Data</button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

</body>
</html>
