<?php
/**
 * Tet Wellbeing Group - Caregiver Wellbeing Hub (caregiver_hub.php)
 * Specialized portal with caregiver burnout tracker, respite scheduler, and resources.
 */
require_once 'db.php';

// Auth Guard: Redirect to login if user session is not active
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));

// Query user archetype
$user_archetype = $_SESSION['user_archetype'] ?? null;
if ($user_archetype === null) {
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT archetype FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_archetype = $stmt->fetchColumn();
            $_SESSION['user_archetype'] = $user_archetype;
        } catch (PDOException $ex) {}
    }
}
if ($user_archetype === null && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $user_archetype = $u['archetype'] ?? null;
            $_SESSION['user_archetype'] = $user_archetype;
            break;
        }
    }
}

$action_success = '';
$action_error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 0: Clear crisis state
    if ($action === 'clear_crisis') {
        $_SESSION['crisis_state'] = 0;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET crisis_state = 0 WHERE id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $ex) {}
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$mu) {
                if ($mu['id'] == $user_id) {
                    $mu['crisis_state'] = 0;
                }
            }
        }
        header('Location: caregiver_hub.php');
        exit;
    }

    // Action 1: Save Burnout Assessment
    if ($action === 'log_burnout') {
        $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        $level = filter_input(INPUT_POST, 'level', FILTER_DEFAULT);

        if ($score !== false && !empty($level)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO caregiver_burnout_logs (user_id, score, level) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $score, $level]);
                    $action_success = "Your self-assessment score has been saved to your health history.";
                } catch (PDOException $ex) {
                    $action_success = "Assessment logged! (Session mock storage: " . htmlspecialchars($ex->getMessage()) . ")";
                }
            } else {
                $_SESSION['mock_burnout_logs'][] = [
                    'user_id' => $user_id,
                    'score' => $score,
                    'level' => $level,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $action_success = "Your self-assessment score has been saved (Session mock storage).";
            }
        } else {
            $action_error = "Invalid assessment score. Please try completing the questionnaire again.";
        }
    }

    // Action 2: Schedule Respite Break
    if ($action === 'schedule_respite') {
        $break_date = $_POST['break_date'] ?? '';
        $duration = filter_input(INPUT_POST, 'duration_hours', FILTER_VALIDATE_INT);
        $cover_plan = filter_input(INPUT_POST, 'cover_plan', FILTER_DEFAULT);

        if (!empty($break_date) && $duration > 0 && !empty($cover_plan)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO caregiver_respite_breaks (user_id, break_date, duration_hours, cover_plan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $break_date, $duration, $cover_plan]);
                    $action_success = "Your respite break has been scheduled successfully.";
                } catch (PDOException $ex) {
                    $action_success = "Respite break scheduled! (Session mock storage: " . htmlspecialchars($ex->getMessage()) . ")";
                }
            } else {
                $_SESSION['mock_respite_breaks'][] = [
                    'user_id' => $user_id,
                    'break_date' => $break_date,
                    'duration_hours' => $duration,
                    'cover_plan' => $cover_plan,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $action_success = "Your respite break has been scheduled (Session mock storage).";
            }
        } else {
            $action_error = "Please fill out all respite break fields correctly.";
        }
    }

    // Action 3: Save Resilience Plan
    if ($action === 'save_resilience_plan') {
        $stressors = filter_input(INPUT_POST, 'stressors', FILTER_DEFAULT);
        $daily_buffers = filter_input(INPUT_POST, 'daily_buffers', FILTER_DEFAULT);
        $coping_strategies = filter_input(INPUT_POST, 'coping_strategies', FILTER_DEFAULT);
        $signs_of_burnout = filter_input(INPUT_POST, 'signs_of_burnout', FILTER_DEFAULT);
        $backup_support = filter_input(INPUT_POST, 'backup_support', FILTER_DEFAULT);
        
        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM caregiver_resilience_plans WHERE user_id = ?");
                $check->execute([$user_id]);
                if ($check->fetchColumn() > 0) {
                    $stmt = $pdo->prepare("UPDATE caregiver_resilience_plans SET stressors = ?, daily_buffers = ?, coping_strategies = ?, signs_of_burnout = ?, backup_support = ? WHERE user_id = ?");
                    $stmt->execute([$stressors, $daily_buffers, $coping_strategies, $signs_of_burnout, $backup_support, $user_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO caregiver_resilience_plans (user_id, stressors, daily_buffers, coping_strategies, signs_of_burnout, backup_support) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $stressors, $daily_buffers, $coping_strategies, $signs_of_burnout, $backup_support]);
                }
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (!$saved) {
            $found = false;
            foreach ($_SESSION['mock_resilience_plans'] as &$p) {
                if ($p['user_id'] == $user_id) {
                    $p['stressors'] = $stressors;
                    $p['daily_buffers'] = $daily_buffers;
                    $p['coping_strategies'] = $coping_strategies;
                    $p['signs_of_burnout'] = $signs_of_burnout;
                    $p['backup_support'] = $backup_support;
                    $found = true;
                    break;
                }
            }
            unset($p);
            if (!$found) {
                $_SESSION['mock_resilience_plans'][] = [
                    'user_id' => $user_id,
                    'stressors' => $stressors,
                    'daily_buffers' => $daily_buffers,
                    'coping_strategies' => $coping_strategies,
                    'signs_of_burnout' => $signs_of_burnout,
                    'backup_support' => $backup_support
                ];
            }
        }
        header("Location: caregiver_hub.php?tab=resilience&plan_saved=1");
        exit;
    }

    // Action 4: Toggle Goal Completion (AJAX)
    if ($action === 'toggle_goal') {
        header('Content-Type: application/json');
        $goal_id = filter_input(INPUT_POST, 'goal_id', FILTER_VALIDATE_INT);
        $is_completed = filter_input(INPUT_POST, 'is_completed', FILTER_VALIDATE_INT);
        
        $updated = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE caregiver_daily_goals SET is_completed = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$is_completed, $goal_id, $user_id]);
                $updated = true;
            } catch (PDOException $ex) {}
        }
        
        if (!$updated && isset($_SESSION['mock_daily_goals'])) {
            foreach ($_SESSION['mock_daily_goals'] as &$g) {
                if ($g['id'] == $goal_id && $g['user_id'] == $user_id) {
                    $g['is_completed'] = $is_completed;
                    $updated = true;
                    break;
                }
            }
            unset($g);
        }
        
        echo json_encode(['status' => $updated ? 'success' : 'error']);
        exit;
    }

    // Action 5: Complete Academy Course Quiz (AJAX)
    if ($action === 'complete_course') {
        header('Content-Type: application/json');
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_DEFAULT);
        $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        
        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO caregiver_academy_progress (user_id, course_id, is_completed, score) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE is_completed = 1, score = ?");
                $stmt->execute([$user_id, $course_id, $score, $score]);
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (!$saved && isset($_SESSION['mock_academy_progress'])) {
            $found = false;
            foreach ($_SESSION['mock_academy_progress'] as &$p) {
                if ($p['user_id'] == $user_id && $p['course_id'] === $course_id) {
                    $p['is_completed'] = 1;
                    $p['score'] = $score;
                    $found = true;
                    break;
                }
            }
            unset($p);
            if (!$found) {
                $_SESSION['mock_academy_progress'][] = [
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'is_completed' => 1,
                    'score' => $score
                ];
            }
            $saved = true;
        }
        
        echo json_encode(['status' => $saved ? 'success' : 'error']);
        exit;
    }

    // Action 6: Connect with Peer (AJAX)
    if ($action === 'connect_peer') {
        header('Content-Type: application/json');
        $peer_id = filter_input(INPUT_POST, 'peer_id', FILTER_VALIDATE_INT);
        
        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO caregiver_connections (requester_id, receiver_id, status) VALUES (?, ?, 'connected')");
                $stmt->execute([$user_id, $peer_id]);
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (!$saved && isset($_SESSION['mock_connections'])) {
            $exists = false;
            foreach ($_SESSION['mock_connections'] as $c) {
                if ($c['requester_id'] == $user_id && $c['receiver_id'] == $peer_id) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $_SESSION['mock_connections'][] = [
                    'requester_id' => $user_id,
                    'receiver_id' => $peer_id,
                    'status' => 'connected'
                ];
            }
            $saved = true;
        }
        
        echo json_encode(['status' => $saved ? 'success' : 'error']);
        exit;
    }
}

// FETCH DATA FOR THE USER
$burnout_logs = [];
$respite_breaks = [];
$resilience_plan = null;
$daily_goals = [];
$peer_matches = [];
$connections = [];
$academy_progress = [];

if ($db_connected && $pdo) {
    try {
        // Fetch last 5 burnout scores
        $stmt = $pdo->prepare("SELECT * FROM caregiver_burnout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $burnout_logs = $stmt->fetchAll();

        // Fetch upcoming respite breaks
        $stmt = $pdo->prepare("SELECT * FROM caregiver_respite_breaks WHERE user_id = ? ORDER BY break_date ASC");
        $stmt->execute([$user_id]);
        $respite_breaks = $stmt->fetchAll();
        
        // Fetch resilience plan
        $stmt = $pdo->prepare("SELECT * FROM caregiver_resilience_plans WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $resilience_plan = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch peer matches & active connections
        if ($user_archetype) {
            $stmt = $pdo->prepare("SELECT id, name, email, archetype FROM users WHERE role = 'client' AND id != ? AND archetype = ?");
            $stmt->execute([$user_id, $user_archetype]);
            $peer_matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT receiver_id FROM caregiver_connections WHERE requester_id = ?");
            $stmt->execute([$user_id]);
            $connections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Fetch academy progress
        $stmt = $pdo->prepare("SELECT * FROM caregiver_academy_progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $academy_progress[$row['course_id']] = $row;
        }
    } catch (PDOException $e) {}
}

// Fallback session reading if DB is down or has no records
if (empty($burnout_logs)) {
    $filtered_logs = array_filter($_SESSION['mock_burnout_logs'], function($log) use ($user_id) {
        return $log['user_id'] == $user_id;
    });
    usort($filtered_logs, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    $burnout_logs = array_slice($filtered_logs, 0, 5);
}

if (empty($respite_breaks)) {
    $filtered_breaks = array_filter($_SESSION['mock_respite_breaks'], function($brk) use ($user_id) {
        return $brk['user_id'] == $user_id;
    });
    usort($filtered_breaks, function($a, $b) {
        return strcmp($a['break_date'], $b['break_date']);
    });
    $respite_breaks = $filtered_breaks;
}

if ($resilience_plan === null && isset($_SESSION['mock_resilience_plans'])) {
    foreach ($_SESSION['mock_resilience_plans'] as $p) {
        if ($p['user_id'] == $user_id) {
            $resilience_plan = $p;
            break;
        }
    }
}

if (empty($peer_matches) && isset($_SESSION['mock_users'])) {
    $peer_matches = array_filter($_SESSION['mock_users'], function($u) use ($user_id, $user_archetype) {
        return $u['role'] === 'client' && $u['id'] != $user_id && ($u['archetype'] ?? '') === $user_archetype;
    });
}

if (empty($connections) && isset($_SESSION['mock_connections'])) {
    foreach ($_SESSION['mock_connections'] as $c) {
        if ($c['requester_id'] == $user_id) {
            $connections[] = $c['receiver_id'];
        }
    }
}

if (empty($academy_progress) && isset($_SESSION['mock_academy_progress'])) {
    foreach ($_SESSION['mock_academy_progress'] as $p) {
        if ($p['user_id'] == $user_id) {
            $academy_progress[$p['course_id']] = $p;
        }
    }
}

// AI Daily Goals Generation and Fetching
$today_str = date('Y-m-d');
$goals_loaded = false;

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM caregiver_daily_goals WHERE user_id = ? AND created_date = ?");
        $stmt->execute([$user_id, $today_str]);
        $daily_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $goals_loaded = true;
    } catch (PDOException $ex) {}
}

if (!$goals_loaded && isset($_SESSION['mock_daily_goals'])) {
    $daily_goals = array_filter($_SESSION['mock_daily_goals'], function($g) use ($user_id, $today_str) {
        return $g['user_id'] == $user_id && $g['created_date'] === $today_str;
    });
    $goals_loaded = true;
}

if (empty($daily_goals)) {
    $burnout_score = 10;
    if (!empty($burnout_logs)) {
        $burnout_score = (int)$burnout_logs[0]['score'];
    }
    
    $goals_pool = [];
    if ($user_archetype === 'stressed_student') {
        if ($burnout_score >= 15) {
            $goals_pool = [
                "Take a 5-minute cognitive disengagement block right now.",
                "Do 3 complete cycles of Box Breathing to reset your nervous system.",
                "Identify one deadline you can request an extension for to reduce stress."
            ];
        } else {
            $goals_pool = [
                "Walk outside for 10 minutes between study sessions.",
                "Organize your study desk and remove clutter.",
                "Send a quick message to a classmate to check in."
            ];
        }
    } elseif ($user_archetype === 'dementia_carer') {
        if ($burnout_score >= 15) {
            $goals_pool = [
                "Contact one support person from your backup list for a 15-minute chat.",
                "Take a 10-minute micro-break during the late-afternoon sundowning window.",
                "Do the 5-minute audio breathing space exercise in the Caregiver Hub."
            ];
        } else {
            $goals_pool = [
                "Write down one moment of connection you shared with your partner today.",
                "Sit in silence with your favorite hot drink for 10 minutes.",
                "Stretch your shoulders and neck for 3 minutes to relieve caregiving tension."
            ];
        }
    } else {
        if ($burnout_score >= 15) {
            $goals_pool = [
                "Identify one daily task you can delegate or skip today.",
                "Log a self-assessment on the Caregiver Burnout Tracker.",
                "Do 3 minutes of grounding (name 3 things you hear right now)."
            ];
        } else {
            $goals_pool = [
                "Write a gratitude reflection about one small helper in your life.",
                "Do a 5-minute gentle stretch deck routine.",
                "Read a Caregiver Respite guide to prepare for your next break."
            ];
        }
    }
    
    foreach ($goals_pool as $g_text) {
        $goal_saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO caregiver_daily_goals (user_id, goal_text, created_date, is_completed) VALUES (?, ?, ?, 0)");
                $stmt->execute([$user_id, $g_text, $today_str]);
                $goal_saved = true;
            } catch (PDOException $ex) {}
        }
        if (!$goal_saved) {
            $_SESSION['mock_daily_goals'][] = [
                'id' => count($_SESSION['mock_daily_goals']) + 1,
                'user_id' => $user_id,
                'goal_text' => $g_text,
                'created_date' => $today_str,
                'is_completed' => 0
            ];
        }
    }
    
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM caregiver_daily_goals WHERE user_id = ? AND created_date = ?");
            $stmt->execute([$user_id, $today_str]);
            $daily_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {}
    } else {
        $daily_goals = array_filter($_SESSION['mock_daily_goals'], function($g) use ($user_id, $today_str) {
            return $g['user_id'] == $user_id && $g['created_date'] === $today_str;
        });
    }
}

$sub_tab = filter_input(INPUT_GET, 'tab', FILTER_DEFAULT);
if (!in_array($sub_tab, ['respite', 'resilience', 'recovery', 'academy', 'matching'])) {
    $sub_tab = 'respite';
}

$today_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caregiver Wellbeing Hub - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',       // Soft warm off-white
                            sage: '#5E8C71',     // Calming Sage Green
                            slate: '#264653',    // Deep Slate
                            sky: '#8ECAE6',      // Soft Sky Blue
                            coral: '#E76F51',    // Muted Coral
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        'active': '0 12px 24px -6px rgba(94, 140, 113, 0.15)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F7F5F0;
            color: #264653;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #F7F5F0;
        }
        ::-webkit-scrollbar-thumb {
            background: #D9D5CB;
            border-radius: 9999px;
            border: 2px solid #F7F5F0;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #5E8C71;
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Emergency Support</span>
                </button>

                <!-- Profile Avatar -->
                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <hr class="my-2 border-gray-100">
                        <?php if ($user_role === 'admin'): ?>
                        <a href="admin_dashboard.php" class="block px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold font-outfit">Admin Console</a>
                        <?php endif; ?>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-4xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Header / Banner Card (The Generational Anchor) -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-lg">
                <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit">Ecosystem Layer 1</p>
                <h1 class="text-3xl md:text-4xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    Caregiver <span class="text-brand-sage">Wellbeing Hub</span>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed mt-1">
                    You take care of others. We are here to support and care for you. Access respite tools, burnout trackers, and dedicated programs built on shared strength.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-56 h-36 md:h-36 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/generational_anchor.png" alt="Generational Anchor - Supporting Hands" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold overflow-x-auto whitespace-nowrap pb-1">
            <a href="dashboard.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage font-outfit">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Teletherapy Hub</a>
            <a href="ai_companion.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">AI Companion</a>
            <a href="predictive_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Digital Twin</a>
        </div>

        <!-- CAREGIVER SUB-TABS NAVIGATION -->
        <div class="flex items-center gap-2 mb-8 text-xs overflow-x-auto whitespace-nowrap pb-2">
            <a href="caregiver_hub.php?tab=respite" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($sub_tab === 'respite') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Respite & Burnout Tracker</a>
            <a href="caregiver_hub.php?tab=resilience" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($sub_tab === 'resilience') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Resilience Plan & Daily Goals</a>
            <a href="caregiver_hub.php?tab=recovery" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($sub_tab === 'recovery') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Recovery & Stretches</a>
            <a href="caregiver_hub.php?tab=academy" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($sub_tab === 'academy') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Caregiver Academy</a>
            <a href="caregiver_hub.php?tab=matching" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($sub_tab === 'matching') ? 'bg-brand-sage text-white shadow-soft' : 'bg-white text-brand-slate hover:bg-brand-sageLight/50 border border-[#EBE8E0]'; ?>">Peer Matching</a>
        </div>

        <?php if ($sub_tab === 'respite'): ?>

        <!-- FORM ACTION NOTIFICATIONS -->
        <?php if (!empty($action_success)): ?>
            <div id="action-toast-s" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Success</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_success; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-s').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
            <div id="action-toast-e" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Action Required</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_error; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-e').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: INTERACTIVE BURNOUT TRACKER -->
        <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] mb-8 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-2 h-full bg-brand-coral"></div>
            
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate flex items-center gap-2">
                        <span>Caregiver Burnout Tracker</span>
                    </h2>
                    <p class="text-sm text-gray-500 mt-0.5">Evaluate your fatigue indices to access targeted relief options.</p>
                </div>
                <!-- History Badge -->
                <?php if (!empty($burnout_logs)): ?>
                    <div class="self-start text-xs font-semibold text-gray-500 bg-brand-bg border border-[#EBE8E0] px-3 py-1.5 rounded-xl">
                        Last Assessment: <span class="font-bold text-brand-slate"><?php echo htmlspecialchars($burnout_logs[0]['level']); ?> Burnout</span> (<?php echo date('M j', strtotime($burnout_logs[0]['created_at'])); ?>)
                    </div>
                <?php endif; ?>
            </div>

            <!-- MULTI-STEP ASSESSMENT LAYOUT -->
            <div id="assessment-quiz-container">
                <!-- Progress Indicator -->
                <div class="w-full bg-[#FAF9F6] rounded-full h-2 mb-6 border border-gray-100 overflow-hidden">
                    <div id="quiz-progress-bar" class="bg-brand-coral h-full w-[20%] transition-all duration-300"></div>
                </div>

                <!-- Questions List (Shown one by one via JS) -->
                <div id="quiz-question-card" class="min-h-[160px] flex flex-col justify-center">
                    <span id="quiz-question-number" class="text-xs font-bold text-brand-coral uppercase tracking-wider font-outfit">Question 1 of 5</span>
                    <h3 id="quiz-question-text" class="text-lg font-bold text-brand-slate mt-1 leading-snug">How often do you feel physically exhausted from caregiving duties?</h3>
                    
                    <!-- Choices Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 mt-6">
                        <button type="button" onclick="selectAnswer(1)" class="quiz-choice-btn py-3 px-4 rounded-xl border border-gray-200 text-sm font-semibold text-brand-slate bg-[#FAF9F6] hover:bg-brand-sky/10 hover:border-brand-sky hover:text-brand-slate transition-all text-center">Never</button>
                        <button type="button" onclick="selectAnswer(2)" class="quiz-choice-btn py-3 px-4 rounded-xl border border-gray-200 text-sm font-semibold text-brand-slate bg-[#FAF9F6] hover:bg-brand-sky/10 hover:border-brand-sky hover:text-brand-slate transition-all text-center">Seldom</button>
                        <button type="button" onclick="selectAnswer(3)" class="quiz-choice-btn py-3 px-4 rounded-xl border border-gray-200 text-sm font-semibold text-brand-slate bg-[#FAF9F6] hover:bg-brand-sky/10 hover:border-brand-sky hover:text-brand-slate transition-all text-center">Sometimes</button>
                        <button type="button" onclick="selectAnswer(4)" class="quiz-choice-btn py-3 px-4 rounded-xl border border-gray-200 text-sm font-semibold text-brand-slate bg-[#FAF9F6] hover:bg-brand-sky/10 hover:border-brand-sky hover:text-brand-slate transition-all text-center">Often</button>
                        <button type="button" onclick="selectAnswer(5)" class="quiz-choice-btn py-3 px-4 rounded-xl border border-gray-200 text-sm font-semibold text-brand-slate bg-[#FAF9F6] hover:bg-brand-sky/10 hover:border-brand-sky hover:text-brand-slate transition-all text-center">Always</button>
                    </div>
                </div>
            </div>

            <!-- ASSESSMENT RESULT PANEL (Hidden initially, active via JS) -->
            <div id="assessment-result-container" class="hidden space-y-6">
                <div class="p-6 rounded-2xl bg-brand-bg border border-gray-100 flex flex-col md:flex-row items-center justify-between gap-6">
                    
                    <!-- Ring Gauge -->
                    <div class="relative flex items-center justify-center h-32 w-32 shrink-0">
                        <svg class="w-full h-full transform -rotate-90">
                            <!-- Background Circle -->
                            <circle cx="64" cy="64" r="54" stroke="#EBE8E0" stroke-width="8" fill="transparent"/>
                            <!-- Active indicator ring -->
                            <circle id="result-gauge-ring" cx="64" cy="64" r="54" stroke="#E76F51" stroke-width="8" fill="transparent" stroke-dasharray="339" stroke-dashoffset="339" class="transition-all duration-1000 ease-out"/>
                        </svg>
                        <div class="absolute flex flex-col items-center justify-center">
                            <span id="result-score-text" class="text-3xl font-extrabold text-brand-slate font-outfit">18</span>
                            <span class="text-[10px] uppercase font-bold tracking-widest text-gray-400">Score / 25</span>
                        </div>
                    </div>

                    <!-- Level Explanation -->
                    <div class="flex-grow space-y-2">
                        <span id="result-level-badge" class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">High Burnout</span>
                        <h4 class="text-lg font-bold text-brand-slate font-outfit">Burnout Self-Assessment Result</h4>
                        <p id="result-recommendation-text" class="text-sm text-gray-500 leading-relaxed">
                            Caring for others should not cost your own health. Please prioritize taking immediate respite breaks, setting clear medical boundaries, or contacting our teletherapy matching desk.
                        </p>
                    </div>
                </div>

                <!-- History grid preview -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pt-4 border-t border-gray-100">
                    <button type="button" onclick="resetQuiz()" class="px-5 py-2.5 rounded-xl border border-gray-300 text-xs font-semibold text-brand-slate hover:bg-gray-50 active:scale-95 transition-all text-center">
                        Retake Assessment
                    </button>
                    
                    <!-- Hidden auto-save trigger form -->
                    <form method="POST" action="caregiver_hub.php">
                        <input type="hidden" name="action" value="log_burnout">
                        <input type="hidden" name="score" id="db-score-input" value="0">
                        <input type="hidden" name="level" id="db-level-input" value="">
                        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-brand-sage text-white text-xs font-bold shadow-sm hover:bg-brand-sageHover active:scale-95 transition-all text-center">
                            Save Result to History
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- SECTION 2: RESPITE BREAK SCHEDULER & HISTORY -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            
            <!-- Respite Form (2/3 columns on desktop) -->
            <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] md:col-span-2 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                <div class="mb-6">
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Schedule a Respite Break</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Secure some personal downtime by organizing support backup in advance.</p>
                </div>

                <form method="POST" action="caregiver_hub.php" class="space-y-4">
                    <input type="hidden" name="action" value="schedule_respite">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="break_date" class="block text-xs font-semibold text-brand-slate mb-1">Target Date</label>
                            <input 
                                type="date" 
                                name="break_date" 
                                id="break_date" 
                                required 
                                min="<?php echo date('Y-m-d'); ?>"
                                class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                            >
                        </div>
                        <div>
                            <label for="duration_hours" class="block text-xs font-semibold text-brand-slate mb-1">Downtime Duration (Hours)</label>
                            <input 
                                type="number" 
                                name="duration_hours" 
                                id="duration_hours" 
                                required 
                                min="1" 
                                max="72"
                                placeholder="e.g. 4"
                                class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-sm focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                            >
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="cover_plan" class="block text-xs font-semibold text-brand-slate">Backup Coverage Plan / Notes</label>
                            <button type="button" id="transcribe-cover-plan" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-2 py-0.5 rounded-lg border border-brand-sage/10 transition-all select-none">
                                <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                </svg>
                                <span>Transcribe Voice</span>
                            </button>
                        </div>
                        <textarea 
                            name="cover_plan" 
                            id="cover_plan" 
                            rows="3" 
                            required 
                            placeholder="e.g., Uncle Robert covering medication schedules and lunch visit. I will use this time for walking and self-care."
                            class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                        ></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-all active:scale-95">
                        Confirm Respite Schedule
                    </button>
                </form>
            </section>

            <!-- Respite History list (1/3 columns) -->
            <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold font-outfit text-brand-slate">My Respite Breaks</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Keep track of your scheduled rest times.</p>
                    
                    <div class="mt-4 space-y-3 max-h-[220px] overflow-y-auto pr-1">
                        <?php if (empty($respite_breaks)): ?>
                            <div class="text-center py-8 text-gray-400 text-xs">
                                <span class="text-2xl block mb-2">📅</span>
                                No breaks logged yet.<br>Prioritize rest today.
                            </div>
                        <?php else: ?>
                            <?php foreach ($respite_breaks as $break): ?>
                                <div class="p-3 rounded-xl bg-brand-bg border border-gray-100 space-y-1.5">
                                    <div class="flex items-center justify-between text-xs font-bold text-brand-slate">
                                        <span><?php echo date('D, M j, Y', strtotime($break['break_date'])); ?></span>
                                        <span class="text-brand-sage bg-brand-sageLight px-2 py-0.5 rounded text-[10px]"><?php echo (int)$break['duration_hours']; ?> Hrs</span>
                                    </div>
                                    <p class="text-[10px] text-gray-500 leading-normal truncate-3-lines italic">
                                        &ldquo;<?php echo htmlspecialchars($break['cover_plan']); ?>&rdquo;
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick tips card -->
                <div class="mt-4 pt-4 border-t border-gray-100 text-[10px] text-gray-400 leading-relaxed">
                    <strong>Tip:</strong> Even 20 minutes of mental disengagement per day dramatically lowers sympathetic nervous system strain.
                </div>
            </section>
        </div>

        <!-- SECTION 3: MIND MINDFULNESS PLAYER & GUIDES -->
        <section class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            
            <!-- Audio Breathing Player -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between relative overflow-hidden">
                <!-- Wave vector overlay decoration -->
                <div class="absolute -bottom-8 left-0 right-0 h-20 bg-brand-sky/10 blur-xl"></div>
                
                <div>
                    <h3 class="text-base font-bold font-outfit text-brand-slate flex items-center gap-1.5">
                        <span class="text-brand-sky">♫</span>
                        <span>Mindfulness Player</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Quick audio respite exercises for instant cognitive centering.</p>
                </div>

                <!-- Player Box -->
                <div class="my-6 p-4 rounded-2xl bg-brand-bg border border-gray-100 flex items-center gap-4 relative z-10">
                    <!-- Play/Pause Button -->
                    <button type="button" id="audio-play-btn" class="h-12 w-12 rounded-full bg-brand-sky text-brand-slate hover:bg-brand-sky/80 flex items-center justify-center shadow transition-all active:scale-90">
                        <!-- Play Icon (default) -->
                        <svg id="play-icon" class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <!-- Pause Icon (hidden default) -->
                        <svg id="pause-icon" class="h-5 w-5 fill-current hidden" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>

                    <!-- Title & Waveform Seek -->
                    <div class="flex-grow space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-bold text-brand-slate">
                            <span>Breathing Space for Caregivers</span>
                            <span id="audio-timer" class="text-gray-400">05:00</span>
                        </div>
                        <!-- Progress bar -->
                        <div id="audio-progress-container" class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden cursor-pointer relative">
                            <div id="audio-progress-bar" class="h-full bg-brand-sky w-0 transition-all duration-300"></div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 italic text-center">
                    "Deep inhale for 4 seconds, hold for 4, exhale for 4, hold for 4..."
                </p>
            </div>

            <!-- Helpful Guides Directory -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] space-y-4">
                <h3 class="text-base font-bold font-outfit text-brand-slate">Respite & Care Guides</h3>
                
                <div class="space-y-3 text-xs">
                    <?php if ($user_archetype === 'dementia_carer'): ?>
                        <!-- Dementia Carer Guides -->
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Memory Loss Care: Dealing with Sundowning</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">4 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Saying 'no' to guilt and 'yes' to health while managing sundowning and memory decay.</p>
                        </a>
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Dementia Care Respite Checklists</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">6 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Structured checklists for handoffs to respite providers, ensuring continuity of medication schedules.</p>
                        </a>
                    <?php elseif ($user_archetype === 'stressed_student'): ?>
                        <!-- Stressed Student Guides -->
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Academic Burnout: Managing Exam Anxiety</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">5 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Cognitive pacing and mental reframing to lower stress hormones during heavy academic loads.</p>
                        </a>
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Pacing Studies with Scheduled Breaks</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">4 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Using study breaks and mental disengagement blocks to enhance memory consolidation and avoid cramming fatigue.</p>
                        </a>
                    <?php else: ?>
                        <!-- General Wellbeing Guides (Default) -->
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Setting Boundaries with Relatives</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">4 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Saying 'no' to guilt and 'yes' to health while managing parent and spouse expectations.</p>
                        </a>
                        <a href="#" class="block p-3 rounded-2xl border border-gray-100 bg-[#FAF9F6] hover:border-brand-sage transition-all hover:shadow-sm">
                            <div class="flex items-center justify-between font-bold text-brand-slate">
                                <span>Respite Care Financial Resource Guide</span>
                                <span class="text-[10px] font-medium text-gray-400 font-sans">8 min read</span>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Exploring federal subsidies, state programs, and grant options for caregiver support.</p>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- SUB-TAB CONTENT: RESILIENCE PLAN & DAILY GOALS -->
        <?php if ($sub_tab === 'resilience'): ?>
            <div class="space-y-8">
                <!-- Daily Goals Card -->
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sky"></div>
                    <div class="mb-6">
                        <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate flex items-center gap-2">
                            <span>🌱 AI Daily Wellbeing Targets</span>
                        </h2>
                        <p class="text-sm text-gray-500 mt-0.5">Custom mental health goals computed by AI based on your archetype and burnout assessment level.</p>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($daily_goals)): ?>
                            <p class="text-xs text-gray-400 italic">No goals defined for today.</p>
                        <?php else: ?>
                            <?php foreach ($daily_goals as $goal): ?>
                                <div class="flex items-center gap-3.5 p-4 bg-brand-bg border border-gray-100 rounded-2xl transition-all hover:shadow-sm">
                                    <input 
                                        type="checkbox" 
                                        id="goal-<?php echo $goal['id']; ?>" 
                                        <?php echo $goal['is_completed'] ? 'checked' : ''; ?>
                                        onchange="toggleGoal(<?php echo $goal['id']; ?>, this.checked)"
                                        class="h-5 w-5 rounded-md border-gray-300 text-brand-sage focus:ring-brand-sage cursor-pointer transition-all"
                                    >
                                    <label 
                                        for="goal-<?php echo $goal['id']; ?>" 
                                        class="text-sm font-semibold cursor-pointer text-brand-slate transition-all select-none <?php echo $goal['is_completed'] ? 'line-through text-gray-400 font-medium' : ''; ?>"
                                    >
                                        <?php echo htmlspecialchars($goal['goal_text']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Personal Resilience Plan Section -->
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate">My Personal Resilience Plan</h2>
                            <p class="text-sm text-gray-500 mt-0.5">A structured plan to anchor your mental wellbeing under stress.</p>
                        </div>
                        <?php if ($resilience_plan && !isset($_GET['edit_plan'])): ?>
                            <a href="caregiver_hub.php?tab=resilience&edit_plan=1" class="self-start px-4 py-2 rounded-xl border border-gray-300 text-xs font-semibold text-brand-slate hover:bg-gray-50 active:scale-95 transition-all text-center">
                                Edit My Plan
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($resilience_plan && !isset($_GET['edit_plan'])): ?>
                        <!-- Display Resilience Plan details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-coral flex items-center gap-1.5">
                                    <span>⚡</span> Stressors & Triggers
                                </h4>
                                <p class="text-sm font-semibold text-brand-slate leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($resilience_plan['stressors']); ?></p>
                            </div>

                            <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-sage flex items-center gap-1.5">
                                    <span>🛡️</span> Daily Wellbeing Buffers
                                </h4>
                                <p class="text-sm font-semibold text-brand-slate leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($resilience_plan['daily_buffers']); ?></p>
                            </div>

                            <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-slate flex items-center gap-1.5">
                                    <span>⚓</span> Coping Strategies
                                </h4>
                                <p class="text-sm font-semibold text-brand-slate leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($resilience_plan['coping_strategies']); ?></p>
                            </div>

                            <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-coral flex items-center gap-1.5">
                                    <span>⚠️</span> Overload Warning Signs
                                </h4>
                                <p class="text-sm font-semibold text-brand-slate leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($resilience_plan['signs_of_burnout']); ?></p>
                            </div>

                            <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-2 md:col-span-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-brand-sage flex items-center gap-1.5">
                                    <span>📞</span> Backup Support Network
                                </h4>
                                <p class="text-sm font-semibold text-brand-slate leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($resilience_plan['backup_support']); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Resilience Plan Form -->
                        <form method="POST" action="caregiver_hub.php" class="space-y-5">
                            <input type="hidden" name="action" value="save_resilience_plan">
                            
                            <div>
                                <label for="stressors" class="block text-xs font-bold text-brand-slate mb-1">1. What are your primary stressors & stress triggers?</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">Identify situations that trigger anxiety, physical fatigue, or frustration (e.g. lack of sleep, difficult transitions).</p>
                                <textarea 
                                    name="stressors" 
                                    id="stressors" 
                                    rows="2" 
                                    required 
                                    placeholder="e.g. Broken sleep patterns, preparing dinners during late-afternoon agitation windows."
                                    class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                                ><?php echo $resilience_plan ? htmlspecialchars($resilience_plan['stressors']) : ''; ?></textarea>
                            </div>

                            <div>
                                <label for="daily_buffers" class="block text-xs font-bold text-brand-slate mb-1">2. Daily Wellbeing Buffers</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">Define small, non-negotiable daily habits that keep you grounded (e.g. 10 minutes sitting in silence, short walk, coffee before others wake up).</p>
                                <textarea 
                                    name="daily_buffers" 
                                    id="daily_buffers" 
                                    rows="2" 
                                    required 
                                    placeholder="e.g. Morning quiet coffee for 15 minutes, doing 5 minutes of gentle stretching before bed."
                                    class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                                ><?php echo $resilience_plan ? htmlspecialchars($resilience_plan['daily_buffers']) : ''; ?></textarea>
                            </div>

                            <div>
                                <label for="coping_strategies" class="block text-xs font-bold text-brand-slate mb-1">3. Personal Coping Strategies</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">What can you do in the middle of a stressful event? (e.g. 3 cycles of Box Breathing, walking away for 2 minutes, repeating a grounding mantra).</p>
                                <textarea 
                                    name="coping_strategies" 
                                    id="coping_strategies" 
                                    rows="2" 
                                    required 
                                    placeholder="e.g. Pausing to do Box Breathing, telling myself 'I can only do my best, and that is enough'."
                                    class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                                ><?php echo $resilience_plan ? htmlspecialchars($resilience_plan['coping_strategies']) : ''; ?></textarea>
                            </div>

                            <div>
                                <label for="signs_of_burnout" class="block text-xs font-bold text-brand-slate mb-1">4. Your Overload Warning Signs</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">What are physical/emotional signs that you need a break immediately? (e.g. headaches, snapping at family, feelings of resentment).</p>
                                <textarea 
                                    name="signs_of_burnout" 
                                    id="signs_of_burnout" 
                                    rows="2" 
                                    required 
                                    placeholder="e.g. Clenched jaw, heavy headache, feeling of wanting to cry from minor inconveniences."
                                    class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                                ><?php echo $resilience_plan ? htmlspecialchars($resilience_plan['signs_of_burnout']) : ''; ?></textarea>
                            </div>

                            <div>
                                <label for="backup_support" class="block text-xs font-bold text-brand-slate mb-1">5. Backup Support Network</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">Who can you contact for help or to cover for you? (Include name, contact info, and role).</p>
                                <textarea 
                                    name="backup_support" 
                                    id="backup_support" 
                                    rows="2" 
                                    required 
                                    placeholder="e.g. Brother Robert (555-0199) - can do medication drop-offs. Neighbor Jane (555-0120) - available for short 30-min coverages."
                                    class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none transition-all text-brand-slate"
                                ><?php echo $resilience_plan ? htmlspecialchars($resilience_plan['backup_support']) : ''; ?></textarea>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" class="px-6 py-2.5 rounded-xl bg-brand-sage text-white text-xs font-bold shadow-md hover:bg-brand-sageHover active:scale-95 transition-all text-center">
                                    Save Resilience Plan
                                </button>
                                <?php if ($resilience_plan): ?>
                                    <a href="caregiver_hub.php?tab=resilience" class="px-5 py-2.5 rounded-xl border border-gray-300 text-xs font-semibold text-brand-slate hover:bg-gray-50 active:scale-95 transition-all text-center">
                                        Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
            
            <script>
                function toggleGoal(goalId, isChecked) {
                    const label = document.querySelector(`label[for="goal-${goalId}"]`);
                    if (isChecked) {
                        label.classList.add('line-through', 'text-gray-400', 'font-medium');
                    } else {
                        label.classList.remove('line-through', 'text-gray-400', 'font-medium');
                    }

                    const formData = new FormData();
                    formData.append('action', 'toggle_goal');
                    formData.append('goal_id', goalId);
                    formData.append('is_completed', isChecked ? 1 : 0);

                    fetch('caregiver_hub.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            console.error('Failed to update goal');
                        }
                    })
                    .catch(err => console.error('Error toggling goal:', err));
                }
            </script>
        <?php endif; ?>

        <!-- SUB-TAB CONTENT: RECOVERY TOOLS -->
        <?php if ($sub_tab === 'recovery'): ?>
            <div class="space-y-8">
                <!-- Grounding and Timer Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Grounding Slideshow Card -->
                    <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] flex flex-col justify-between min-h-[380px] relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                        
                        <div>
                            <h3 class="text-lg font-bold font-outfit text-brand-slate flex items-center gap-2">
                                <span>🧘</span> 5-4-3-2-1 Sensory Grounding
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">Pause and anchor yourself in the physical room to quiet anxious loops.</p>
                        </div>

                        <!-- Grounding Slides -->
                        <div id="grounding-slideshow" class="my-6 flex-grow flex flex-col justify-center">
                            <!-- Slide 1: Intro -->
                            <div class="grounding-slide space-y-3" data-step="0">
                                <h4 class="text-sm font-bold text-brand-slate">Center Your Mind</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">This exercise redirects your focus away from racing thoughts and back to the safety of the present moment. Breathe deep and tap Next when ready.</p>
                            </div>

                            <!-- Slide 2: 5 See -->
                            <div class="grounding-slide hidden space-y-3" data-step="1">
                                <span class="text-xs font-bold text-brand-sage uppercase tracking-wider font-outfit">Step 1 of 5</span>
                                <h4 class="text-sm font-bold text-brand-slate">Name 5 things you can SEE around you</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Look around your environment. Focus on small details: a pattern on the wall, a shadow, a cup, or a plant.</p>
                                <input type="text" placeholder="e.g. A green leaf, my wristwatch..." class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-2.5 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                            </div>

                            <!-- Slide 3: 4 Feel -->
                            <div class="grounding-slide hidden space-y-3" data-step="2">
                                <span class="text-xs font-bold text-brand-sage uppercase tracking-wider font-outfit">Step 2 of 5</span>
                                <h4 class="text-sm font-bold text-brand-slate">Name 4 things you can FEEL (tactile)</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Observe tactile sensations: the weight of your feet on the floor, the texture of your shirt, or the cool air on your skin.</p>
                                <input type="text" placeholder="e.g. Feet on the floor, fabric of my pants..." class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-2.5 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                            </div>

                            <!-- Slide 4: 3 Hear -->
                            <div class="grounding-slide hidden space-y-3" data-step="3">
                                <span class="text-xs font-bold text-brand-sage uppercase tracking-wider font-outfit">Step 3 of 5</span>
                                <h4 class="text-sm font-bold text-brand-slate">Name 3 things you can HEAR right now</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Listen to background sounds: the click of a fan, birds chirping outside, or traffic in the distance.</p>
                                <input type="text" placeholder="e.g. Clock ticking, car passing..." class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-2.5 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                            </div>

                            <!-- Slide 5: 2 Smell -->
                            <div class="grounding-slide hidden space-y-3" data-step="4">
                                <span class="text-xs font-bold text-brand-sage uppercase tracking-wider font-outfit">Step 4 of 5</span>
                                <h4 class="text-sm font-bold text-brand-slate">Name 2 things you can SMELL around you</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Inhale slowly. Try to detect subtle smells in the air, or smell your sleeve or a cup of tea.</p>
                                <input type="text" placeholder="e.g. Coffee aroma, laundry detergent..." class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-2.5 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                            </div>

                            <!-- Slide 6: 1 Taste -->
                            <div class="grounding-slide hidden space-y-3" data-step="5">
                                <span class="text-xs font-bold text-brand-sage uppercase tracking-wider font-outfit">Step 5 of 5</span>
                                <h4 class="text-sm font-bold text-brand-slate">Name 1 thing you can TASTE</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Observe the taste in your mouth. Sip some water if needed, or recall a taste from your last meal.</p>
                                <input type="text" placeholder="e.g. Minty toothpaste, cool water..." class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-2.5 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                            </div>

                            <!-- Slide 7: Complete -->
                            <div class="grounding-slide hidden space-y-3 text-center" data-step="6">
                                <span class="text-3xl block">✨</span>
                                <h4 class="text-sm font-bold text-brand-sage">Grounding Completed</h4>
                                <p class="text-xs text-gray-500 leading-relaxed">Your awareness has returned to the physical room. Keep breathing slowly and carry this calm forward.</p>
                            </div>
                        </div>

                        <!-- Grounding Controls -->
                        <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                            <button type="button" id="grounding-prev-btn" onclick="moveGroundingSlide(-1)" class="px-4 py-2 rounded-xl text-xs font-semibold text-gray-400 hover:text-brand-slate transition-colors disabled:opacity-30" disabled>
                                Back
                            </button>
                            <button type="button" id="grounding-next-btn" onclick="moveGroundingSlide(1)" class="px-5 py-2 rounded-xl bg-brand-sage text-white text-xs font-bold shadow-sm hover:bg-brand-sageHover active:scale-95 transition-all">
                                Start Exercise
                            </button>
                        </div>
                    </section>

                    <!-- Circular Micro-Break Timer Card -->
                    <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] flex flex-col justify-between min-h-[380px] relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-2 h-full bg-brand-sky"></div>

                        <div>
                            <h3 class="text-lg font-bold font-outfit text-brand-slate flex items-center gap-2">
                                <span>⏱️</span> Interactive Respite Timer
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">Trigger a brief scheduled focus timeout to reset your cognitive load.</p>
                        </div>

                        <!-- Timer Dial Widget -->
                        <div class="my-4 flex flex-col items-center justify-center">
                            <div class="relative flex items-center justify-center h-28 w-28 shrink-0">
                                <svg class="w-full h-full transform -rotate-90">
                                    <circle cx="56" cy="56" r="48" stroke="#F7F5F0" stroke-width="6" fill="transparent"/>
                                    <circle id="timer-gauge-ring" cx="56" cy="56" r="48" stroke="#8ECAE6" stroke-width="6" fill="transparent" stroke-dasharray="301.6" stroke-dashoffset="301.6" class="transition-all duration-300 ease-linear"/>
                                </svg>
                                <div class="absolute flex flex-col items-center justify-center">
                                    <span id="timer-countdown-text" class="text-2xl font-extrabold text-brand-slate font-outfit">00:00</span>
                                    <span id="timer-state-label" class="text-[9px] uppercase font-bold tracking-wider text-gray-400">Idle</span>
                                </div>
                            </div>

                            <!-- Duration Selectors -->
                            <div class="flex items-center gap-2 mt-4">
                                <button type="button" onclick="selectTimerPreset(180, 'Neck & Eye Release')" class="preset-btn px-2.5 py-1 rounded-lg bg-[#FAF9F6] border border-gray-200 text-[10px] font-bold text-brand-slate hover:border-brand-sky transition-all">3 Min</button>
                                <button type="button" onclick="selectTimerPreset(300, 'Coffee Stretch')" class="preset-btn px-2.5 py-1 rounded-lg bg-[#FAF9F6] border border-gray-200 text-[10px] font-bold text-brand-slate hover:border-brand-sky transition-all">5 Min</button>
                                <button type="button" onclick="selectTimerPreset(600, 'Mindful Walk')" class="preset-btn px-2.5 py-1 rounded-lg bg-[#FAF9F6] border border-gray-200 text-[10px] font-bold text-brand-slate hover:border-brand-sky transition-all">10 Min</button>
                            </div>
                        </div>

                        <!-- Timer Controls -->
                        <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                            <button type="button" id="timer-start-btn" onclick="toggleTimer()" class="flex-grow py-2.5 rounded-xl bg-brand-sky text-brand-slate font-bold text-xs shadow-sm hover:bg-brand-sky/80 active:scale-95 transition-all text-center" disabled>
                                Select Preset First
                            </button>
                            <button type="button" id="timer-reset-btn" onclick="resetTimer()" class="px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-400 hover:text-brand-slate transition-colors" disabled>
                                Reset
                            </button>
                        </div>
                    </section>
                </div>

                <!-- Caregiver Physical Stretches Deck -->
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-coral"></div>
                    <div class="mb-6">
                        <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate">Caregiver Stretching Deck</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Quick stretching exercises specifically selected to relieve physical caregiving strain.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                        <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-3 hover:shadow-sm transition-all">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-coralLight text-brand-coral text-lg font-bold">🧘‍♂️</div>
                            <h4 class="font-bold text-brand-slate text-sm font-outfit">Shoulder Roll</h4>
                            <p class="text-[11px] text-gray-500 leading-relaxed">Roll your shoulders back and down 10 times in slow, circular motions. Reduces upper back muscle strain from lifting.</p>
                        </div>
                        <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-3 hover:shadow-sm transition-all">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-coralLight text-brand-coral text-lg font-bold">🙆‍♀️</div>
                            <h4 class="font-bold text-brand-slate text-sm font-outfit">Neck Tilt</h4>
                            <p class="text-[11px] text-gray-500 leading-relaxed">Gently tilt your right ear to your right shoulder, holding for 15s. Repeat on the left side. Eases neck stiffness.</p>
                        </div>
                        <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-3 hover:shadow-sm transition-all">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-coralLight text-brand-coral text-lg font-bold">🕺</div>
                            <h4 class="font-bold text-brand-slate text-sm font-outfit">Seated Twist</h4>
                            <p class="text-[11px] text-gray-500 leading-relaxed">Sit straight in a chair. Twist your torso to the left, holding the backrest for 15s, then twist right. Relieves lower spine strain.</p>
                        </div>
                        <div class="p-5 rounded-2xl bg-brand-bg border border-gray-100 space-y-3 hover:shadow-sm transition-all">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-coralLight text-brand-coral text-lg font-bold">👐</div>
                            <h4 class="font-bold text-brand-slate text-sm font-outfit">Wrist Flexor</h4>
                            <p class="text-[11px] text-gray-500 leading-relaxed">Extend your right arm forward, fingers down. Pull fingers back with your left hand, holding 15s. Relieves hand fatigue.</p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Recovery Tools JS -->
            <script>
                // 1. 5-4-3-2-1 Sensory Grounding Slideshow Controller
                let currentGroundingStep = 0;
                function moveGroundingSlide(direction) {
                    const slides = document.querySelectorAll('.grounding-slide');
                    const prevBtn = document.getElementById('grounding-prev-btn');
                    const nextBtn = document.getElementById('grounding-next-btn');

                    // Hide current slide
                    slides[currentGroundingStep].classList.add('hidden');
                    
                    // Increment
                    currentGroundingStep += direction;
                    
                    // Show next slide
                    slides[currentGroundingStep].classList.remove('hidden');

                    // Button status updates
                    prevBtn.disabled = (currentGroundingStep === 0);
                    
                    if (currentGroundingStep === 0) {
                        nextBtn.textContent = 'Start Exercise';
                    } else if (currentGroundingStep === 6) {
                        nextBtn.textContent = 'Reset Exercise';
                    } else if (currentGroundingStep === 5) {
                        nextBtn.textContent = 'Finalize';
                    } else {
                        nextBtn.textContent = 'Next';
                    }

                    // Reset slideshow when clicking "Reset Exercise" on last step
                    if (currentGroundingStep > 6) {
                        slides[6].classList.add('hidden');
                        currentGroundingStep = 0;
                        slides[0].classList.remove('hidden');
                        prevBtn.disabled = true;
                        nextBtn.textContent = 'Start Exercise';
                        // Clear inputs
                        document.querySelectorAll('#grounding-slideshow input').forEach(inp => inp.value = '');
                    }
                }

                // 2. Interactive Circular Respite Timer
                let timerDurationTotal = 0;
                let timerDurationRemaining = 0;
                let timerInterval = null;
                let isTimerRunning = false;
                let currentPresetName = '';

                function selectTimerPreset(seconds, presetName) {
                    resetTimer();
                    
                    timerDurationTotal = seconds;
                    timerDurationRemaining = seconds;
                    currentPresetName = presetName;

                    updateTimerText();
                    updateTimerGauge();

                    const startBtn = document.getElementById('timer-start-btn');
                    startBtn.disabled = false;
                    startBtn.textContent = `Start ${presetName}`;
                    document.getElementById('timer-state-label').textContent = 'Ready';

                    document.querySelectorAll('.preset-btn').forEach(btn => {
                        btn.classList.remove('border-brand-sky', 'bg-brand-skyLight/30');
                    });
                    event.currentTarget.classList.add('border-brand-sky', 'bg-brand-skyLight/30');
                }

                function toggleTimer() {
                    const startBtn = document.getElementById('timer-start-btn');
                    const resetBtn = document.getElementById('timer-reset-btn');
                    const label = document.getElementById('timer-state-label');

                    if (isTimerRunning) {
                        clearInterval(timerInterval);
                        isTimerRunning = false;
                        startBtn.textContent = 'Resume';
                        label.textContent = 'Paused';
                    } else {
                        isTimerRunning = true;
                        startBtn.textContent = 'Pause';
                        resetBtn.disabled = false;
                        label.textContent = currentPresetName;

                        timerInterval = setInterval(() => {
                            timerDurationRemaining--;
                            updateTimerText();
                            updateTimerGauge();

                            if (timerDurationRemaining <= 0) {
                                triggerTimerAlarm();
                                resetTimer();
                                label.textContent = 'Finished';
                            }
                        }, 1000);
                    }
                }

                function resetTimer() {
                    clearInterval(timerInterval);
                    timerInterval = null;
                    isTimerRunning = false;
                    
                    timerDurationRemaining = timerDurationTotal;
                    updateTimerText();
                    updateTimerGauge();

                    const startBtn = document.getElementById('timer-start-btn');
                    const resetBtn = document.getElementById('timer-reset-btn');
                    
                    if (timerDurationTotal > 0) {
                        startBtn.textContent = `Start ${currentPresetName}`;
                        startBtn.disabled = false;
                        document.getElementById('timer-state-label').textContent = 'Ready';
                    } else {
                        startBtn.textContent = 'Select Preset First';
                        startBtn.disabled = true;
                        document.getElementById('timer-state-label').textContent = 'Idle';
                    }
                    resetBtn.disabled = true;
                }

                function updateTimerText() {
                    const mins = Math.floor(timerDurationRemaining / 60);
                    const secs = timerDurationRemaining % 60;
                    document.getElementById('timer-countdown-text').textContent = 
                        `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }

                function updateTimerGauge() {
                    const ring = document.getElementById('timer-gauge-ring');
                    if (timerDurationTotal === 0) {
                        ring.style.strokeDashoffset = 301.6;
                        return;
                    }
                    const ratio = timerDurationRemaining / timerDurationTotal;
                    const offset = 301.6 - (ratio * 301.6);
                    ring.style.strokeDashoffset = offset;
                }

                function triggerTimerAlarm() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                        gain.gain.setValueAtTime(0.5, ctx.currentTime);
                        
                        gain.gain.setValueAtTime(0.5, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                        
                        setTimeout(() => {
                            try {
                                const osc2 = ctx.createOscillator();
                                const gain2 = ctx.createGain();
                                osc2.connect(gain2);
                                gain2.connect(ctx.destination);
                                osc2.type = 'sine';
                                osc2.frequency.setValueAtTime(880, ctx.currentTime);
                                gain2.gain.setValueAtTime(0.5, ctx.currentTime);
                                gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.0);
                                osc2.start();
                                osc2.stop(ctx.currentTime + 1.0);
                            } catch(e) {}
                        }, 500);

                        osc.start();
                        osc.stop(ctx.currentTime + 0.5);
                    } catch(e) {
                        console.error('AudioContext chime failed:', e);
                    }
                    alert(`⏰ Respite Timer Finished: ${currentPresetName} has completed!`);
                }
            </script>
        <?php endif; ?>

        <!-- SUB-TAB CONTENT: CAREGIVER ACADEMY -->
        <?php if ($sub_tab === 'academy'): ?>
            <div class="space-y-8">
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                    <div class="mb-6">
                        <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate">Caregiver Academy</h2>
                        <p class="text-sm text-gray-500 mt-0.5">Micro-learning courses to build psychological resilience and master specialized caregiving skills.</p>
                    </div>

                    <!-- Courses Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Course 1 -->
                        <div class="p-6 rounded-2xl bg-brand-bg border border-gray-100 flex flex-col justify-between hover:shadow-md transition-all">
                            <div class="space-y-3">
                                <span class="px-2.5 py-1 rounded-md bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">Module 1</span>
                                <h4 class="font-bold text-brand-slate text-base font-outfit leading-snug">Cognitive Boundaries in Caregiving</h4>
                                <p class="text-[11px] text-gray-500 leading-relaxed">Learn to manage caregiver guilt, establish mental buffers, and set realistic boundaries with relatives.</p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-150 flex items-center justify-between">
                                <span class="text-[10px] font-bold <?php echo isset($academy_progress['boundaries']) ? 'text-brand-sage' : 'text-gray-400'; ?>">
                                    <?php echo isset($academy_progress['boundaries']) ? '✅ Completed (Score: ' . $academy_progress['boundaries']['score'] . '/3)' : '⏱️ Not Completed'; ?>
                                </span>
                                <button onclick="startCourse('boundaries')" class="px-3.5 py-1.5 rounded-lg bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-[10px] transition-all">
                                    <?php echo isset($academy_progress['boundaries']) ? 'Review' : 'Start'; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Course 2 -->
                        <div class="p-6 rounded-2xl bg-brand-bg border border-gray-100 flex flex-col justify-between hover:shadow-md transition-all">
                            <div class="space-y-3">
                                <span class="px-2.5 py-1 rounded-md bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">Module 2</span>
                                <h4 class="font-bold text-brand-slate text-base font-outfit leading-snug">De-escalating Dementia Agitation</h4>
                                <p class="text-[11px] text-gray-500 leading-relaxed">Practical behavioral workflows to handle evening sundowning and anxious agitation triggers calm.</p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-150 flex items-center justify-between">
                                <span class="text-[10px] font-bold <?php echo isset($academy_progress['dementia']) ? 'text-brand-sage' : 'text-gray-400'; ?>">
                                    <?php echo isset($academy_progress['dementia']) ? '✅ Completed (Score: ' . $academy_progress['dementia']['score'] . '/3)' : '⏱️ Not Completed'; ?>
                                </span>
                                <button onclick="startCourse('dementia')" class="px-3.5 py-1.5 rounded-lg bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-[10px] transition-all">
                                    <?php echo isset($academy_progress['dementia']) ? 'Review' : 'Start'; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Course 3 -->
                        <div class="p-6 rounded-2xl bg-brand-bg border border-gray-100 flex flex-col justify-between hover:shadow-md transition-all">
                            <div class="space-y-3">
                                <span class="px-2.5 py-1 rounded-md bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">Module 3</span>
                                <h4 class="font-bold text-brand-slate text-base font-outfit leading-snug">Effective Respite Planning</h4>
                                <p class="text-[11px] text-gray-500 leading-relaxed">Establish comprehensive checklists for backup coverage and dedicate hours to actual self-recovery.</p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-150 flex items-center justify-between">
                                <span class="text-[10px] font-bold <?php echo isset($academy_progress['respite_planning']) ? 'text-brand-sage' : 'text-gray-400'; ?>">
                                    <?php echo isset($academy_progress['respite_planning']) ? '✅ Completed (Score: ' . $academy_progress['respite_planning']['score'] . '/3)' : '⏱️ Not Completed'; ?>
                                </span>
                                <button onclick="startCourse('respite_planning')" class="px-3.5 py-1.5 rounded-lg bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-[10px] transition-all">
                                    <?php echo isset($academy_progress['respite_planning']) ? 'Review' : 'Start'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Modal overlay -->
            <div id="course-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeCourseModal()"></div>
                <div class="relative bg-white w-full max-w-xl rounded-3xl p-6 md:p-8 shadow-2xl border border-[#EBE8E0] transform scale-95 transition-all duration-300 flex flex-col max-h-[90vh]">
                    <button onclick="closeCourseModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>

                    <div class="overflow-y-auto pr-1 flex-grow">
                        <!-- Course Header -->
                        <div class="mb-4">
                            <span id="modal-course-badge" class="px-2.5 py-1 rounded-md bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">Module 1</span>
                            <h3 id="modal-course-title" class="text-xl font-bold font-outfit text-brand-slate mt-1.5">Cognitive Boundaries</h3>
                        </div>

                        <!-- Slide Reader View -->
                        <div id="modal-reader-view" class="space-y-4">
                            <div id="modal-slide-content" class="p-5 rounded-2xl bg-brand-bg border border-gray-100 text-sm leading-relaxed text-brand-slate min-h-[160px] flex items-center justify-center">
                                <!-- Dynamic slide content -->
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-400 font-bold">
                                <span id="modal-slide-progress">Slide 1 of 3</span>
                                <div class="flex gap-2">
                                    <button type="button" id="modal-prev-slide" onclick="moveSlide(-1)" class="px-3 py-1 rounded-lg border border-gray-200 hover:bg-gray-50 transition-all">Prev</button>
                                    <button type="button" id="modal-next-slide" onclick="moveSlide(1)" class="px-4 py-1 rounded-lg bg-brand-slate text-white hover:bg-brand-slate/90 transition-all">Next</button>
                                </div>
                            </div>
                        </div>

                        <!-- Quiz View (Hidden initially) -->
                        <div id="modal-quiz-view" class="hidden space-y-6">
                            <div class="p-4 rounded-xl bg-brand-skyLight/30 border border-brand-sky/20">
                                <h4 class="text-sm font-bold text-brand-slate">Knowledge Check-in</h4>
                                <p class="text-[11px] text-gray-500">Answer these 3 quick scenarios to complete this academy module.</p>
                            </div>
                            <div id="modal-quiz-questions" class="space-y-6">
                                <!-- Dynamic quiz questions -->
                            </div>
                            <button type="button" onclick="submitQuiz()" class="w-full py-3 rounded-2xl bg-brand-sage text-white font-bold text-xs shadow-md hover:bg-brand-sageHover active:scale-95 transition-all text-center">
                                Submit Quiz Answers
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Data & Academy JS -->
            <script>
                const coursesData = {
                    'boundaries': {
                        'title': 'Cognitive Boundaries in Caregiving',
                        'slides': [
                            "<strong>1. Caregiver Guilt</strong><br><br>Caregiver guilt is natural but harmful. Many caregivers feel guilty when prioritizing their own needs. Remember: Self-care is not selfish; it is a clinical requirement for long-term caregiving sustainability.",
                            "<strong>2. Establishing Buffers</strong><br><br>Establish a daily ' wellbeing buffer'—a minimum of 10 to 15 minutes that is strictly yours. This time is not for chores; it is for cognitive disengagement (walking, reading, stretching).",
                            "<strong>3. Saying 'No'</strong><br><br>Learn to say no to family members who request non-essential help. Communicate your limits clearly: 'I am managing full-time caregiving duties and cannot take on extra commitments at this time.'"
                        ],
                        'quiz': [
                            {
                                'q': 'Which statement best represents a healthy caregiver mindset?',
                                'options': [
                                    'I must manage all care tasks myself to prove I care.',
                                    'Self-care is a clinical requirement for sustainable caregiving.',
                                    'Prioritizing my health is selfish.'
                                ],
                                'answer': 1
                            },
                            {
                                'q': 'What is the primary rule of a Daily Wellbeing Buffer?',
                                'options': [
                                    'It must be at least 4 hours long.',
                                    'It is dedicated to chores and scheduling.',
                                    'It is strictly for personal relaxation and cognitive disengagement.'
                                ],
                                'answer': 2
                            },
                            {
                                'q': 'How should you decline an unrealistic request from a relative?',
                                'options': [
                                    'Accept it but harbor silent resentment.',
                                    'Communicate your caregiving limits clearly and set a boundary.',
                                    'Avoid their calls.'
                                ],
                                'answer': 1
                            }
                        ]
                    },
                    'dementia': {
                        'title': 'De-escalating Dementia Agitation',
                        'slides': [
                            "<strong>1. Understanding Agitation</strong><br><br>Agitation and evening confusion (sundowning) are common dementia behaviors. They are often triggered by fatigue, sensory overload, or confusion about the time of day.",
                            "<strong>2. Environmental Cues</strong><br><br>Reduce environmental triggers: close curtains and turn on bright indoor lights before dusk to avoid shadows, minimize loud TV noises, and stick to a predictable afternoon schedule.",
                            "<strong>3. De-escalation & Redirection</strong><br><br>Never argue or correct confusion. Validate their emotion ('I see you feel anxious') and redirect them to a comforting, repetitive task like folding towels or drinking warm tea."
                        ],
                        'quiz': [
                            {
                                'q': 'What is a common trigger for late-afternoon sundowning?',
                                'options': [
                                    'Getting too much sleep.',
                                    'Fatigue, confusion, and dimming light conditions.',
                                    'Too much physical activity.'
                                ],
                                'answer': 1
                            },
                            {
                                'q': 'How can you minimize evening shadows that confuse dementia patients?',
                                'options': [
                                    'Close curtains and turn on bright indoor lights before dusk.',
                                    'Turn off all lights.',
                                    'Keep the TV on high volume.'
                                ],
                                'answer': 0
                            },
                            {
                                'q': 'What should you do if a patient insists they must go home (when already home)?',
                                'options': [
                                    'Argue and explain they are already home.',
                                    'Validate their feeling of anxiety and redirect them to a comfort activity.',
                                    'Call the doctor immediately.'
                                ],
                                'answer': 1
                            }
                        ]
                    },
                    'respite_planning': {
                        'title': 'Effective Respite Planning',
                        'slides': [
                            "<strong>1. What is Respite?</strong><br><br>Respite is a scheduled temporary break from caregiving. It is a critical buffer designed to prevent severe clinical burnout and restore your capacity to care.",
                            "<strong>2. Handoff Checklists</strong><br><br>Prepare a comprehensive handoff sheet. Document medication times, meal preferences, comfort objects, and emergency contact details for the respite provider.",
                            "<strong>3. Reclaiming Downtime</strong><br><br>Do not spend your respite breaks running errands or cleaning. Reclaim this time for physical rest, sleep, counseling, or engaging in a personal interest."
                        ],
                        'quiz': [
                            {
                                'q': 'What is the primary purpose of a respite break?',
                                'options': [
                                    'To catch up on massive household chores.',
                                    'To prevent clinical burnout and restore caregiver wellbeing.',
                                    'To test the recipient’s independence.'
                                ],
                                'answer': 1
                            },
                            {
                                'q': 'What must be included on a respite provider handoff checklist?',
                                'options': [
                                    'Medication schedules, food rules, and emergency contacts.',
                                    'Long-term family medical history.',
                                    'Financial files.'
                                ],
                                'answer': 0
                            },
                            {
                                'q': 'How should you spend your respite hours?',
                                'options': [
                                    'Watching from the next room.',
                                    'Running stressful errands.',
                                    'Resting, sleeping, or enjoying personal interests.'
                                ],
                                'answer': 2
                            }
                        ]
                    }
                };

                let activeCourseId = '';
                let activeSlideIndex = 0;
                let activeCourseData = null;

                function startCourse(courseId) {
                    activeCourseId = courseId;
                    activeCourseData = coursesData[courseId];
                    activeSlideIndex = 0;

                    document.getElementById('modal-course-title').textContent = activeCourseData.title;
                    document.getElementById('modal-course-badge').textContent = `Academy Module`;

                    renderSlide();

                    document.getElementById('modal-reader-view').classList.remove('hidden');
                    document.getElementById('modal-quiz-view').classList.add('hidden');

                    const modal = document.getElementById('course-modal');
                    modal.classList.remove('opacity-0', 'pointer-events-none');
                    modal.querySelector('.relative').classList.remove('scale-95');
                    modal.querySelector('.relative').classList.add('scale-100');
                }

                function renderSlide() {
                    document.getElementById('modal-slide-content').innerHTML = activeCourseData.slides[activeSlideIndex];
                    document.getElementById('modal-slide-progress').textContent = `Slide ${activeSlideIndex + 1} of ${activeCourseData.slides.length}`;
                    
                    const prevBtn = document.getElementById('modal-prev-slide');
                    const nextBtn = document.getElementById('modal-next-slide');

                    prevBtn.disabled = (activeSlideIndex === 0);
                    
                    if (activeSlideIndex === activeCourseData.slides.length - 1) {
                        nextBtn.textContent = 'Go to Quiz';
                        nextBtn.classList.remove('bg-brand-slate');
                        nextBtn.classList.add('bg-brand-sage');
                    } else {
                        nextBtn.textContent = 'Next';
                        nextBtn.classList.remove('bg-brand-sage');
                        nextBtn.classList.add('bg-brand-slate');
                    }
                }

                function moveSlide(direction) {
                    activeSlideIndex += direction;
                    if (activeSlideIndex >= activeCourseData.slides.length) {
                        startQuiz();
                    } else {
                        renderSlide();
                    }
                }

                function startQuiz() {
                    document.getElementById('modal-reader-view').classList.add('hidden');
                    document.getElementById('modal-quiz-view').classList.remove('hidden');

                    const container = document.getElementById('modal-quiz-questions');
                    container.innerHTML = '';

                    activeCourseData.quiz.forEach((q, qIndex) => {
                        let optionsHtml = '';
                        q.options.forEach((opt, oIndex) => {
                            optionsHtml += `
                                <label class="flex items-start gap-3 p-3 rounded-xl bg-brand-bg hover:bg-brand-sageLight/20 border border-gray-150 cursor-pointer transition-all">
                                    <input type="radio" name="question-${qIndex}" value="${oIndex}" class="h-4.5 w-4.5 mt-0.5 text-brand-sage focus:ring-brand-sage">
                                    <span class="text-xs font-semibold text-brand-slate select-none">${opt}</span>
                                </label>
                            `;
                        });

                        const qHtml = `
                            <div class="space-y-3">
                                <h5 class="text-xs font-bold text-brand-slate font-outfit uppercase tracking-wider">Question ${qIndex + 1}</h5>
                                <p class="text-xs font-bold text-brand-slate leading-relaxed">${q.q}</p>
                                <div class="space-y-2">
                                    ${optionsHtml}
                                </div>
                            </div>
                        `;
                        container.insertAdjacentHTML('beforeend', qHtml);
                    });
                }

                function submitQuiz() {
                    const answersSelected = [];
                    let score = 0;
                    let complete = true;

                    activeCourseData.quiz.forEach((q, qIndex) => {
                        const selected = document.querySelector(`input[name="question-${qIndex}"]:checked`);
                        if (!selected) {
                            complete = false;
                            return;
                        }
                        const val = parseInt(selected.value);
                        answersSelected.push(val);
                        if (val === q.answer) {
                            score++;
                        }
                    });

                    if (!complete) {
                        alert('Please answer all 3 questions first.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'complete_course');
                    formData.append('course_id', activeCourseId);
                    formData.append('score', score);

                    fetch('caregiver_hub.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(`🎉 Quiz Completed!\nScore: ${score}/3\nCourse progress logged successfully.`);
                            closeCourseModal();
                            window.location.reload();
                        } else {
                            alert('Could not submit quiz progress.');
                        }
                    })
                    .catch(err => console.error('Error submitting quiz:', err));
                }

                function closeCourseModal() {
                    const modal = document.getElementById('course-modal');
                    modal.classList.add('opacity-0', 'pointer-events-none');
                    modal.querySelector('.relative').classList.add('scale-95');
                    modal.querySelector('.relative').classList.remove('scale-100');
                }
                </script>
        <?php endif; ?>

        <!-- SUB-TAB CONTENT: PEER MATCHING -->
        <?php if ($sub_tab === 'matching'): ?>
            <div class="space-y-8">
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate">Caregiver Peer Matching</h2>
                            <p class="text-sm text-gray-500 mt-0.5 font-sans leading-relaxed">Connect with fellow caregivers who share your caregiving profile to swap tips, share respite support, and build mutual resilience.</p>
                        </div>
                        <?php if ($user_archetype): ?>
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-brand-sageLight text-brand-sage font-bold text-xs font-outfit shadow-sm self-start md:self-auto">
                                <span class="h-2 w-2 rounded-full bg-brand-sage animate-pulse"></span>
                                Active Profile: <?php 
                                    if ($user_archetype === 'dementia_carer') echo 'Dementia Caregiver';
                                    elseif ($user_archetype === 'stressed_student') echo 'Stressed Student & Carer';
                                    elseif ($user_archetype === 'general_wellbeing') echo 'General Caregiver';
                                    else echo htmlspecialchars($user_archetype);
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$user_archetype): ?>
                        <div class="p-8 rounded-2xl bg-brand-bg border border-gray-150 text-center max-w-lg mx-auto my-6 space-y-4">
                            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage mb-2">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            </div>
                            <h3 class="font-bold text-brand-slate text-base font-outfit">Determine Your Archetype First</h3>
                            <p class="text-xs text-gray-500 leading-relaxed font-sans">
                                Peer matching is based on your caregiver archetype. Please take the <strong>Burnout Tracker</strong> assessment on the first tab or create your <strong>Resilience Plan</strong> to identify your profile.
                            </p>
                            <div class="pt-2">
                                <a href="caregiver_hub.php?tab=respite" class="px-5 py-2.5 rounded-xl bg-brand-slate text-white font-bold text-xs shadow-md hover:bg-brand-slate/90 transition-all font-outfit">
                                    Take Burnout Assessment
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Peer Matches Grid -->
                        <?php if (empty($peer_matches)): ?>
                            <div class="p-8 rounded-2xl bg-brand-bg border border-gray-150 text-center max-w-lg mx-auto my-6 space-y-2">
                                <p class="text-xs text-gray-500 leading-relaxed font-sans">
                                    We couldn't find other caregiver peers matching your exact archetype at the moment. As more caregivers complete their assessments, matches will appear here automatically!
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($peer_matches as $peer): ?>
                                    <?php 
                                        $peer_id = $peer['id'];
                                        $is_connected = in_array($peer_id, $connections);
                                        $peer_name = $peer['name'];
                                        $peer_email = $peer['email'];
                                        $peer_arch = $peer['archetype'];
                                        
                                        // Get initials
                                        $words = explode(' ', $peer_name);
                                        $initials = '';
                                        foreach ($words as $w) {
                                            $initials .= strtoupper(substr($w, 0, 1));
                                        }
                                        $initials = substr($initials, 0, 2);
                                    ?>
                                    <div class="p-6 rounded-2xl bg-[#FAF9F6] border border-gray-150 flex flex-col justify-between hover:shadow-md transition-all relative overflow-hidden">
                                        <div class="flex items-start gap-4">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage font-bold text-sm font-outfit shrink-0 shadow-sm border border-brand-sage/10">
                                                <?php echo htmlspecialchars($initials); ?>
                                            </div>
                                            <div class="space-y-1">
                                                <h4 class="font-bold text-brand-slate text-base font-outfit"><?php echo htmlspecialchars($peer_name); ?></h4>
                                                <span class="inline-block px-2.5 py-0.5 rounded-md bg-white border border-gray-150 text-brand-slate font-bold text-[9px] uppercase tracking-wider font-sans">
                                                    <?php 
                                                        if ($peer_arch === 'dementia_carer') echo 'Dementia Caregiver';
                                                        elseif ($peer_arch === 'stressed_student') echo 'Stressed Student';
                                                        elseif ($peer_arch === 'general_wellbeing') echo 'General Caregiver';
                                                        else echo htmlspecialchars($peer_arch);
                                                    ?>
                                                </span>
                                                <p class="text-[11px] text-gray-500 mt-1 leading-relaxed font-sans">
                                                    Shared interest in coping strategies, daily resilience micro-breaks, and supportive respite coverage swap.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-6 pt-4 border-t border-gray-150 flex items-center justify-between gap-4">
                                            <div class="peer-contact-info-<?php echo $peer_id; ?> text-[10px] text-gray-500 font-bold <?php echo $is_connected ? '' : 'hidden'; ?> font-sans truncate">
                                                📧 <a href="mailto:<?php echo htmlspecialchars($peer_email); ?>" class="underline text-brand-sage hover:text-brand-sageHover transition-colors"><?php echo htmlspecialchars($peer_email); ?></a>
                                            </div>
                                            <div class="peer-status-label-<?php echo $peer_id; ?> text-[10px] font-bold text-gray-400 <?php echo $is_connected ? 'hidden' : ''; ?> font-sans flex items-center gap-1">
                                                <span>⏱️ Pending connection</span>
                                            </div>
                                            
                                            <div class="shrink-0">
                                                <?php if ($is_connected): ?>
                                                    <span class="px-3 py-1.5 rounded-lg bg-[#E8EFEA] text-brand-sage font-bold text-[10px] inline-block font-outfit border border-brand-sage/10">Connected</span>
                                                <?php else: ?>
                                                    <button type="button" onclick="connectPeer(<?php echo $peer_id; ?>, this)" class="px-4 py-1.5 rounded-lg bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-[10px] transition-all font-outfit flex items-center gap-1.5 shadow-sm active:scale-95">
                                                        <span>Connect</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Connect in Community CTA -->
                <div class="p-6 md:p-8 rounded-3xl bg-brand-skyLight/30 border border-brand-sky/20 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="space-y-1">
                        <h4 class="font-bold text-brand-slate text-base font-outfit">Join the Caregiver Discussion Channel</h4>
                        <p class="text-xs text-gray-500 leading-relaxed max-w-xl font-sans">
                            Looking to chat with caregivers of all profiles? Participate in our public channels inside the Community Hub to read updates and share recommendations.
                        </p>
                    </div>
                    <a href="community_hub.php?channel=caregiver-respite" class="px-5 py-2.5 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs shadow-md transition-all text-center shrink-0 font-outfit">
                        Go to Community Hub
                    </a>
                </div>
            </div>

            <!-- Peer Matching JS -->
            <script>
                function connectPeer(peerId, buttonEl) {
                    // Disable button
                    buttonEl.disabled = true;
                    const originalHtml = buttonEl.innerHTML;
                    buttonEl.innerHTML = `
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Connecting...</span>
                    `;

                    const formData = new FormData();
                    formData.append('action', 'connect_peer');
                    formData.append('peer_id', peerId);

                    fetch('caregiver_hub.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Show contact info
                            const contactDiv = document.querySelector(`.peer-contact-info-${peerId}`);
                            if (contactDiv) contactDiv.classList.remove('hidden');

                            // Hide pending label
                            const labelDiv = document.querySelector(`.peer-status-label-${peerId}`);
                            if (labelDiv) labelDiv.classList.add('hidden');

                            // Replace button with Connected badge
                            const parent = buttonEl.parentNode;
                            parent.innerHTML = `<span class="px-3 py-1.5 rounded-lg bg-[#E8EFEA] text-brand-sage font-bold text-[10px] inline-block font-outfit border border-brand-sage/10 animate-fade-in">Connected</span>`;
                            
                            alert('🎉 Peer connection successfully established! You can now contact them.');
                        } else {
                            alert('Could not establish connection. Please try again.');
                            buttonEl.disabled = false;
                            buttonEl.innerHTML = originalHtml;
                        }
                    })
                    .catch(err => {
                        console.error('Error connecting to peer:', err);
                        alert('Could not establish connection due to network error.');
                        buttonEl.disabled = false;
                        buttonEl.innerHTML = originalHtml;
                    });
                }
            </script>
        <?php endif; ?>

    </main>

    <!-- MOBILE NAVIGATION BAR (Sticky Bottom) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-[#EBE8E0] bg-white/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Journal</span>
            </a>
            <a href="caregiver_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Caregiver</span>
            </a>
            <a href="#" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Profile</span>
            </a>
        </div>
    </nav>

    <!-- EMERGENCY SUPPORT MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Crisis Text Line (US/CA)</h4>
                        <p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p>
                    </div>
                    <a href="sms:741741?body=HOME" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-xs text-gray-500">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text SHOUT</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- CLINICAL SAFETY TRIAGE CRISIS MODAL -->
    <?php if (isset($_SESSION['crisis_state']) && $_SESSION['crisis_state'] == 1): ?>
    <div id="crisis-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-md"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/30 transform scale-100 transition-all duration-300">
            
            <div class="flex items-center gap-3.5 mb-5 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-[10px] text-brand-coral font-bold uppercase tracking-wider font-outfit">Un-bypassable safety notice</p>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                Our safety scans detected keywords indicating severe distress in your journal entry. Your life and wellbeing are valuable to us. Please connect with one of these free, confidential support resources immediately:
            </p>

            <div class="space-y-3 mb-6">
                <!-- 988 -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">988 Suicide & Crisis Lifeline</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 988</a>
                </div>

                <!-- Samaritans UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Samaritans Helpline (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 116 123</a>
                </div>

                <!-- Shout UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-3.5 py-1.5 rounded-xl bg-brand-slate text-white font-bold text-[10px] shadow-sm hover:bg-gray-800">Text SHOUT</a>
                </div>
            </div>

            <form method="POST" action="caregiver_hub.php">
                <input type="hidden" name="action" value="clear_crisis">
                
                <label class="flex items-start gap-2.5 mb-4 cursor-pointer select-none text-[10px] text-gray-500 leading-normal">
                    <input type="checkbox" required class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-0.5">
                    <span>I acknowledge these resources and confirm that I am safe or currently seeking appropriate help.</span>
                </label>

                <button type="submit" class="w-full py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-colors text-center">
                    Acknowledge & Dismiss Modal
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- VANILLA JAVASCRIPT -->
    <script>
        // 1. BURNOUT TRACKER STATE MACHINE
        const quizQuestions = [
            {
                q: "How often do you feel physically exhausted from caregiving duties?",
                c: ["Never", "Seldom", "Sometimes", "Often", "Always"]
            },
            {
                q: "Do you feel you lack privacy or time for yourself due to caring?",
                c: ["Never", "Seldom", "Sometimes", "Often", "Always"]
            },
            {
                q: "Are you losing sleep or eating poorly due to care demands?",
                c: ["Never", "Seldom", "Sometimes", "Often", "Always"]
            },
            {
                q: "Do you feel irritable, anxious, or easily frustrated by minor issues?",
                c: ["Never", "Seldom", "Sometimes", "Often", "Always"]
            },
            {
                q: "Are you worried about having enough money or support to cover care expenses?",
                c: ["Never", "Seldom", "Sometimes", "Often", "Always"]
            }
        ];

        let currentQuestionIndex = 0;
        let cumulativeScore = 0;

        const quizCard = document.getElementById('quiz-question-card');
        const questionNumText = document.getElementById('quiz-question-number');
        const questionText = document.getElementById('quiz-question-text');
        const progressIndicator = document.getElementById('quiz-progress-bar');
        const resultContainer = document.getElementById('assessment-result-container');
        const quizContainer = document.getElementById('assessment-quiz-container');

        function selectAnswer(scorePoints) {
            // scorePoints ranges from 1 to 5 (Never=1, Always=5)
            cumulativeScore += scorePoints;

            currentQuestionIndex++;
            if (currentQuestionIndex < quizQuestions.length) {
                // Update progress bar
                const percentage = ((currentQuestionIndex + 1) / quizQuestions.length) * 100;
                progressIndicator.style.width = percentage + '%';

                // Display next question
                questionNumText.innerText = `Question ${currentQuestionIndex + 1} of ${quizQuestions.length}`;
                questionText.innerText = quizQuestions[currentQuestionIndex].q;
            } else {
                // Assessment Complete
                showAssessmentResults();
            }
        }

        function showAssessmentResults() {
            quizContainer.classList.add('hidden');
            resultContainer.classList.remove('hidden');

            // Max points = 25. Level classifications:
            // 5-11: Low, 12-18: Moderate, 19-25: High
            let level = "Low";
            let color = "#5E8C71"; // Sage
            let bgClass = "bg-[#E8EFEA]";
            let textClass = "text-brand-sage";
            let rec = "You are demonstrating healthy boundaries. Keep logging check-ins and prioritizing brief moments of self-care daily.";

            if (cumulativeScore >= 19) {
                level = "High";
                color = "#E76F51"; // Coral
                bgClass = "bg-[#FCEBE6]";
                textClass = "text-brand-coral";
                rec = "Critical burnout warning. Please prioritize taking immediate respite breaks, setting firm boundaries, and contacting our counseling matching team.";
            } else if (cumulativeScore >= 12) {
                level = "Moderate";
                color = "#8ECAE6"; // Sky Blue
                bgClass = "bg-[#E8F4F8]";
                textClass = "text-brand-sky";
                rec = "Moderate fatigue detected. Schedule a block of downtime using our Respite Scheduler and explore peer discussion groups.";
            }

            // Set result texts
            document.getElementById('result-score-text').innerText = cumulativeScore;
            
            const badge = document.getElementById('result-level-badge');
            badge.innerText = `${level} Burnout`;
            badge.className = `inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${bgClass} ${textClass}`;

            document.getElementById('result-recommendation-text').innerText = rec;

            // Update hidden inputs for DB/Session submission
            document.getElementById('db-score-input').value = cumulativeScore;
            document.getElementById('db-level-input').value = level;

            // Animate gauge ring (Stroke dasharray = 339)
            // Stroke dashoffset: 339 is empty, 0 is full.
            // Calculate percentage
            const pct = cumulativeScore / 25;
            const offset = 339 - (pct * 339);
            const ring = document.getElementById('result-gauge-ring');
            ring.setAttribute('stroke', color);
            
            // Trigger delay for smooth visual transition
            setTimeout(() => {
                ring.style.strokeDashoffset = offset;
            }, 100);
        }

        function resetQuiz() {
            currentQuestionIndex = 0;
            cumulativeScore = 0;

            progressIndicator.style.width = '20%';
            questionNumText.innerText = "Question 1 of 5";
            questionText.innerText = quizQuestions[0].q;

            resultContainer.classList.add('hidden');
            quizContainer.classList.remove('hidden');

            const ring = document.getElementById('result-gauge-ring');
            ring.style.strokeDashoffset = '339';
        }

        // 2. MINDFUL BREATHING PLAYER INTERACTIVE ACTIONS
        const playBtn = document.getElementById('audio-play-btn');
        const playIcon = document.getElementById('play-icon');
        const pauseIcon = document.getElementById('pause-icon');
        const timerText = document.getElementById('audio-timer');
        const progressBar = document.getElementById('audio-progress-bar');
        const progressContainer = document.getElementById('audio-progress-container');

        let isPlaying = false;
        let totalSeconds = 300; // 5 minutes
        let elapsedSeconds = 0;
        let playerInterval = null;

        playBtn.addEventListener('click', () => {
            if (isPlaying) {
                // Pause
                clearInterval(playerInterval);
                playIcon.classList.remove('hidden');
                pauseIcon.classList.add('hidden');
                isPlaying = false;
            } else {
                // Play
                playIcon.classList.add('hidden');
                pauseIcon.classList.remove('hidden');
                isPlaying = true;

                playerInterval = setInterval(() => {
                    elapsedSeconds++;
                    if (elapsedSeconds <= totalSeconds) {
                        // Update Progress bar
                        const pct = (elapsedSeconds / totalSeconds) * 100;
                        progressBar.style.width = pct + '%';

                        // Update Timer text
                        const remaining = totalSeconds - elapsedSeconds;
                        const mins = Math.floor(remaining / 60);
                        const secs = remaining % 60;
                        timerText.innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                    } else {
                        // Finished
                        clearInterval(playerInterval);
                        resetPlayer();
                    }
                }, 1000);
            }
        });

        function resetPlayer() {
            isPlaying = false;
            elapsedSeconds = 0;
            progressBar.style.width = '0%';
            timerText.innerText = "05:00";
            playIcon.classList.remove('hidden');
            pauseIcon.classList.add('hidden');
        }

        // Allow clicking seekbar to set progress
        progressContainer.addEventListener('click', (e) => {
            const rect = progressContainer.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const pct = clickX / rect.width;
            elapsedSeconds = Math.floor(pct * totalSeconds);
            progressBar.style.width = (pct * 100) + '%';
            
            const remaining = totalSeconds - elapsedSeconds;
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            timerText.innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        });

        // 3. EMERGENCY SUPPORT MODAL ACTIONS
        const emergencyModal = document.getElementById('emergency-modal');

        function openEmergencyModal() {
            emergencyModal.classList.remove('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-95');
            emergencyModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEmergencyModal() {
            emergencyModal.classList.add('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-100');
            emergencyModal.querySelector('.relative').classList.add('scale-95');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !emergencyModal.classList.contains('opacity-0')) {
                closeEmergencyModal();
            }
        });

        // Speech Transcription Helper
        function initVoiceTranscription(textareaId, buttonId) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                const btn = document.getElementById(buttonId);
                if (btn) btn.style.display = 'none';
                return;
            }
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            
            const textarea = document.getElementById(textareaId);
            const button = document.getElementById(buttonId);
            let isRecording = false;
            
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (isRecording) {
                    recognition.stop();
                } else {
                    recognition.start();
                }
            });
            
            recognition.onstart = () => {
                isRecording = true;
                button.innerHTML = `
                    <svg class="h-3.5 w-3.5 text-brand-coral animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                    </svg>
                    <span class="text-brand-coral font-bold">Listening...</span>
                `;
                button.classList.add('bg-brand-coralLight', 'border-brand-coral/20');
            };
            
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                if (textarea.value) {
                    textarea.value += ' ' + transcript;
                } else {
                    textarea.value = transcript;
                }
                textarea.dispatchEvent(new Event('input'));
            };
            
            recognition.onspeechend = () => {
                recognition.stop();
            };
            
            recognition.onend = () => {
                isRecording = false;
                button.innerHTML = `
                    <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    <span>Transcribe Voice</span>
                `;
                button.classList.remove('bg-brand-coralLight', 'border-brand-coral/20');
            };
            
            recognition.onerror = (event) => {
                console.error("Speech recognition error", event.error);
                recognition.stop();
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            initVoiceTranscription('cover_plan', 'transcribe-cover-plan');
        });
    </script>
</body>
</html>
