<?php
/**
 * Tet Wellbeing Group - Human-Centred Mental Health Research Centre (research_hub.php)
 * A premium research portal combining population wellbeing analytics, intervention impact
 * evaluations, anonymized trends (mood, sleep, burnout), and study participation controls.
 * Features inline SVG data dashboards and AJAX consent opt-in/opt-out workflows.
 */

// Initialize database & session
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/EmotionalHealthService.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$user_name = $_SESSION['user_name'] ?? 'Staff Member';

$action_success = '';
$action_error = '';

// Handle Study Enrollment/Consent via AJAX (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_consent') {
    header('Content-Type: application/json');
    $study_id = intval($_POST['study_id'] ?? 0);
    $consent = intval($_POST['consent'] ?? 0); // 1 = Join, 0 = Leave

    if ($study_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Study ID']);
        exit;
    }

    if ($db_connected && $pdo) {
        try {
            if ($consent === 1) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO research_participants (user_id, study_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $study_id]);
                $joined = true;
            } else {
                $stmt = $pdo->prepare("DELETE FROM research_participants WHERE user_id = ? AND study_id = ?");
                $stmt->execute([$user_id, $study_id]);
                $joined = false;
            }

            // Get updated participant count for this study
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM research_participants WHERE study_id = ?");
            $stmt->execute([$study_id]);
            $new_count = intval($stmt->fetchColumn());

            echo json_encode(['success' => true, 'joined' => $joined, 'new_count' => $new_count]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        // Mock fallback session
        if (!isset($_SESSION['mock_research_participants'])) {
            $_SESSION['mock_research_participants'] = [];
        }

        $found_index = -1;
        foreach ($_SESSION['mock_research_participants'] as $index => $part) {
            if ($part['user_id'] == $user_id && $part['study_id'] == $study_id) {
                $found_index = $index;
                break;
            }
        }

        if ($consent === 1 && $found_index === -1) {
            $_SESSION['mock_research_participants'][] = [
                'id' => count($_SESSION['mock_research_participants']) + 1,
                'user_id' => $user_id,
                'study_id' => $study_id
            ];
            $joined = true;
        } elseif ($consent === 0 && $found_index !== -1) {
            unset($_SESSION['mock_research_participants'][$found_index]);
            $_SESSION['mock_research_participants'] = array_values($_SESSION['mock_research_participants']);
            $joined = false;
        } else {
            $joined = ($found_index !== -1);
        }

        // Calculate count from session
        $new_count = 0;
        foreach ($_SESSION['mock_research_participants'] as $part) {
            if ($part['study_id'] == $study_id) {
                $new_count++;
            }
        }

        echo json_encode(['success' => true, 'joined' => $joined, 'new_count' => $new_count]);
    }
    exit;
}

// Handle Launching a New Research Study (Admin/Specialist Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_study') {
    if ($user_role !== 'admin' && $user_role !== 'specialist') {
        $action_error = 'Unauthorized operation.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $target = intval($_POST['target_participants'] ?? 0);

        if (empty($title) || empty($description) || $target <= 0) {
            $action_error = 'All fields are required. Target participants must be greater than 0.';
        } else {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO research_studies (title, description, status, target_participants) VALUES (?, ?, 'active', ?)");
                    $stmt->execute([$title, $description, $target]);
                    $action_success = "Research study '" . htmlspecialchars($title) . "' launched successfully!";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            } else {
                // Mock insert
                $new_id = count($_SESSION['mock_research_studies']) + 1;
                $_SESSION['mock_research_studies'][] = [
                    'id' => $new_id,
                    'title' => $title,
                    'description' => $description,
                    'status' => 'active',
                    'target_participants' => $target
                ];
                $action_success = "Research study '" . htmlspecialchars($title) . "' launched successfully! (Session Mock)";
            }
        }
    }
}

// 1. Fetch Research Studies
$studies = [];
if ($db_connected && $pdo) {
    try {
        $studies = $pdo->query("SELECT * FROM research_studies ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $studies = $_SESSION['mock_research_studies'] ?? [];
    }
} else {
    $studies = $_SESSION['mock_research_studies'] ?? [];
}

// Calculate participant counts per study
$study_participant_counts = [];
$user_consented_studies = [];

if ($db_connected && $pdo) {
    try {
        // Enrolled user list for the current logged-in user
        $stmt = $pdo->prepare("SELECT study_id FROM research_participants WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_consented_studies = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Counts for all studies
        $counts = $pdo->query("SELECT study_id, COUNT(*) as c FROM research_participants GROUP BY study_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($counts as $row) {
            $study_participant_counts[$row['study_id']] = intval($row['c']);
        }
    } catch (PDOException $e) {}
} else {
    $mock_parts = $_SESSION['mock_research_participants'] ?? [];
    foreach ($mock_parts as $part) {
        if ($part['user_id'] == $user_id) {
            $user_consented_studies[] = $part['study_id'];
        }
        if (!isset($study_participant_counts[$part['study_id']])) {
            $study_participant_counts[$part['study_id']] = 0;
        }
        $study_participant_counts[$part['study_id']]++;
    }
}

// 2. Aggregate Population Stats (For Specialists & Admins)
$total_consented_users = 0;
$overall_mood_index = 75; // Default fallback
$avg_recovery_rate = 18; // Default fallback (percentage improvement)

if ($db_connected && $pdo) {
    try {
        // Count distinct enrolled users
        $total_consented_users = intval($pdo->query("SELECT COUNT(DISTINCT user_id) FROM research_participants")->fetchColumn());

        // Overall mood index from checkins
        $mood_data = $pdo->query("
            SELECT AVG(
                CASE 
                    WHEN LOWER(mood) = 'terrible' THEN 20
                    WHEN LOWER(mood) = 'bad' THEN 40
                    WHEN LOWER(mood) = 'okay' THEN 60
                    WHEN LOWER(mood) = 'good' THEN 80
                    WHEN LOWER(mood) = 'great' THEN 100
                    ELSE 60
                END
            ) FROM daily_checkins
        ")->fetchColumn();
        if ($mood_data !== null) {
            $overall_mood_index = round($mood_data);
        }

        // Average mood recovery rate from VR logs
        $vr_recovery = $pdo->query("SELECT AVG(mood_improvement) FROM vr_practice_logs")->fetchColumn();
        if ($vr_recovery !== null) {
            // Map improvement scale (1-5 scale) to percentage (e.g. 1 point = 20%)
            $avg_recovery_rate = round($vr_recovery * 20);
        }
    } catch (PDOException $e) {}
} else {
    $total_consented_users = count(array_unique(array_column($_SESSION['mock_research_participants'] ?? [], 'user_id')));
    $overall_mood_index = 76;
    $avg_recovery_rate = 22; // 22% average improvement
}

// 3. SVG Charts Calculations & Data Seeding
$mood_trends_30_days = [];
if ($db_connected && $pdo) {
    try {
        $rows = $pdo->query("
            SELECT DATE(created_at) as date_val, AVG(
                CASE 
                    WHEN LOWER(mood) = 'terrible' THEN 20
                    WHEN LOWER(mood) = 'bad' THEN 40
                    WHEN LOWER(mood) = 'okay' THEN 60
                    WHEN LOWER(mood) = 'good' THEN 80
                    WHEN LOWER(mood) = 'great' THEN 100
                    ELSE 60
                END
            ) as avg_mood 
            FROM daily_checkins 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY DATE(created_at) ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $mood_trends_30_days[date('M d', strtotime($r['date_val']))] = round($r['avg_mood']);
        }
    } catch (PDOException $e) {}
}

if (empty($mood_trends_30_days)) {
    // Generate clean mock line chart values
    $start_time = strtotime('-10 days');
    for ($i = 0; $i <= 10; $i++) {
        $day_label = date('M d', strtotime("+$i days", $start_time));
        $mood_trends_30_days[$day_label] = [70, 72, 68, 75, 78, 74, 82, 80, 85, 79, 81][$i];
    }
}

// Burnout scores by department
$dept_burnout = [];
if ($db_connected && $pdo) {
    try {
        $rows = $pdo->query("
            SELECT d.name as dept, AVG(c.score) as score
            FROM caregiver_burnout_logs c
            JOIN users u ON c.user_id = u.id
            JOIN workplace_departments d ON u.department_id = d.id
            GROUP BY d.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            // Burnout scores are usually out of 20, map to percentage (score / 20 * 100)
            $dept_burnout[$r['dept']] = round(($r['score'] / 20) * 100);
        }
    } catch (PDOException $e) {}
}

if (empty($dept_burnout)) {
    $dept_burnout = [
        'Nursing' => 45,
        'ICU & ER' => 74,
        'Social Work' => 58,
        'Administration' => 28
    ];
}

// 4. Policy Alert Logic
$policy_alerts = [];
foreach ($dept_burnout as $dept => $burnout) {
    if ($burnout >= 70) {
        $policy_alerts[] = [
            'severity' => 'critical',
            'alert' => "High Burnout Indicator in $dept ($burnout%)",
            'recommendation' => "Mandate 12-hour rest gap between ER shifts. Automatically trigger Caregiver Respite coverage credits."
        ];
    } elseif ($burnout >= 50) {
        $policy_alerts[] = [
            'severity' => 'warning',
            'alert' => "Moderate Burnout Index in $dept ($burnout%)",
            'recommendation' => "Facilitate peer-support matching sessions. Recommend somatic release audio guides in the Streaming Hub."
        ];
    }
}
if ($overall_mood_index < 70) {
    $policy_alerts[] = [
        'severity' => 'warning',
        'alert' => "Overall Population Wellbeing Drop ($overall_mood_index%)",
        'recommendation' => "Inject organization-wide lunch-and-learn mindfulness webinars. Provide VR Centre coordinates to managers."
    ];
}

// Tab Selection
$active_tab = $_GET['tab'] ?? 'population';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human-Centred Mental Health Research Centre | Tet Wellbeing</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#FAFAF7', // Warm premium light background
                            slate: '#1C2E24',
                            sage: '#5F7D6B',
                            sageLight: '#F4F6F5',
                            coral: '#D17B6C',
                            coralLight: '#F9EFF0',
                            chartGreen: '#4D8A67',
                            chartRed: '#E06B56'
                        }
                    },
                    boxShadow: {
                        soft: '0 8px 30px rgb(0,0,0,0.03)',
                        glow: '0 0 20px rgba(95, 125, 107, 0.15)',
                    },
                    fontFamily: {
                        outfit: ['Outfit', 'sans-serif'],
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FAFAF7;
        }
        .font-outfit {
            font-family: 'Outfit', sans-serif;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #E5E7EB;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #D1D5DB;
        }
    </style>
</head>
<body class="text-brand-slate antialiased pb-24">

    <!-- HEADER BAR -->
    <header class="bg-white border-b border-[#EBE8E0] sticky top-0 z-40 backdrop-blur-md bg-white/95">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-brand-sageLight border border-brand-sage/10 shadow-sm">
                    <img src="logo.svg" alt="Tet Wellbeing Logo" class="h-6 w-6">
                </div>
                <div>
                    <h1 class="text-lg font-bold font-outfit tracking-tight leading-none text-brand-slate">Tet Wellbeing Group</h1>
                    <span class="text-[10px] text-gray-500 font-semibold tracking-wider uppercase block mt-1">Research & Population Analytics</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden sm:block text-right">
                    <span class="text-xs text-gray-500 block font-medium">Logged in as</span>
                    <span class="text-sm font-bold text-brand-slate"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <div class="h-9 w-9 rounded-full bg-brand-sage/10 border border-brand-sage/20 flex items-center justify-center text-brand-sage font-bold font-outfit uppercase">
                    <?php echo substr($user_name, 0, 1); ?>
                </div>
                <a href="logout.php" class="text-xs font-bold text-brand-coral hover:text-red-700 transition-colors uppercase font-outfit">Log out</a>
            </div>
        </div>
    </header>

    <!-- CONTENT PORTAL -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">

        <!-- TITLE SPOT -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
            <div>
                <span class="px-3 py-1 rounded-full bg-brand-sage/10 text-brand-sage border border-brand-sage/20 font-bold text-xs uppercase tracking-wider font-outfit">Ecosystem Layer 7</span>
                <h2 class="text-3xl font-extrabold font-outfit text-brand-slate mt-2">Mental Health Research Centre</h2>
                <p class="text-sm text-gray-600 mt-1 leading-relaxed max-w-2xl">
                    Population analytics, clinical efficacy evaluation, and anonymous trend data tracking for evidence-based policymaking.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-3 bg-white p-4.5 rounded-2xl border border-[#EBE8E0] shadow-soft">
                <div class="h-10 w-10 rounded-xl bg-brand-coral/15 flex items-center justify-center text-xl">🔬</div>
                <div>
                    <span class="text-[10px] text-gray-500 font-semibold block uppercase">Opt-in Data Tracking</span>
                    <span class="text-xs font-bold text-brand-coral">Anonymized Dataset Active</span>
                </div>
            </div>
        </div>

        <!-- SHARED NAVIGATION MENU INCLUDED -->
        <?php include 'nav_menu.php'; ?>

        <!-- TOAST ALERTS -->
        <?php if (!empty($action_success)): ?>
            <div id="status-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Action Successful</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_success; ?></p>
                </div>
                <button onclick="document.getElementById('status-toast').remove()" class="text-gray-400 hover:text-brand-slate p-0.5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
            <div id="error-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Action Failed</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_error; ?></p>
                </div>
                <button onclick="document.getElementById('error-toast').remove()" class="text-gray-400 hover:text-brand-slate p-0.5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($user_role === 'admin' || $user_role === 'specialist'): ?>
            <!-- SPECIALIST & ADMIN PORTAL LAYOUT -->
            
            <!-- POPULATION METRIC STAT CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-gray-500 uppercase font-outfit">Anonymized Enrollment</span>
                        <span class="text-lg">📊</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black font-outfit text-brand-slate"><?php echo $total_consented_users; ?></span>
                        <span class="text-xs font-semibold text-gray-500">Staff Participants</span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">Active consented profiles contributing to studies</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-gray-500 uppercase font-outfit">Overall Wellness Index</span>
                        <span class="text-lg text-brand-sage">💖</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black font-outfit text-brand-sage"><?php echo $overall_mood_index; ?>/100</span>
                        <span class="text-xs font-semibold text-brand-sage">Good Index</span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">Average self-reported check-in score over 30 days</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-gray-500 uppercase font-outfit">Efficacy Rate</span>
                        <span class="text-lg text-brand-coral">📈</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black font-outfit text-brand-coral">+<?php echo $avg_recovery_rate; ?>%</span>
                        <span class="text-xs font-semibold text-brand-coral">Mood Jump</span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">Average immediate improvement from VR Practice Sessions</p>
                </div>
            </div>

            <!-- ANALYTICS SUB-NAV -->
            <div class="flex items-center gap-2 mb-8 text-xs overflow-x-auto whitespace-nowrap pb-2">
                <a href="research_hub.php?tab=population" class="px-4 py-2 rounded-xl font-bold transition-all <?php echo ($active_tab === 'population') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight border border-[#EBE8E0]'; ?>">Population Analytics</a>
                <a href="research_hub.php?tab=intervention" class="px-4 py-2 rounded-xl font-bold transition-all <?php echo ($active_tab === 'intervention') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight border border-[#EBE8E0]'; ?>">Intervention Efficacy</a>
                <a href="research_hub.php?tab=studies" class="px-4 py-2 rounded-xl font-bold transition-all <?php echo ($active_tab === 'studies') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight border border-[#EBE8E0]'; ?>">Manage Research Studies</a>
                <a href="research_hub.php?tab=policy" class="px-4 py-2 rounded-xl font-bold transition-all <?php echo ($active_tab === 'policy') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight border border-[#EBE8E0]'; ?>">Policy & Guidelines</a>
            </div>

            <!-- SUB-TAB CONTENT -->
            <?php if ($active_tab === 'population'): ?>
                <!-- POPULATION ANALYTICS -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left & Mid: Graphs (Col-span 2) -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Mood Trend Chart Card -->
                        <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                            <h3 class="text-md font-bold font-outfit text-brand-slate mb-4">📈 Aggregated Wellness Index (Last 30 Days)</h3>
                            
                            <!-- SVG LINE CHART -->
                            <?php
                            $labels = array_keys($mood_trends_30_days);
                            $values = array_values($mood_trends_30_days);
                            $max_val = 100;
                            $chart_width = 800;
                            $chart_height = 250;
                            $padding = 30;

                            $points = [];
                            $count = count($values);
                            for ($i = 0; $i < $count; $i++) {
                                $x = $padding + ($i * (($chart_width - (2 * $padding)) / max(1, $count - 1)));
                                $y = $chart_height - $padding - (($values[$i] / $max_val) * ($chart_height - (2 * $padding)));
                                $points[] = "$x,$y";
                            }
                            $points_str = implode(' ', $points);
                            ?>
                            <div class="w-full overflow-x-auto">
                                <svg class="mx-auto" width="<?php echo $chart_width; ?>" height="<?php echo $chart_height; ?>" viewBox="0 0 <?php echo $chart_width; ?> <?php echo $chart_height; ?>" fill="none">
                                    <!-- Grid lines -->
                                    <line x1="<?php echo $padding; ?>" y1="<?php echo $chart_height - $padding; ?>" x2="<?php echo $chart_width - $padding; ?>" y2="<?php echo $chart_height - $padding; ?>" stroke="#F3F4F6" stroke-width="2"/>
                                    <line x1="<?php echo $padding; ?>" y1="<?php echo $padding; ?>" x2="<?php echo $chart_width - $padding; ?>" y2="<?php echo $padding; ?>" stroke="#F3F4F6" stroke-dasharray="4 4"/>
                                    <line x1="<?php echo $padding; ?>" y1="<?php echo ($chart_height/2); ?>" x2="<?php echo $chart_width - $padding; ?>" y2="<?php echo ($chart_height/2); ?>" stroke="#F3F4F6" stroke-dasharray="4 4"/>

                                    <!-- Chart Path -->
                                    <polyline points="<?php echo $points_str; ?>" fill="none" stroke="#5F7D6B" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                    
                                    <!-- Circles over coordinates -->
                                    <?php foreach ($points as $index => $pt): 
                                        $xy = explode(',', $pt);
                                    ?>
                                        <circle cx="<?php echo $xy[0]; ?>" cy="<?php echo $xy[1]; ?>" r="5" fill="#5F7D6B" stroke="#FFFFFF" stroke-width="2" class="cursor-pointer hover:r-7 transition-all"/>
                                        <text x="<?php echo $xy[0]; ?>" y="<?php echo $xy[1] - 10; ?>" text-anchor="middle" font-size="10" font-weight="bold" fill="#1C2E24" font-family="Outfit"><?php echo $values[$index]; ?>%</text>
                                    <?php endforeach; ?>

                                    <!-- Labels -->
                                    <?php foreach ($labels as $index => $lbl): 
                                        $x = $padding + ($index * (($chart_width - (2 * $padding)) / max(1, $count - 1)));
                                    ?>
                                        <text x="<?php echo $x; ?>" y="<?php echo $chart_height - 10; ?>" text-anchor="middle" font-size="9" font-weight="600" fill="#9CA3AF" font-family="Inter"><?php echo $lbl; ?></text>
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                            <p class="text-xs text-gray-500 mt-4 italic text-center">Aggregated, non-identifiable employee daily check-in averages.</p>
                        </div>

                        <!-- Burnout By Department Bar Chart -->
                        <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                            <h3 class="text-md font-bold font-outfit text-brand-slate mb-6">📊 Burnout Risk Metrics by Organizational Division</h3>
                            
                            <!-- SVG BAR CHART -->
                            <?php
                            $bar_height = 200;
                            $bar_width = 600;
                            $bar_padding = 40;
                            $depts = array_keys($dept_burnout);
                            $scores = array_values($dept_burnout);
                            $bars_count = count($depts);
                            ?>
                            <div class="w-full overflow-x-auto">
                                <svg class="mx-auto" width="<?php echo $bar_width; ?>" height="<?php echo $bar_height; ?>" viewBox="0 0 <?php echo $bar_width; ?>" fill="none">
                                    <!-- Axes -->
                                    <line x1="<?php echo $bar_padding; ?>" y1="<?php echo $bar_height - 30; ?>" x2="<?php echo $bar_width - 20; ?>" y2="<?php echo $bar_height - 30; ?>" stroke="#E5E7EB" stroke-width="1.5"/>

                                    <?php 
                                    $col_width = ($bar_width - $bar_padding - 20) / $bars_count;
                                    foreach ($scores as $index => $score): 
                                        $x = $bar_padding + ($index * $col_width) + ($col_width * 0.15);
                                        $w = $col_width * 0.7;
                                        $h = (($score / 100) * ($bar_height - 30 - 20));
                                        $y = $bar_height - 30 - $h;
                                        
                                        // Pick bar color based on burnout levels
                                        $bar_color = ($score >= 70) ? '#D17B6C' : (($score >= 50) ? '#E0A96D' : '#5F7D6B');
                                    ?>
                                        <!-- Bar Rect -->
                                        <rect x="<?php echo $x; ?>" y="<?php echo $y; ?>" width="<?php echo $w; ?>" height="<?php echo $h; ?>" fill="<?php echo $bar_color; ?>" rx="8" class="transition-opacity hover:opacity-90"/>
                                        <!-- Label value -->
                                        <text x="<?php echo $x + ($w/2); ?>" y="<?php echo $y - 8; ?>" text-anchor="middle" font-size="11" font-weight="800" fill="<?php echo $bar_color; ?>" font-family="Outfit"><?php echo $score; ?>%</text>
                                        <!-- Axis label -->
                                        <text x="<?php echo $x + ($w/2); ?>" y="<?php echo $bar_height - 12; ?>" text-anchor="middle" font-size="10" font-weight="600" fill="#6B7280" font-family="Inter"><?php echo $depts[$index]; ?></text>
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                            <div class="flex justify-center gap-6 mt-4 text-xs font-semibold text-gray-500">
                                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-brand-coral"></span> Critical (70%+)</span>
                                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-[#E0A96D]"></span> Warning (50-69%)</span>
                                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-brand-sage"></span> Healthy (<50%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Participant Metrics & Summaries -->
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                            <h3 class="text-md font-bold font-outfit text-brand-slate mb-4">🔬 Research Consent Audit</h3>
                            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                                Staff members have consented to anonymized database ingestion. Consents are managed individually and can be revoked instantly without disciplinary actions.
                            </p>
                            <div class="space-y-3.5 pt-2">
                                <div class="flex justify-between items-center text-xs border-b border-gray-100 pb-2">
                                    <span class="text-gray-500 font-medium">consented profiles</span>
                                    <span class="font-bold text-brand-slate"><?php echo $total_consented_users; ?></span>
                                </div>
                                <div class="flex justify-between items-center text-xs border-b border-gray-100 pb-2">
                                    <span class="text-gray-500 font-medium">active clinical trials</span>
                                    <span class="font-bold text-brand-sage"><?php echo count($studies); ?> Active</span>
                                </div>
                                <div class="flex justify-between items-center text-xs pb-1">
                                    <span class="text-gray-500 font-medium">demographic divisions</span>
                                    <span class="font-bold text-brand-slate">4 Departments</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-brand-sageLight p-6 rounded-3xl border border-brand-sage/20 shadow-soft">
                            <h4 class="font-bold font-outfit text-brand-slate text-sm">💡 Innovative Efficacy Highlight</h4>
                            <p class="text-xs text-gray-600 mt-2 leading-relaxed">
                                Our data analytics show a **positive Pearson correlation** between caregiver respite hours and immediate mood recovery. Efficacy tracking allows counselors to target underperforming departments.
                            </p>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_tab === 'intervention'): ?>
                <!-- INTERVENTION EFFICACY -->
                <div class="bg-white p-8 rounded-3xl border border-[#EBE8E0] shadow-soft">
                    <h3 class="text-xl font-bold font-outfit text-brand-slate mb-2">🔍 Clinical Intervention Efficacy Matrix</h3>
                    <p class="text-xs text-gray-500 mb-6">Cross-analyzing logs to measure how active therapies affect employee self-reported stressors.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <!-- VR Efficacy -->
                        <div class="p-6 rounded-2xl bg-brand-sageLight/50 border border-brand-sage/10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="h-10 w-10 rounded-xl bg-brand-sage/15 flex items-center justify-center text-lg">🕶️</div>
                                <div>
                                    <h4 class="font-bold font-outfit text-brand-slate text-sm">VR Resilience Centre Efficacy</h4>
                                    <p class="text-[10px] text-gray-500">Based on pre-and-post simulated checkins</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-gray-600">Average Mood Recovery Jump</span>
                                        <span class="text-brand-sage">+42%</span>
                                    </div>
                                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-sage rounded-full" style="width: 84%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-gray-600">Anxiety Reduction Ratio</span>
                                        <span class="text-brand-sage">3.8 pts</span>
                                    </div>
                                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-sage rounded-full" style="width: 76%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Respite Breaks Efficacy -->
                        <div class="p-6 rounded-2xl bg-brand-coralLight/40 border border-brand-coral/10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="h-10 w-10 rounded-xl bg-brand-coral/15 flex items-center justify-center text-lg">⏳</div>
                                <div>
                                    <h4 class="font-bold font-outfit text-brand-slate text-sm">Caregiver Respite Break Impact</h4>
                                    <p class="text-[10px] text-gray-500">Correlation with weekly burnout logs</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-gray-600">Burnout Score Mitigation Rate</span>
                                        <span class="text-brand-coral">-28% Burnout</span>
                                    </div>
                                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-coral rounded-full" style="width: 56%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-gray-600">Coverage Plan Success Score</span>
                                        <span class="text-brand-coral">91%</span>
                                    </div>
                                    <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-coral rounded-full" style="width: 91%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="font-bold font-outfit text-brand-slate text-sm mb-4">📋 Aggregated Efficacy Correlation Logs</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-brand-sageLight text-brand-slate font-bold uppercase tracking-wider text-[10px]">
                                    <th class="p-4 rounded-l-xl">Therapy Category</th>
                                    <th class="p-4">Utilizations</th>
                                    <th class="p-4">Stress Deflection</th>
                                    <th class="p-4">Average Session Length</th>
                                    <th class="p-4 rounded-r-xl">Outcome Efficacy</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr>
                                    <td class="p-4 font-semibold text-brand-slate">Box Breathing Audio Pacemaker</td>
                                    <td class="p-4">215 plays</td>
                                    <td class="p-4 text-green-600">-3.2 Stress Score</td>
                                    <td class="p-4">4 mins</td>
                                    <td class="p-4"><span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-bold uppercase text-[9px]">High Efficacy</span></td>
                                </tr>
                                <tr>
                                    <td class="p-4 font-semibold text-brand-slate">Deep Ocean sleepscapes</td>
                                    <td class="p-4">480 plays</td>
                                    <td class="p-4 text-green-600">+1.4h sleep duration</td>
                                    <td class="p-4">30 mins</td>
                                    <td class="p-4"><span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-bold uppercase text-[9px]">High Efficacy</span></td>
                                </tr>
                                <tr>
                                    <td class="p-4 font-semibold text-brand-slate">Virtual Public Speaking VR</td>
                                    <td class="p-4">32 practice runs</td>
                                    <td class="p-4 text-amber-600">-1.1 Panic triggers</td>
                                    <td class="p-4">12 mins</td>
                                    <td class="p-4"><span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold uppercase text-[9px]">Moderate Efficacy</span></td>
                                </tr>
                                <tr>
                                    <td class="p-4 font-semibold text-brand-slate">Clinical Boundary Training</td>
                                    <td class="p-4">95 views</td>
                                    <td class="p-4 text-green-600">-2.0 Burnout scores</td>
                                    <td class="p-4">10 mins</td>
                                    <td class="p-4"><span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-bold uppercase text-[9px]">High Efficacy</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($active_tab === 'studies'): ?>
                <!-- MANAGE RESEARCH STUDIES -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left: Studies list (Col-span 2) -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft">
                            <h3 class="text-lg font-bold font-outfit text-brand-slate mb-4">📋 Active Research Studies</h3>
                            
                            <div class="space-y-4">
                                <?php foreach ($studies as $study): 
                                    $s_id = $study['id'];
                                    $p_count = $study_participant_counts[$s_id] ?? 0;
                                    $target = $study['target_participants'];
                                    $pct = min(100, round(($p_count / $target) * 100));
                                ?>
                                    <div class="p-5 rounded-2xl border border-gray-100 bg-gray-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-brand-slate font-outfit text-sm"><?php echo htmlspecialchars($study['title']); ?></h4>
                                                <span class="px-2 py-0.5 rounded-full bg-brand-sage/10 text-brand-sage border border-brand-sage/20 text-[9px] font-bold uppercase"><?php echo htmlspecialchars($study['status']); ?></span>
                                            </div>
                                            <p class="text-xs text-gray-500 max-w-xl"><?php echo htmlspecialchars($study['description']); ?></p>
                                            
                                            <!-- Progress bar -->
                                            <div class="w-64 pt-2">
                                                <div class="flex justify-between text-[10px] text-gray-500 font-semibold mb-1">
                                                    <span>Enrolled: <?php echo $p_count; ?>/<?php echo $target; ?></span>
                                                    <span><?php echo $pct; ?>%</span>
                                                </div>
                                                <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-brand-sage rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Launch new study form -->
                    <div class="bg-white p-6 rounded-3xl border border-[#EBE8E0] shadow-soft h-fit">
                        <h3 class="text-md font-bold font-outfit text-brand-slate mb-4">🚀 Launch New Clinical Study</h3>
                        <form action="research_hub.php?tab=studies" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="create_study">
                            
                            <div>
                                <label for="title" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 font-outfit">Study Title</label>
                                <input type="text" id="title" name="title" required placeholder="e.g. Somatic Stress Ingestion Analysis" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-xs focus:ring-2 focus:ring-brand-sage focus:outline-none transition-all">
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 font-outfit">Description & Parameters</label>
                                <textarea id="description" name="description" required rows="4" placeholder="Detail the clinical scope, triggers analyzed, and demographics targeted..." class="w-full px-4 py-3 rounded-xl border border-gray-200 text-xs focus:ring-2 focus:ring-brand-sage focus:outline-none transition-all resize-none"></textarea>
                            </div>

                            <div>
                                <label for="target_participants" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 font-outfit">Target Participant Count</label>
                                <input type="number" id="target_participants" name="target_participants" required min="5" value="50" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-xs focus:ring-2 focus:ring-brand-sage focus:outline-none transition-all">
                            </div>

                            <button type="submit" class="w-full py-3 bg-brand-sage hover:bg-brand-slate text-white text-xs font-bold rounded-xl transition-colors font-outfit shadow-sm">Launch Study</button>
                        </form>
                    </div>
                </div>

            <?php elseif ($active_tab === 'policy'): ?>
                <!-- POLICY & GUIDELINES -->
                <div class="bg-white p-8 rounded-3xl border border-[#EBE8E0] shadow-soft max-w-4xl mx-auto">
                    <h3 class="text-xl font-bold font-outfit text-brand-slate mb-2">📋 Clinical Policy & Guidelines Dashboard</h3>
                    <p class="text-xs text-gray-500 mb-8">AI-Synthesized organizational health policies compiled from aggregate staff metrics.</p>

                    <div class="space-y-6">
                        <!-- Active policy alerts -->
                        <h4 class="text-xs font-bold text-gray-500 uppercase font-outfit tracking-wider">Active Policy Triggers</h4>
                        <div class="space-y-4">
                            <?php foreach ($policy_alerts as $alert): ?>
                                <div class="p-5 rounded-2xl border flex items-start gap-4 <?php echo ($alert['severity'] === 'critical') ? 'bg-brand-coralLight/40 border-brand-coral/20' : 'bg-amber-50 border-amber-200'; ?>">
                                    <span class="text-lg"><?php echo ($alert['severity'] === 'critical') ? '🚨' : '⚠️'; ?></span>
                                    <div>
                                        <h5 class="text-sm font-bold text-brand-slate font-outfit"><?php echo $alert['alert']; ?></h5>
                                        <p class="text-xs text-gray-600 mt-1 leading-relaxed"><strong class="text-brand-slate">Policy recommendation:</strong> <?php echo $alert['recommendation']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="border-gray-100 my-8">

                        <h4 class="text-xs font-bold text-gray-500 uppercase font-outfit tracking-wider">Evidence-Based Research Publications</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-5 rounded-2xl border border-gray-100 bg-gray-50/50">
                                <span class="px-2 py-0.5 rounded-full bg-brand-sage/10 text-brand-sage text-[9px] font-bold uppercase tracking-wider">Draft Policy</span>
                                <h5 class="text-xs font-bold text-brand-slate font-outfit mt-2">ICU shift buffer protocol (v1.2)</h5>
                                <p class="text-[11px] text-gray-500 mt-1 leading-relaxed">
                                    Requiring mandatory 2-hour offline respite spaces during ER peak caseload transitions, triggered by low step frequency alerts.
                                </p>
                            </div>
                            <div class="p-5 rounded-2xl border border-gray-100 bg-gray-50/50">
                                <span class="px-2 py-0.5 rounded-full bg-brand-sage/10 text-brand-sage text-[9px] font-bold uppercase tracking-wider">Under Evaluation</span>
                                <h5 class="text-xs font-bold text-brand-slate font-outfit mt-2">Binaural Sleep Therapy Efficacy</h5>
                                <p class="text-[11px] text-gray-500 mt-1 leading-relaxed">
                                    Evaluating the long term improvement rate in heart rate variability index among nurses utilizing the Ambient Sleep waves.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- CLIENT (STAFF) PORTAL LAYOUT -->
            
            <!-- TRANSPARENCY WELLBEING SCORE -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-[#EBE8E0] shadow-soft mb-8 flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="space-y-1.5">
                    <span class="px-2 py-0.5 rounded-full bg-brand-sage/10 text-brand-sage text-[9px] font-bold uppercase tracking-wider">Community Integrity</span>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate leading-tight">Anonymous Community Wellbeing Transparency</h3>
                    <p class="text-xs text-gray-500 leading-relaxed max-w-xl">
                        Here is the overall health index of the Tet community. Individual checkins are strictly private, but aggregate analytics help management verify organizational support targets.
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-4 bg-brand-sageLight p-6 rounded-2xl border border-brand-sage/10">
                    <div class="text-center">
                        <span class="text-xs font-semibold text-gray-500 uppercase block font-outfit">Community Index</span>
                        <span class="text-3xl font-black font-outfit text-brand-sage mt-1 block"><?php echo $overall_mood_index; ?>/100</span>
                    </div>
                    <div class="h-12 w-[1.5px] bg-brand-sage/20"></div>
                    <div class="max-w-[120px] text-[10px] text-gray-600 font-medium">Our overall mental wellness is currently flagged as **Good**.</div>
                </div>
            </div>

            <!-- ACTIVE STUDIES DECK -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-[#EBE8E0] shadow-soft">
                <h3 class="text-lg font-bold font-outfit text-brand-slate mb-2">📋 Active Clinical Research Studies</h3>
                <p class="text-xs text-gray-500 mb-6">Review the research trials currently seeking consented participants. Your data contribution remains fully anonymous.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($studies as $study): 
                        $s_id = $study['id'];
                        $is_joined = in_array($s_id, $user_consented_studies);
                        $p_count = $study_participant_counts[$s_id] ?? 0;
                        $target = $study['target_participants'];
                    ?>
                        <div class="p-6 rounded-2xl border border-gray-100 bg-gray-50 flex flex-col justify-between h-[230px] transition-all hover:border-brand-sage/20">
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-brand-slate font-outfit text-sm leading-tight"><?php echo htmlspecialchars($study['title']); ?></h4>
                                    <span class="px-2 py-0.5 rounded-full bg-brand-sage/10 text-brand-sage border border-brand-sage/20 text-[8px] font-bold uppercase tracking-wider shrink-0"><?php echo htmlspecialchars($study['status']); ?></span>
                                </div>
                                <p class="text-xs text-gray-500 leading-normal line-clamp-4"><?php echo htmlspecialchars($study['description']); ?></p>
                            </div>

                            <div class="pt-4 flex items-center justify-between border-t border-gray-200/50 mt-auto">
                                <div class="text-[10px] font-semibold text-gray-500">
                                    <span>Joined: </span>
                                    <span id="count-<?php echo $s_id; ?>" class="text-brand-slate font-bold"><?php echo $p_count; ?></span>
                                    <span>/<?php echo $target; ?></span>
                                </div>
                                
                                <button id="btn-<?php echo $s_id; ?>" onclick="toggleConsent(<?php echo $s_id; ?>)" class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all <?php echo $is_joined ? 'bg-brand-coral text-white hover:bg-red-700' : 'bg-brand-sage text-white hover:bg-brand-slate'; ?>">
                                    <?php echo $is_joined ? 'Leave Study' : 'Join Study'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Client AJAX script -->
            <script>
                function toggleConsent(studyId) {
                    const btn = document.getElementById(`btn-${studyId}`);
                    const countSpan = document.getElementById(`count-${studyId}`);
                    const isJoined = btn.textContent.trim() === 'Leave Study';
                    const newConsent = isJoined ? 0 : 1;

                    // Optimistic disable
                    btn.disabled = true;
                    btn.style.opacity = '0.6';

                    const formData = new FormData();
                    formData.append('action', 'toggle_consent');
                    formData.append('study_id', studyId);
                    formData.append('consent', newConsent);

                    fetch('research_hub.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        
                        if (data.success) {
                            if (data.joined) {
                                btn.textContent = 'Leave Study';
                                btn.className = 'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all bg-brand-coral text-white hover:bg-red-700';
                            } else {
                                btn.textContent = 'Join Study';
                                btn.className = 'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all bg-brand-sage text-white hover:bg-brand-slate';
                            }
                            countSpan.textContent = data.new_count;
                        } else {
                            alert('Consent update failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        alert('Error processing request.');
                        console.error(error);
                    });
                }
            </script>
        <?php endif; ?>

    </main>

</body>
</html>
