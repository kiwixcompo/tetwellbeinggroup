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
        header('Location: caregiver_hub.php');
        exit;
    }

    // Action 1: Save Burnout Assessment
    if ($action === 'log_burnout') {
        $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        $level = filter_input(INPUT_POST, 'level', FILTER_SANITIZE_SPECIAL_CHARS);

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
        $cover_plan = filter_input(INPUT_POST, 'cover_plan', FILTER_SANITIZE_SPECIAL_CHARS);

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
}

// FETCH DATA FOR THE USER
$burnout_logs = [];
$respite_breaks = [];

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
    } catch (PDOException $e) {
        // Fall back to empty or session logs if query errors
    }
}

// Fallback session reading if DB is down or has no records
if (empty($burnout_logs)) {
    $filtered_logs = array_filter($_SESSION['mock_burnout_logs'], function($log) use ($user_id) {
        return $log['user_id'] == $user_id;
    });
    // Sort descending by date
    usort($filtered_logs, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    $burnout_logs = array_slice($filtered_logs, 0, 5);
}

if (empty($respite_breaks)) {
    $filtered_breaks = array_filter($_SESSION['mock_respite_breaks'], function($brk) use ($user_id) {
        return $brk['user_id'] == $user_id;
    });
    // Sort ascending by date
    usort($filtered_breaks, function($a, $b) {
        return strcmp($a['break_date'], $b['break_date']);
    });
    $respite_breaks = $filtered_breaks;
}

$today_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
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
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold">
            <a href="dashboard.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">Teletherapy Hub</a>
        </div>

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
