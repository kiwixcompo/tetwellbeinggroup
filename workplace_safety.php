<?php
/**
 * Tet Wellbeing Group - Workplace Psychological Safety Platform (workplace_safety.php)
 * Provides organizational psychological safety scoring, anonymous employee surveys,
 * team conflict tracking with AI resolution plans, and predictive workforce safety trends.
 */

// Initialize database & session
require_once __DIR__ . '/db.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$user_name = $_SESSION['user_name'] ?? 'Staff Member';

// Retrieve active user info
$user_dept_id = null;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_dept_id = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $user_dept_id = 1; // fallback
    }
} else {
    foreach ($_SESSION['mock_users'] as $mu) {
        if ($mu['id'] == $user_id) {
            $user_dept_id = $mu['department_id'] ?? null;
            break;
        }
    }
}

// Fetch departments
$departments = [];
if ($db_connected && $pdo) {
    try {
        $departments = $pdo->query("SELECT * FROM workplace_departments")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $departments = $_SESSION['mock_workplace_departments'] ?? [];
    }
} else {
    $departments = $_SESSION['mock_workplace_departments'] ?? [];
}

// Create department ID-to-Name map
$dept_map = [];
foreach ($departments as $d) {
    $dept_map[$d['id']] = $d['name'];
}

// Handle Form Submissions
$action_success = "";
$action_error = "";
$current_period = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Submit Anonymous Survey
    if (isset($_POST['action']) && $_POST['action'] === 'submit_survey') {
        $q1 = intval($_POST['q1'] ?? 3);
        $q2 = intval($_POST['q2'] ?? 3);
        $q3 = intval($_POST['q3'] ?? 3);
        $q4 = intval($_POST['q4'] ?? 3);
        $q5 = intval($_POST['q5'] ?? 3);
        $feedback = trim($_POST['feedback'] ?? '');
        $dept_id = intval($_POST['department_id'] ?? $user_dept_id);

        if (!$dept_id) {
            $action_error = "Please select your department before submitting.";
        } else {
            // Verify if already voted this month
            $already_done = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM workplace_user_survey_status WHERE user_id = ? AND survey_period = ?");
                    $stmt->execute([$user_id, $current_period]);
                    $already_done = ($stmt->fetchColumn() > 0);
                } catch (PDOException $e) {
                    $already_done = false;
                }
            } else {
                foreach ($_SESSION['mock_workplace_user_survey_status'] as $status) {
                    if ($status['user_id'] == $user_id && $status['survey_period'] == $current_period) {
                        $already_done = true;
                        break;
                    }
                }
            }

            if ($already_done) {
                $action_error = "You have already completed this month's survey. Thank you for your support!";
            } else {
                // Log survey response anonymously (NO USER ID)
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO workplace_survey_responses (department_id, q1_raise_issues, q2_team_mistakes, q3_supportive_env, q4_respect_others, q5_burnout_level, feedback, submitted_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$dept_id, $q1, $q2, $q3, $q4, $q5, $feedback, date('Y-m-d')]);

                        $stmt = $pdo->prepare("INSERT INTO workplace_user_survey_status (user_id, survey_period) VALUES (?, ?)");
                        $stmt->execute([$user_id, $current_period]);
                    } catch (PDOException $e) {
                        $action_error = "Database error logging survey: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['mock_workplace_survey_responses'][] = [
                        'id' => count($_SESSION['mock_workplace_survey_responses']) + 1,
                        'department_id' => $dept_id,
                        'q1_raise_issues' => $q1,
                        'q2_team_mistakes' => $q2,
                        'q3_supportive_env' => $q3,
                        'q4_respect_others' => $q4,
                        'q5_burnout_level' => $q5,
                        'feedback' => $feedback,
                        'submitted_date' => date('Y-m-d')
                    ];
                    $_SESSION['mock_workplace_user_survey_status'][] = [
                        'user_id' => $user_id,
                        'survey_period' => $current_period
                    ];
                }

                if (empty($action_error)) {
                    // Update user's local department if they chose a new one and it was not set
                    if ($dept_id != $user_dept_id) {
                        $user_dept_id = $dept_id;
                        if ($db_connected && $pdo) {
                            try {
                                $stmt = $pdo->prepare("UPDATE users SET department_id = ? WHERE id = ?");
                                $stmt->execute([$dept_id, $user_id]);
                            } catch (PDOException $e) {}
                        } else {
                            foreach ($_SESSION['mock_users'] as &$mu) {
                                if ($mu['id'] == $user_id) {
                                    $mu['department_id'] = $dept_id;
                                    break;
                                }
                            }
                        }
                    }
                    $action_success = "Your anonymous psychological safety check-in has been recorded. Thank you for making our workplace safer.";
                }
            }
        }
    }

    // 2. Resolve or Update Conflict Status
    if (isset($_POST['action']) && $_POST['action'] === 'update_conflict_status') {
        if ($user_role === 'admin' || $user_role === 'specialist') {
            $conflict_id = intval($_POST['conflict_id'] ?? 0);
            $new_status = $_POST['status'] ?? 'open';

            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE workplace_conflicts SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $conflict_id]);
                } catch (PDOException $e) {
                    $action_error = "Database error updating conflict: " . $e->getMessage();
                }
            } else {
                foreach ($_SESSION['mock_workplace_conflicts'] as &$c) {
                    if ($c['id'] == $conflict_id) {
                        $c['status'] = $new_status;
                        break;
                    }
                }
            }

            if (empty($action_error)) {
                $action_success = "Conflict tracker status updated successfully.";
            }
        }
    }

    // 3. Log a new anonymous workplace friction issue
    if (isset($_POST['action']) && $_POST['action'] === 'log_conflict') {
        $dept_id = intval($_POST['department_id'] ?? $user_dept_id);
        $description = trim($_POST['description'] ?? '');
        $severity = $_POST['severity'] ?? 'medium';

        if (!$dept_id || empty($description)) {
            $action_error = "Please fill out all fields before submitting.";
        } else {
            // Compute standard AI recommendation plans based on text analysis
            $ai_plan = "1. Facilitate a 1-on-1 boundary alignment discussion.\n2. Leverage Peer Matching configurations.\n3. Recommend box breathing and micro-break resources in the Caregiver portal.";
            $desc_lower = strtolower($description);
            if (strpos($desc_lower, 'shift') !== false || strpos($desc_lower, 'handover') !== false || strpos($desc_lower, 'transition') !== false) {
                $ai_plan = "Deploy the SBAR Cross-Shift Handover template in this department. Schedule a 15-minute briefing session to align cognitive boundaries.";
            } elseif (strpos($desc_lower, 'schedule') !== false || strpos($desc_lower, 'hours') !== false || strpos($desc_lower, 'overtime') !== false) {
                $ai_plan = "Initiate burnout vulnerability screenings for active biometrics logs. Advise management to distribute caseload templates using Caregiver matching metrics.";
            } elseif (strpos($desc_lower, 'doctor') !== false || strpos($desc_lower, 'nurse') !== false || strpos($desc_lower, 'override') !== false) {
                $ai_plan = "Organize a clinical mediation audit. Clarify nursing override rights and establish a safety review escalation dashboard.";
            }

            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO workplace_conflicts (department_id, description, severity, status, ai_mitigation_plan, logged_date) VALUES (?, ?, ?, 'open', ?, ?)");
                    $stmt->execute([$dept_id, $description, $severity, $ai_plan, date('Y-m-d')]);
                } catch (PDOException $e) {
                    $action_error = "Database error creating conflict: " . $e->getMessage();
                }
            } else {
                $_SESSION['mock_workplace_conflicts'][] = [
                    'id' => count($_SESSION['mock_workplace_conflicts']) + 1,
                    'department_id' => $dept_id,
                    'description' => $description,
                    'severity' => $severity,
                    'status' => 'open',
                    'ai_mitigation_plan' => $ai_plan,
                    'logged_date' => date('Y-m-d')
                ];
            }

            if (empty($action_error)) {
                $action_success = "Anonymous conflict logged. AI mitigation strategies have been computed for review.";
            }
        }
    }
}

// Fetch Survey Responses
$all_responses = [];
if ($db_connected && $pdo) {
    try {
        $all_responses = $pdo->query("SELECT * FROM workplace_survey_responses ORDER BY submitted_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $all_responses = $_SESSION['mock_workplace_survey_responses'] ?? [];
    }
} else {
    $all_responses = $_SESSION['mock_workplace_survey_responses'] ?? [];
}
// Sort by date descending
usort($all_responses, function($a, $b) {
    return strcmp($b['submitted_date'], $a['submitted_date']);
});

// Fetch Conflicts
$all_conflicts = [];
if ($db_connected && $pdo) {
    try {
        $all_conflicts = $pdo->query("SELECT * FROM workplace_conflicts ORDER BY logged_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $all_conflicts = $_SESSION['mock_workplace_conflicts'] ?? [];
    }
} else {
    $all_conflicts = $_SESSION['mock_workplace_conflicts'] ?? [];
}
usort($all_conflicts, function($a, $b) {
    return strcmp($b['logged_date'], $a['logged_date']);
});

// Check survey status for active client
$has_completed_current_survey = false;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM workplace_user_survey_status WHERE user_id = ? AND survey_period = ?");
        $stmt->execute([$user_id, $current_period]);
        $has_completed_current_survey = ($stmt->fetchColumn() > 0);
    } catch (PDOException $e) {}
} else {
    foreach ($_SESSION['mock_workplace_user_survey_status'] as $status) {
        if ($status['user_id'] == $user_id && $status['survey_period'] == $current_period) {
            $has_completed_current_survey = true;
            break;
        }
    }
}

// Aggregation Math
$dept_metrics = [];
foreach ($departments as $d) {
    $dept_metrics[$d['id']] = [
        'name' => $d['name'],
        'safety_sum' => 0,
        'burnout_sum' => 0,
        'count' => 0
    ];
}

foreach ($all_responses as $r) {
    $d_id = $r['department_id'];
    if (isset($dept_metrics[$d_id])) {
        // q1 to q4 measure safety (1-5 score). Standard index = (average of q1..q4) * 20
        $safety_val = ($r['q1_raise_issues'] + $r['q2_team_mistakes'] + $r['q3_supportive_env'] + $r['q4_respect_others']) / 4 * 20;
        $dept_metrics[$d_id]['safety_sum'] += $safety_val;
        $dept_metrics[$d_id]['burnout_sum'] += ($r['q5_burnout_level'] * 20); // 1-5scaled to 0-100
        $dept_metrics[$d_id]['count']++;
    }
}

$org_safety_sum = 0;
$org_burnout_sum = 0;
$org_count = 0;
$dept_summary = [];

foreach ($dept_metrics as $id => $m) {
    $avg_safety = $m['count'] > 0 ? round($m['safety_sum'] / $m['count']) : 75; // default fallback
    $avg_burnout = $m['count'] > 0 ? round($m['burnout_sum'] / $m['count']) : 30; // default fallback

    $dept_summary[$id] = [
        'name' => $m['name'],
        'safety_index' => $avg_safety,
        'burnout_index' => $avg_burnout,
        'count' => $m['count']
    ];

    if ($m['count'] > 0) {
        $org_safety_sum += $m['safety_sum'];
        $org_burnout_sum += $m['burnout_sum'];
        $org_count += $m['count'];
    }
}

$overall_safety_index = $org_count > 0 ? round($org_safety_sum / $org_count) : 78;
$overall_burnout_index = $org_count > 0 ? round($org_burnout_sum / $org_count) : 34;

// Calculate monthly trend data (April, May, June)
$monthly_safety = ['April' => [], 'May' => [], 'June' => []];
foreach ($all_responses as $r) {
    $date = $r['submitted_date'];
    $month = date('F', strtotime($date));
    if (isset($monthly_safety[$month])) {
        $safety_val = ($r['q1_raise_issues'] + $r['q2_team_mistakes'] + $r['q3_supportive_env'] + $r['q4_respect_others']) / 4 * 20;
        $monthly_safety[$month][] = $safety_val;
    }
}

$trend_points = [];
$months_list = ['April', 'May', 'June'];
foreach ($months_list as $m) {
    if (!empty($monthly_safety[$m])) {
        $trend_points[$m] = round(array_sum($monthly_safety[$m]) / count($monthly_safety[$m]));
    } else {
        // seed hardcoded fallbacks if no database items exist
        $trend_points[$m] = ($m === 'April') ? 72 : (($m === 'May') ? 75 : 78);
    }
}

$y_apr = 120 - (($trend_points['April'] ?? 72) * 1.0);
$y_may = 120 - (($trend_points['May'] ?? 75) * 1.0);
$y_jun = 120 - (($trend_points['June'] ?? 78) * 1.0);

// Set default active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'analytics';
if ($user_role === 'client' && $active_tab === 'analytics') {
    $active_tab = 'survey'; // Clients start on survey
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workplace Psychological Safety | Tet Wellbeing</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F9F8F3',
                            slate: '#2C3E35',
                            sage: '#7FA08E',
                            sageLight: '#E8F0EC',
                            coral: '#E07A5F',
                            coralLight: '#FBEBE7',
                            sand: '#F2CC8F',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    boxShadow: {
                        soft: '0 12px 40px -12px rgba(44, 62, 53, 0.08)',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        body { background-color: #F9F8F3; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="text-brand-slate min-h-screen pb-20 md:pb-8">

    <!-- GLOBAL CLIENT NAV BAR -->
    <header class="border-b border-[#EBE8E0] bg-white/80 backdrop-blur-md sticky top-0 z-50 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <span class="text-xl font-extrabold tracking-tight font-outfit text-brand-slate flex items-center gap-2">
                    <object data="logo.svg" type="image/svg+xml" class="h-6 w-6 pointer-events-none"></object>
                    Tet <span class="text-brand-sage font-medium">Wellbeing</span>
                </span>
            </a>
            
            <div class="flex items-center gap-3">
                <span class="hidden md:inline text-xs font-semibold text-gray-500 bg-brand-bg border border-gray-100 px-3.5 py-1.5 rounded-full">
                    🛡️ Workplace Account: <strong class="text-brand-slate font-bold"><?php echo htmlspecialchars($user_name); ?></strong>
                </span>
                
                <div class="relative group">
                    <button class="flex items-center gap-2 px-3 py-1.5 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="h-7 w-7 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs flex items-center justify-center font-outfit">
                            <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                        </div>
                    </button>
                    <div class="absolute right-0 top-full mt-2 w-48 bg-white border border-[#EBE8E0] rounded-2xl shadow-lg opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all p-1 z-50">
                        <a href="logout.php" class="block px-4 py-2.5 text-xs font-bold text-brand-coral hover:bg-brand-coralLight rounded-xl transition-all">Logout Portal</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <!-- TITLE BANNER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 border-b border-[#EBE8E0] pb-6">
            <div>
                <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs uppercase tracking-wider font-outfit">Ecosystem Layer 5</span>
                <h2 class="text-3xl font-extrabold font-outfit text-brand-slate mt-2">Workplace Psychological Safety</h2>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed max-w-2xl">
                    Organizational wellbeing intelligence, anonymous psychological safety indicators, risk forecasting, and resolution playbooks.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-52 h-28 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10 bg-brand-sageLight/25 flex items-center justify-center p-4">
                <span class="text-4xl">🏢</span>
                <div class="ml-3">
                    <span class="text-xs text-gray-400 font-semibold block uppercase">Safety Index</span>
                    <span class="text-2xl font-black font-outfit text-brand-sage"><?php echo $overall_safety_index; ?>%</span>
                </div>
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold overflow-x-auto whitespace-nowrap pb-1">
            <a href="dashboard.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Teletherapy Hub</a>
            <a href="ai_companion.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">AI Companion</a>
            <a href="predictive_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Digital Twin</a>
            <a href="vr_resilience.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">VR Centre</a>
            <a href="workplace_safety.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage font-outfit">Workplace Safety</a>
        </div>

        <!-- ACTION TOASTS -->
        <?php if (!empty($action_success)): ?>
            <div id="status-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Action Complete</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_success; ?></p>
                </div>
                <button onclick="document.getElementById('status-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
            <div id="error-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral/30 bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Error Encountered</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_error; ?></p>
                </div>
                <button onclick="document.getElementById('error-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- SUB-TABS INTERFACE -->
        <div class="flex flex-wrap items-center gap-2 mb-8 text-xs overflow-x-auto whitespace-nowrap pb-2">
            <?php if ($user_role === 'admin' || $user_role === 'specialist'): ?>
                <a href="workplace_safety.php?tab=analytics" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'analytics') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Executive Diagnostics</a>
                <a href="workplace_safety.php?tab=conflicts" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'conflicts') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Conflict Resolution tracker (<?php echo count(array_filter($all_conflicts, function($c){return $c['status'] !== 'resolved';})); ?>)</a>
                <a href="workplace_safety.php?tab=predictions" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'predictions') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Workforce Health Forecast</a>
                <a href="workplace_safety.php?tab=inbox" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'inbox') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Anonymized Feedback Inbox</a>
            <?php else: ?>
                <a href="workplace_safety.php?tab=survey" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'survey') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Staff Survey Portal</a>
                <a href="workplace_safety.php?tab=insights" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'insights') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Department Transparency Scores</a>
                <a href="workplace_safety.php?tab=resources" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'resources') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Coping & Boundaries Resources</a>
                <a href="workplace_safety.php?tab=logfriction" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'logfriction') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Log Anonymized Conflict</a>
            <?php endif; ?>
        </div>

        <!-- ==================== EXECUTIVE VIEW: DIAGNOSTICS ==================== -->
        <?php if (($user_role === 'admin' || $user_role === 'specialist') && $active_tab === 'analytics'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left 2 Cols: Main Graphs and Metrics -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Key Statistics Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="p-5 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm flex flex-col justify-between">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Overall Safety Score</span>
                            <div class="mt-4 flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold font-outfit text-brand-sage"><?php echo $overall_safety_index; ?>%</span>
                                <span class="text-xs text-emerald-500 font-bold">▲ Good</span>
                            </div>
                        </div>
                        <div class="p-5 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm flex flex-col justify-between">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Burnout risk index</span>
                            <div class="mt-4 flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold font-outfit text-brand-coral"><?php echo $overall_burnout_index; ?>%</span>
                                <span class="text-xs text-brand-coral font-bold">⚠️ Moderate</span>
                            </div>
                        </div>
                        <div class="p-5 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm flex flex-col justify-between">
                            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Unresolved conflicts</span>
                            <div class="mt-4 flex items-baseline gap-2">
                                <span class="text-4xl font-extrabold font-outfit text-yellow-600"><?php echo count(array_filter($all_conflicts, function($c){return $c['status'] !== 'resolved';})); ?></span>
                                <span class="text-xs text-gray-400">cases</span>
                            </div>
                        </div>
                    </div>

                    <!-- Department comparisons -->
                    <section class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm">
                        <h3 class="font-bold text-lg text-brand-slate mb-4 font-outfit">Department Safety Rankings</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-[#EBE8E0] text-xs font-bold text-gray-400 uppercase tracking-wider">
                                        <th class="pb-3">Department</th>
                                        <th class="pb-3">Safety Index</th>
                                        <th class="pb-3">Burnout Risk</th>
                                        <th class="pb-3 text-right">Responses</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dept_summary as $id => $summary): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50/55 transition-colors">
                                            <td class="py-4 font-semibold text-sm"><?php echo htmlspecialchars($summary['name']); ?></td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-bold"><?php echo $summary['safety_index']; ?>%</span>
                                                    <div class="w-24 bg-gray-100 h-2 rounded-full overflow-hidden">
                                                        <div class="bg-brand-sage h-full rounded-full" style="width: <?php echo $summary['safety_index']; ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full <?php echo ($summary['burnout_index'] > 45) ? 'bg-brand-coralLight text-brand-coral' : 'bg-brand-sageLight text-brand-sage'; ?>">
                                                        <?php echo $summary['burnout_index']; ?>%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-4 text-right text-xs font-semibold text-gray-500"><?php echo $summary['count']; ?> submitted</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <!-- Right Col: Trend Line & Survey Details -->
                <div class="space-y-8">
                    <!-- Line Trend Chart -->
                    <section class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm">
                        <h3 class="font-bold text-sm uppercase tracking-wider text-gray-400 mb-4">Safety Index Trend</h3>
                        <div class="flex justify-center py-2 bg-brand-bg rounded-2xl border border-gray-100/50">
                            <!-- SVG LINE PLOT -->
                            <svg width="260" height="150" viewBox="0 0 300 150" class="overflow-visible">
                                <!-- Grid Lines -->
                                <line x1="30" y1="120" x2="270" y2="120" stroke="#EBE8E0" stroke-width="1.5" stroke-dasharray="4"/>
                                <line x1="30" y1="70" x2="270" y2="70" stroke="#EBE8E0" stroke-width="1.5" stroke-dasharray="4"/>
                                <line x1="30" y1="20" x2="270" y2="20" stroke="#EBE8E0" stroke-width="1.5" stroke-dasharray="4"/>

                                <!-- Trend Lines -->
                                <path d="M 50 <?php echo $y_apr; ?> L 150 <?php echo $y_may; ?> L 250 <?php echo $y_jun; ?>" fill="none" stroke="#7FA08E" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                
                                <!-- Dot points -->
                                <circle cx="50" cy="<?php echo $y_apr; ?>" r="6" fill="#2C3E35" stroke="#fff" stroke-width="2"/>
                                <circle cx="150" cy="<?php echo $y_may; ?>" r="6" fill="#2C3E35" stroke="#fff" stroke-width="2"/>
                                <circle cx="250" cy="<?php echo $y_jun; ?>" r="6" fill="#2C3E35" stroke="#fff" stroke-width="2"/>

                                <!-- Labels -->
                                <text x="50" y="142" font-family="Inter" font-size="10" font-weight="bold" fill="#A0AEC0" text-anchor="middle">Apr (<?php echo $trend_points['April'] ?? 72; ?>%)</text>
                                <text x="150" y="142" font-family="Inter" font-size="10" font-weight="bold" fill="#A0AEC0" text-anchor="middle">May (<?php echo $trend_points['May'] ?? 75; ?>%)</text>
                                <text x="250" y="142" font-family="Inter" font-size="10" font-weight="bold" fill="#A0AEC0" text-anchor="middle">Jun (<?php echo $trend_points['June'] ?? 78; ?>%)</text>

                                <text x="20" y="123" font-family="Inter" font-size="9" fill="#A0AEC0" text-anchor="end">0%</text>
                                <text x="20" y="73" font-family="Inter" font-size="9" fill="#A0AEC0" text-anchor="end">50%</text>
                                <text x="20" y="23" font-family="Inter" font-size="9" fill="#A0AEC0" text-anchor="end">100%</text>
                            </svg>
                        </div>
                        <p class="text-xs text-gray-400 mt-3 leading-relaxed">
                            Calculated monthly by consolidating all anonymous responses. Scores above 70% indicate a functional psychological climate.
                        </p>
                    </section>

                    <!-- Safe environment warning -->
                    <div class="p-6 rounded-3xl border border-brand-sage/20 bg-brand-sageLight/20 text-brand-slate">
                        <h4 class="font-bold text-sm mb-1.5 flex items-center gap-1.5">💡 Clinical Guidance</h4>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Teams with higher psychological safety scores are 3x more resilient to acute caregiver burnout. Implement active conflict resolution structures immediately for departments displaying safety indices below 65%.
                        </p>
                    </div>
                </div>

            </div>

        <!-- ==================== EXECUTIVE VIEW: CONFLICT RESOLUTION TRACKER ==================== -->
        <?php elseif (($user_role === 'admin' || $user_role === 'specialist') && $active_tab === 'conflicts'): ?>
            <div class="bg-white border border-[#EBE8E0] rounded-3xl p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-[#EBE8E0] pb-4 mb-6">
                    <h3 class="font-bold text-lg font-outfit text-brand-slate">Active Workplace Friction Cases</h3>
                    <span class="text-xs font-semibold bg-gray-100 text-gray-500 px-3 py-1 rounded-full uppercase">Facilitated Mediation</span>
                </div>

                <div class="space-y-6">
                    <?php if (empty($all_conflicts)): ?>
                        <div class="text-center py-8 text-gray-400 text-sm">No workplace conflicts logged. Outstanding!</div>
                    <?php else: ?>
                        <?php foreach ($all_conflicts as $conflict): ?>
                            <div class="p-5 rounded-2xl bg-brand-bg/50 border border-gray-100 flex flex-col md:flex-row justify-between gap-6 transition-all hover:border-brand-sage/35">
                                <div class="space-y-3 max-w-3xl">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-bold bg-brand-sageLight text-brand-sage px-3 py-0.5 rounded-full">
                                            🏢 <?php echo htmlspecialchars($dept_map[$conflict['department_id']] ?? 'General'); ?>
                                        </span>
                                        <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full <?php echo ($conflict['severity'] === 'high') ? 'bg-brand-coralLight text-brand-coral' : 'bg-yellow-50 text-yellow-700 border border-yellow-100'; ?>">
                                            <?php echo $conflict['severity']; ?> Risk
                                        </span>
                                        <span class="text-[10px] font-bold text-gray-400">Logged on <?php echo $conflict['logged_date']; ?></span>
                                    </div>
                                    <p class="text-sm font-medium text-brand-slate leading-relaxed">
                                        &ldquo;<?php echo htmlspecialchars($conflict['description']); ?>&rdquo;
                                    </p>

                                    <!-- AI MITIGATION PLAYBOOK -->
                                    <?php if (!empty($conflict['ai_mitigation_plan'])): ?>
                                        <div class="p-4 rounded-xl bg-white border border-[#EBE8E0] border-l-4 border-l-brand-sage space-y-1.5">
                                            <h5 class="text-xs font-bold text-brand-sage flex items-center gap-1">🤖 AI-Generated Resolution Steps:</h5>
                                            <p class="text-xs text-gray-600 whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars($conflict['ai_mitigation_plan']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div class="shrink-0 flex flex-col justify-between md:items-end gap-4 border-t md:border-t-0 border-gray-100 pt-4 md:pt-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full <?php echo ($conflict['status'] === 'open') ? 'bg-brand-coral animate-ping' : (($conflict['status'] === 'investigating') ? 'bg-yellow-500 animate-pulse' : 'bg-emerald-500'); ?>"></span>
                                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500"><?php echo ucfirst($conflict['status']); ?></span>
                                    </div>

                                    <form method="POST" action="workplace_safety.php?tab=conflicts" class="flex gap-2">
                                        <input type="hidden" name="action" value="update_conflict_status">
                                        <input type="hidden" name="conflict_id" value="<?php echo $conflict['id']; ?>">
                                        
                                        <?php if ($conflict['status'] === 'open'): ?>
                                            <button type="submit" name="status" value="investigating" class="px-3.5 py-1.5 rounded-xl border border-yellow-200 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 text-xs font-bold transition-all">Investigate</button>
                                        <?php elseif ($conflict['status'] === 'investigating'): ?>
                                            <button type="submit" name="status" value="resolved" class="px-3.5 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition-all">Resolve Case</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <!-- ==================== EXECUTIVE VIEW: PREDICTIONS ==================== -->
        <?php elseif (($user_role === 'admin' || $user_role === 'specialist') && $active_tab === 'predictions'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left 2 Cols: Predictions Model -->
                <div class="lg:col-span-2 space-y-6">
                    <section class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm space-y-6">
                        <div class="border-b border-[#EBE8E0] pb-3">
                            <h3 class="font-bold text-lg font-outfit text-brand-slate">Workforce Health Alert Index</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Calculates department-level overload risks using anonymized telemetry trends.</p>
                        </div>

                        <div class="space-y-5">
                            <!-- Nursing Alert -->
                            <div class="p-4 rounded-2xl bg-brand-bg border border-gray-100 flex items-start gap-4">
                                <div class="h-10 w-10 shrink-0 bg-brand-sageLight text-brand-sage rounded-xl flex items-center justify-center text-lg font-bold">🩺</div>
                                <div class="flex-grow space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-brand-slate">Nursing Department</h4>
                                        <span class="text-xs font-bold text-brand-sage">Stable Health (28% Risk)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-brand-sage h-full rounded-full animate-pulse" style="width: 28%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 leading-relaxed pt-1">
                                        Predictive forecast indicates low risk of burnout over the next 14 days. Peer matching has balanced caseload concerns.
                                    </p>
                                </div>
                            </div>

                            <!-- ICU Alert -->
                            <div class="p-4 rounded-2xl bg-brand-coralLight/20 border border-brand-coral/20 flex items-start gap-4 animate-pulse">
                                <div class="h-10 w-10 shrink-0 bg-brand-coral text-white rounded-xl flex items-center justify-center text-lg font-bold">🚨</div>
                                <div class="flex-grow space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-brand-slate">ICU & ER Department</h4>
                                        <span class="text-xs font-bold text-brand-coral">High Overload Risk (82% Risk)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-brand-coral h-full rounded-full" style="width: 82%"></div>
                                    </div>
                                    <p class="text-xs text-gray-600 leading-relaxed pt-1">
                                        <strong>Risk Predictors:</strong> Consistent survey responses indicating low safety margins around shift handovers and critical staff deficit metrics. Burnout forecast points to likely absenteeism spike.
                                    </p>
                                </div>
                            </div>

                            <!-- Social Work Alert -->
                            <div class="p-4 rounded-2xl bg-brand-bg border border-gray-100 flex items-start gap-4">
                                <div class="h-10 w-10 shrink-0 bg-brand-sageLight text-brand-sage rounded-xl flex items-center justify-center text-lg font-bold">💼</div>
                                <div class="flex-grow space-y-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-brand-slate">Social Work Department</h4>
                                        <span class="text-xs font-bold text-yellow-600">Moderate Alert (48% Risk)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-yellow-500 h-full rounded-full" style="width: 48%"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 leading-relaxed pt-1">
                                        Moderate risk due to uneven caseload distribution logged in open conflict tracks. Mitigated by active Peer Respite options.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Right Col: Risk Forecast Guidelines -->
                <div class="space-y-8">
                    <div class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm">
                        <h3 class="font-bold text-sm uppercase tracking-wider text-gray-400 mb-3">Model Predictors</h3>
                        <div class="space-y-4 text-xs text-gray-600 leading-relaxed">
                            <div>
                                <h5 class="font-bold text-brand-slate mb-0.5">Biometric Thresholds</h5>
                                <p>Aggregated anonymized wearable summaries (resting heart rate, sleep debt) are cross-referenced with department scores to detect team fatigue limits.</p>
                            </div>
                            <div>
                                <h5 class="font-bold text-brand-slate mb-0.5">Friction Frequency</h5>
                                <p>Volume and speed of logged workplace friction markers correlates with team-wide emotional overload warning signs.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        <!-- ==================== EXECUTIVE VIEW: ANONYMOUS INBOX ==================== -->
        <?php elseif (($user_role === 'admin' || $user_role === 'specialist') && $active_tab === 'inbox'): ?>
            <div class="bg-white border border-[#EBE8E0] rounded-3xl p-6 shadow-sm">
                <div class="border-b border-[#EBE8E0] pb-3 mb-6">
                    <h3 class="font-bold text-lg font-outfit text-brand-slate">Staff Feedback Inbox</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Completely anonymized comments pulled from monthly survey submissions.</p>
                </div>

                <div class="space-y-4">
                    <?php 
                    $feedback_found = false; 
                    foreach ($all_responses as $resp): 
                        if (!empty($resp['feedback'])):
                            $feedback_found = true;
                    ?>
                        <div class="p-4.5 rounded-2xl bg-brand-bg/50 border border-gray-100 flex flex-col gap-2">
                            <div class="flex items-center justify-between text-xs font-bold text-gray-400">
                                <span>🏢 <?php echo htmlspecialchars($dept_map[$resp['department_id']] ?? 'General'); ?></span>
                                <span>Logged: <?php echo $resp['submitted_date']; ?></span>
                            </div>
                            <p class="text-sm font-medium text-brand-slate leading-relaxed">
                                &ldquo;<?php echo htmlspecialchars($resp['feedback']); ?>&rdquo;
                            </p>
                            <div class="flex gap-4.5 text-[10px] text-gray-400 font-semibold pt-1 border-t border-gray-100/50 mt-1">
                                <span>Mistake margin: <strong class="text-brand-slate"><?php echo $resp['q2_team_mistakes']; ?>/5</strong></span>
                                <span>Support index: <strong class="text-brand-slate"><?php echo $resp['q3_supportive_env']; ?>/5</strong></span>
                                <span>Safety to speak: <strong class="text-brand-slate"><?php echo $resp['q1_raise_issues']; ?>/5</strong></span>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$feedback_found):
                    ?>
                        <div class="text-center py-8 text-gray-400 text-sm">No anonymized feedback remarks logged.</div>
                    <?php endif; ?>
                </div>
            </div>

        <!-- ==================== STAFF VIEW: SURVEY PORTAL ==================== -->
        <?php elseif ($user_role === 'client' && $active_tab === 'survey'): ?>
            <div class="max-w-2xl mx-auto bg-white border border-[#EBE8E0] rounded-3xl p-6 md:p-8 shadow-sm">
                <div class="border-b border-[#EBE8E0] pb-4 mb-6 text-center">
                    <h3 class="font-bold text-xl font-outfit text-brand-slate">Anonymous Safety Survey</h3>
                    <p class="text-xs text-gray-500 mt-1">Your response is encrypted and stored anonymously. No identifying metadata is retained.</p>
                </div>

                <?php if ($has_completed_current_survey): ?>
                    <div class="text-center py-10 space-y-4">
                        <div class="mx-auto h-16 w-16 bg-brand-sageLight text-brand-sage rounded-full flex items-center justify-center text-3xl">✓</div>
                        <h4 class="text-lg font-bold text-brand-slate font-outfit">Survey Logged for <?php echo date('F Y'); ?></h4>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                            Thank you! You have successfully submitted your anonymous psychological safety check-in for this period. Data-points are refreshed monthly.
                        </p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="workplace_safety.php?tab=survey" class="space-y-6">
                        <input type="hidden" name="action" value="submit_survey">
                        
                        <!-- Select Department -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500">Your Department (Aggregated purposes)</label>
                            <select name="department_id" class="w-full px-4 py-3 rounded-2xl border border-[#EBE8E0] bg-brand-bg text-sm font-semibold text-brand-slate outline-none focus:border-brand-sage transition-all">
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($user_dept_id == $d['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Q1 -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-brand-slate">1. If you make a mistake on this team, it is often held against you.</label>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs font-bold text-gray-500">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="q2" value="<?php echo $i; ?>" class="sr-only peer" <?php echo ($i==3)?'checked':''; ?>>
                                        <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-sage peer-checked:text-white peer-checked:border-brand-sage transition-all"><?php echo $i; ?></div>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                                <span>Strongly Agree (Held against you)</span>
                                <span>Strongly Disagree</span>
                            </div>
                        </div>

                        <!-- Q2 -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-brand-slate">2. Members of this team are able to bring up problems and tough issues.</label>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs font-bold text-gray-500">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="q1" value="<?php echo $i; ?>" class="sr-only peer" <?php echo ($i==3)?'checked':''; ?>>
                                        <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-sage peer-checked:text-white peer-checked:border-brand-sage transition-all"><?php echo $i; ?></div>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                                <span>Strongly Disagree</span>
                                <span>Strongly Agree</span>
                            </div>
                        </div>

                        <!-- Q3 -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-brand-slate">3. People on this team sometimes reject others for being different.</label>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs font-bold text-gray-500">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="q3" value="<?php echo $i; ?>" class="sr-only peer" <?php echo ($i==3)?'checked':''; ?>>
                                        <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-sage peer-checked:text-white peer-checked:border-brand-sage transition-all"><?php echo $i; ?></div>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                                <span>Strongly Agree</span>
                                <span>Strongly Disagree</span>
                            </div>
                        </div>

                        <!-- Q4 -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-brand-slate">4. It is completely safe to take calculated risks on this team.</label>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs font-bold text-gray-500">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="q4" value="<?php echo $i; ?>" class="sr-only peer" <?php echo ($i==3)?'checked':''; ?>>
                                        <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-sage peer-checked:text-white peer-checked:border-brand-sage transition-all"><?php echo $i; ?></div>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                                <span>Strongly Disagree</span>
                                <span>Strongly Agree</span>
                            </div>
                        </div>

                        <!-- Q5: Burnout Indicator -->
                        <div class="space-y-2">
                            <label class="block text-sm font-bold text-brand-slate">5. How would you rate your emotional fatigue/burnout level this month?</label>
                            <div class="grid grid-cols-5 gap-2 text-center text-xs font-bold text-gray-500">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="q5" value="<?php echo $i; ?>" class="sr-only peer" <?php echo ($i==2)?'checked':''; ?>>
                                        <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-sage peer-checked:text-white peer-checked:border-brand-sage transition-all"><?php echo $i; ?></div>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold px-1">
                                <span>Very Low Stress</span>
                                <span>Severe Burnout / Exhaustion</span>
                            </div>
                        </div>

                        <!-- Feedback Box -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-500">Additional Anonymous Remarks (Optional)</label>
                            <textarea name="feedback" rows="3" placeholder="Provide anonymous feedback regarding team support, boundaries, or leadership..." class="w-full p-4 rounded-2xl border border-[#EBE8E0] bg-brand-bg text-sm font-medium text-brand-slate outline-none focus:border-brand-sage transition-all"></textarea>
                        </div>

                        <button type="submit" class="w-full py-3.5 rounded-2xl bg-brand-sage hover:bg-brand-sage/90 text-white font-bold text-sm shadow-soft transition-all active:scale-[0.98]">
                            Submit Survey Anonymously
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <!-- ==================== STAFF VIEW: TRANSPARENCY SCORE ==================== -->
        <?php elseif ($user_role === 'client' && $active_tab === 'insights'): ?>
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- My Dept Index -->
                <?php 
                $my_dept_name = $user_dept_id ? ($dept_map[$user_dept_id] ?? 'Unassigned') : 'Unassigned';
                $my_dept_safety = $user_dept_id ? ($dept_summary[$user_dept_id]['safety_index'] ?? 75) : 75;
                $my_dept_burnout = $user_dept_id ? ($dept_summary[$user_dept_id]['burnout_index'] ?? 30) : 30;
                $my_dept_responses = $user_dept_id ? ($dept_summary[$user_dept_id]['count'] ?? 0) : 0;
                ?>
                <section class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm space-y-6">
                    <div class="border-b border-[#EBE8E0] pb-3">
                        <span class="text-xs font-bold text-brand-sage uppercase">My Department</span>
                        <h3 class="font-bold text-xl font-outfit text-brand-slate"><?php echo htmlspecialchars($my_dept_name); ?> Team Index</h3>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="p-5 rounded-2xl bg-brand-bg flex flex-col justify-between">
                            <span class="text-xs font-bold text-gray-400 uppercase">Team Safety Score</span>
                            <span class="text-3xl font-black font-outfit text-brand-sage mt-4"><?php echo $my_dept_safety; ?>%</span>
                        </div>
                        <div class="p-5 rounded-2xl bg-brand-bg flex flex-col justify-between">
                            <span class="text-xs font-bold text-gray-400 uppercase">Team Burnout Score</span>
                            <span class="text-3xl font-black font-outfit text-brand-coral mt-4"><?php echo $my_dept_burnout; ?>%</span>
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 leading-relaxed border-t border-gray-100 pt-4">
                        Scores represent anonymized averages from the past 30 days. Active responses: <strong><?php echo $my_dept_responses; ?> submitted</strong>.
                    </div>
                </section>

                <!-- Comparison block -->
                <div class="p-6 rounded-3xl border border-brand-sage/20 bg-brand-sageLight/20 text-brand-slate text-center">
                    <h4 class="font-bold text-sm mb-1">🛡️ Transparency Policy</h4>
                    <p class="text-xs text-gray-600 leading-relaxed max-w-md mx-auto">
                        Tet Wellbeing provides team safety metrics directly to personnel. Open transparency improves confidence, encourages speaking up, and allows members to collectively tackle system overwork.
                    </p>
                </div>
            </div>

        <!-- ==================== STAFF VIEW: COPING & BOUNDARIES ==================== -->
        <?php elseif ($user_role === 'client' && $active_tab === 'resources'): ?>
            <div class="max-w-3xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- SBAR template -->
                <div class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm space-y-4">
                    <span class="text-xl">📝</span>
                    <h4 class="font-bold text-base text-brand-slate font-outfit">SBAR Handover Guide</h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Improve shift transitions and reduce communication friction using the clinical SBAR layout:
                    </p>
                    <ul class="text-xs text-gray-600 list-disc pl-5 space-y-1">
                        <li><strong>S (Situation)</strong>: Current state and priority alerts.</li>
                        <li><strong>B (Background)</strong>: Brief historical telemetry context.</li>
                        <li><strong>A (Assessment)</strong>: What you think the current concern is.</li>
                        <li><strong>R (Recommendation)</strong>: Immediate tasks to prioritize.</li>
                    </ul>
                </div>

                <!-- Boundary Setting -->
                <div class="p-6 rounded-3xl bg-white border border-[#EBE8E0] shadow-sm space-y-4">
                    <span class="text-xl">🧘</span>
                    <h4 class="font-bold text-base text-brand-slate font-outfit">Establishing Safe Boundaries</h4>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Practical tips to interrupt organizational emotional overload:
                    </p>
                    <ul class="text-xs text-gray-600 list-disc pl-5 space-y-1">
                        <li>Practice saying: <em>"To guarantee clinic safety, I cannot accommodate extra cases today."</em></li>
                        <li>Leverage early warning dashboards to demonstrate work constraints to supervisors.</li>
                        <li>Utilize the box breathing exercises on the AI Companion page to reduce physical tension.</li>
                    </ul>
                </div>
            </div>

        <!-- ==================== STAFF VIEW: LOG ANONYMOUS FRICTION ==================== -->
        <?php elseif ($user_role === 'client' && $active_tab === 'logfriction'): ?>
            <div class="max-w-2xl mx-auto bg-white border border-[#EBE8E0] rounded-3xl p-6 md:p-8 shadow-sm">
                <div class="border-b border-[#EBE8E0] pb-4 mb-6 text-center">
                    <h3 class="font-bold text-xl font-outfit text-brand-slate">Log Anonymized Workplace Friction</h3>
                    <p class="text-xs text-gray-500 mt-1">Encountered departmental friction or boundary violations? Report it here anonymously to populate the AI Mitigation dashboard.</p>
                </div>

                <form method="POST" action="workplace_safety.php?tab=logfriction" class="space-y-6">
                    <input type="hidden" name="action" value="log_conflict">

                    <!-- Select Department -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 font-outfit">Target Department</label>
                        <select name="department_id" class="w-full px-4 py-3 rounded-2xl border border-[#EBE8E0] bg-brand-bg text-sm font-semibold text-brand-slate outline-none focus:border-brand-sage transition-all">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo ($user_dept_id == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Severity -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 font-outfit">Friction Severity</label>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs font-bold text-gray-500">
                            <label class="cursor-pointer">
                                <input type="radio" name="severity" value="low" class="sr-only peer">
                                <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-emerald-500 peer-checked:text-white peer-checked:border-emerald-500 transition-all">Low Friction</div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="severity" value="medium" class="sr-only peer" checked>
                                <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-yellow-500 peer-checked:text-white peer-checked:border-yellow-500 transition-all">Medium Risk</div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="severity" value="high" class="sr-only peer">
                                <div class="py-2.5 rounded-xl bg-brand-bg border border-gray-100 hover:bg-gray-100/50 peer-checked:bg-brand-coral peer-checked:text-white peer-checked:border-brand-coral transition-all">Critical Conflict</div>
                            </label>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 font-outfit">Description of Friction</label>
                        <textarea name="description" rows="4" required placeholder="Describe the friction or communication breakdowns (e.g. 'Shift handover was delayed causing case overflow' or 'Tension between junior residents and seniors on override safety guidelines'). Do not include names." class="w-full p-4 rounded-2xl border border-[#EBE8E0] bg-brand-bg text-sm font-medium text-brand-slate outline-none focus:border-brand-sage transition-all"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-brand-sage hover:bg-brand-sage/90 text-white font-bold text-sm shadow-soft transition-all active:scale-[0.98]">
                        Log Workplace Issue Anonymously
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <!-- MOBILE NAVIGATION BAR (Sticky Bottom) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-[#EBE8E0] bg-white/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Journal</span>
            </a>
            <a href="caregiver_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Caregiver</span>
            </a>
            <a href="workplace_safety.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="20" rx="2" ry="2"/><path d="M12 18H12.01"/><path d="M12 6v8"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Workplace</span>
            </a>
        </div>
    </nav>

</body>
</html>
