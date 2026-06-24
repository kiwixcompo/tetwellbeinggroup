<?php
/**
 * Tet Wellbeing Group - Client Dashboard (dashboard.php)
 * Authenticated member dashboard with daily check-ins and ecosystem placeholders.
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
$user_role = $_SESSION['user_role'] ?? 'client';

// Specialist Routing Guard
if ($user_role === 'specialist') {
    header('Location: specialist_dashboard.php');
    exit;
}

$form_submitted = false;
$submitted_mood = '';
$submitted_notes = '';
$success_message = '';
$error_message = '';

// Fetch User Profile for Onboarding/Archetype check
$user_archetype = null;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT archetype FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_archetype = $stmt->fetchColumn();
    } catch (PDOException $ex) {}
}

if ($user_archetype === null && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $user_archetype = $u['archetype'] ?? null;
            break;
        }
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Onboarding Archetype Save
    if ($action === 'save_onboarding') {
        $archetype = filter_input(INPUT_POST, 'archetype', FILTER_DEFAULT);
        if (in_array($archetype, ['dementia_carer', 'stressed_student', 'general_wellbeing'])) {
            $saved = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET archetype = ? WHERE id = ?");
                    $stmt->execute([$archetype, $user_id]);
                    $saved = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $email => &$u) {
                    if ($u['id'] == $user_id) {
                        $u['archetype'] = $archetype;
                        $saved = true;
                        break;
                    }
                }
                unset($u);
            }
            if ($saved) {
                $user_archetype = $archetype;
                $_SESSION['user_archetype'] = $archetype;
                header('Location: dashboard.php?onboard_success=1');
                exit;
            }
        }
    }

    // Action 2: Dismiss Crisis Helpline Modal
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
        header('Location: dashboard.php?crisis_cleared=1');
        exit;
    }

    // Action 3: Log Daily Check-in
    if (empty($action) && isset($_POST['mood_value'])) {
        $submitted_mood = filter_input(INPUT_POST, 'mood_value', FILTER_DEFAULT);
        $submitted_notes = filter_input(INPUT_POST, 'mood_notes', FILTER_DEFAULT);
        
        if (!empty($submitted_mood)) {
            $form_submitted = true;
            
            // Sprint 2.2 - Clinical Safety Triage RegEx Scan
            if (EmotionalHealthService::checkDistress($submitted_notes)) {
                $_SESSION['crisis_state'] = 1;
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET crisis_state = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                    } catch (PDOException $ex) {}
                }
                if (isset($_SESSION['mock_users'])) {
                    foreach ($_SESSION['mock_users'] as $email => &$mu) {
                        if ($mu['id'] == $user_id) {
                            $mu['crisis_state'] = 1;
                        }
                    }
                }
            }

            // Save to DB
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO daily_checkins (user_id, mood, notes) VALUES (:user_id, :mood, :notes)");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'mood' => $submitted_mood,
                        'notes' => $submitted_notes
                    ]);
                    $success_message = "Your check-in has been logged successfully.";
                } catch (PDOException $ex) {
                    $success_message = "Check-in logged! (Mock connection fallback)";
                }
            } else {
                // Fallback to session
                $_SESSION['mock_checkins'][] = [
                    'user_id' => $user_id,
                    'mood' => $submitted_mood,
                    'notes' => $submitted_notes,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $success_message = "Your mood check-in was saved successfully.";
            }
        } else {
            $error_message = "Unable to save check-in. Please select a mood value.";
        }
    }
}

// Calculate Emotional Health Index (EHI)
$ehi = EmotionalHealthService::calculateIndex($user_id);

// Check for Downward Trend Warning
$downward_trend_detected = EmotionalHealthService::checkDownwardTrend($user_id);

// Check for Predictive Early Warnings
$early_warnings = EmotionalHealthService::detectEarlyWarnings($user_id);

// Dynamic Greeting based on server time
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 17) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
$today_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tet Wellbeing Group</title>
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
                    borderRadius: {
                        '2xl': '1rem',
                        '3xl': '1.5rem'
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
        .mood-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
                <!-- Emergency Support Button -->
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
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
                        <a href="admin_dashboard.php" class="block px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">Admin Console</a>
                        <?php endif; ?>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium font-outfit">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-4xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Welcoming Banner Card (The Quiet Sanctuary) -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-lg">
                <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit"><?php echo $today_date; ?></p>
                <h1 class="text-3xl md:text-4xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    <?php echo $greeting; ?>, <span class="text-brand-sage"><?php echo htmlspecialchars($user_name); ?></span>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed">
                    Welcome to your quiet sanctuary. Take a deep breath, leave the stress outside, and take a moment for yourself. Let's check in with your mind today.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-56 h-36 md:h-36 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/quiet_sanctuary.png" alt="Serene Sanctuary" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold overflow-x-auto whitespace-nowrap pb-1">
            <a href="dashboard.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage font-outfit">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Teletherapy Hub</a>
            <a href="ai_companion.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">AI Companion</a>
            <a href="predictive_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Digital Twin</a>
            <a href="vr_resilience.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">VR Centre</a>
            <a href="workplace_safety.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Workplace Safety</a>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($form_submitted): ?>
            <div id="status-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Check-in Logged</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $success_message; ?></p>
                    <?php if (!empty($submitted_notes)): ?>
                        <div class="mt-2 text-xs border-t border-brand-sage/20 pt-2 text-gray-500 italic">
                            &ldquo;<?php echo htmlspecialchars($submitted_notes); ?>&rdquo;
                        </div>
                    <?php endif; ?>
                </div>
                <button onclick="document.getElementById('status-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div id="error-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Notice</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $error_message; ?></p>
                </div>
                <button onclick="document.getElementById('error-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- DOWNWARD TREND WARNING BANNER -->
        <?php if ($downward_trend_detected): ?>
            <div class="mb-8 flex items-start gap-4 rounded-3xl border border-brand-coral/20 bg-brand-coralLight p-6 text-brand-slate shadow-soft">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-coral text-white text-xl">
                    ⚠️
                </div>
                <div class="flex-grow space-y-1">
                    <h4 class="font-bold text-sm font-outfit text-brand-slate">Wellbeing Notice: Deteriorating Mood Trend</h4>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        We've noticed a consecutive decline in your mood check-ins over the past few days. Your wellbeing is our priority. Consider utilizing some caregiver respite planning toolsets or schedule a virtual session with one of our specialized clinicians.
                    </p>
                    <div class="pt-2 flex flex-wrap gap-2">
                        <a href="caregiver_hub.php" class="px-3.5 py-1.5 bg-white hover:bg-gray-50 border border-gray-200 text-brand-slate rounded-xl text-[10px] font-bold shadow-sm transition-all font-outfit">
                            Explore Respite Hub
                        </a>
                        <a href="teletherapy_hub.php?recommend=1" class="px-3.5 py-1.5 bg-brand-coral hover:bg-brand-coralHover text-white rounded-xl text-[10px] font-bold shadow-sm transition-all font-outfit">
                            Talk to Recommended Specialist
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PREDICTIVE EARLY WARNINGS -->
        <?php if (!empty($early_warnings)): ?>
            <div class="mb-8 space-y-3">
                <?php foreach ($early_warnings as $warn): ?>
                    <div class="flex items-start gap-4 rounded-3xl border border-brand-coral/20 bg-brand-coralLight p-6 text-brand-slate shadow-soft">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-coral text-white text-xl">
                            ⚠️
                        </div>
                        <div class="flex-grow space-y-1">
                            <h4 class="font-bold text-sm font-outfit text-brand-coral"><?php echo htmlspecialchars($warn['title']); ?></h4>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                <?php echo htmlspecialchars($warn['desc']); ?>
                            </p>
                            <div class="pt-2">
                                <a href="<?php echo htmlspecialchars($warn['link']); ?>" class="px-3.5 py-1.5 bg-brand-slate hover:bg-brand-slate/90 text-white rounded-xl text-[10px] font-bold shadow-sm transition-all inline-block font-outfit">
                                    <?php echo htmlspecialchars($warn['action']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CHECK-IN & EMOTIONAL HEALTH INDEX GRID -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <!-- Left: Checkin (Col-span 2) -->
            <div class="md:col-span-2">
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden h-full flex flex-col justify-between">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                    
                    <div>
                        <div class="mb-6 flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xl md:text-2xl font-bold font-outfit text-brand-slate">How are you feeling today?</h2>
                                <p class="text-sm text-gray-500 mt-0.5">Your reflection is private and helps track trends in your wellbeing.</p>
                            </div>
                            <img src="images/blooming_renewal.png" alt="Personal Growth Sprout" class="w-16 h-16 rounded-2xl object-cover shadow-soft border border-brand-sage/15 shrink-0 hidden sm:block">
                        </div>

                        <form method="POST" id="checkin-form" class="space-y-6">
                            <input type="hidden" name="mood_value" id="mood-value" value="">

                            <!-- Mood Grid -->
                            <div class="grid grid-cols-5 gap-2.5 sm:gap-4">
                                
                                <!-- Terrible -->
                                <button type="button" data-mood="terrible" class="mood-btn flex flex-col items-center justify-between p-3 sm:p-4 rounded-2xl border-2 border-gray-100 bg-[#FAF9F6] text-gray-400 hover:border-gray-300 hover:bg-gray-50 hover:text-brand-slate focus:outline-none transition-all">
                                    <div class="mood-svg-container text-gray-400">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 transition-transform duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5" class="svg-face-outline"/>
                                            <path d="M15 16L21 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M33 16L27 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <circle cx="18" cy="23" r="2.5" fill="currentColor"/>
                                            <circle cx="30" cy="23" r="2.5" fill="currentColor"/>
                                            <path d="M18 26C18 28 16.5 29 16.5 30C16.5 31 18 31 18 31" stroke="#8ECAE6" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M17 34C20 30 28 30 31 34" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] sm:text-xs font-semibold mt-3">Terrible</span>
                                </button>

                                <!-- Bad -->
                                <button type="button" data-mood="bad" class="mood-btn flex flex-col items-center justify-between p-3 sm:p-4 rounded-2xl border-2 border-gray-100 bg-[#FAF9F6] text-gray-400 hover:border-gray-300 hover:bg-gray-50 hover:text-brand-slate focus:outline-none transition-all">
                                    <div class="mood-svg-container text-gray-400">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 transition-transform duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5" class="svg-face-outline"/>
                                            <path d="M16 18L21 19.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M32 18L27 19.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <circle cx="18" cy="23" r="2.5" fill="currentColor"/>
                                            <circle cx="30" cy="23" r="2.5" fill="currentColor"/>
                                            <path d="M18 33C21 31 27 31 30 33" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] sm:text-xs font-semibold mt-3">Bad</span>
                                </button>

                                <!-- Okay -->
                                <button type="button" data-mood="okay" class="mood-btn flex flex-col items-center justify-between p-3 sm:p-4 rounded-2xl border-2 border-gray-100 bg-[#FAF9F6] text-gray-400 hover:border-gray-300 hover:bg-gray-50 hover:text-brand-slate focus:outline-none transition-all">
                                    <div class="mood-svg-container text-gray-400">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 transition-transform duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5" class="svg-face-outline"/>
                                            <circle cx="18" cy="23" r="2.5" fill="currentColor"/>
                                            <circle cx="30" cy="23" r="2.5" fill="currentColor"/>
                                            <path d="M17 32H31" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] sm:text-xs font-semibold mt-3">Okay</span>
                                </button>

                                <!-- Good -->
                                <button type="button" data-mood="good" class="mood-btn flex flex-col items-center justify-between p-3 sm:p-4 rounded-2xl border-2 border-gray-100 bg-[#FAF9F6] text-gray-400 hover:border-gray-300 hover:bg-gray-50 hover:text-brand-slate focus:outline-none transition-all">
                                    <div class="mood-svg-container text-gray-400">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 transition-transform duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5" class="svg-face-outline"/>
                                            <circle cx="18" cy="22" r="2.5" fill="currentColor"/>
                                            <circle cx="30" cy="22" r="2.5" fill="currentColor"/>
                                            <path d="M17 30C20 34 28 34 31 30" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] sm:text-xs font-semibold mt-3">Good</span>
                                </button>

                                <!-- Great -->
                                <button type="button" data-mood="great" class="mood-btn flex flex-col items-center justify-between p-3 sm:p-4 rounded-2xl border-2 border-gray-100 bg-[#FAF9F6] text-gray-400 hover:border-gray-300 hover:bg-gray-50 hover:text-brand-slate focus:outline-none transition-all">
                                    <div class="mood-svg-container text-gray-400">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 transition-transform duration-300" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2.5" class="svg-face-outline"/>
                                            <path d="M15 22C16 20 20 20 21 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M27 22C28 20 32 20 33 22" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                            <path d="M16 29C17.5 35.5 30.5 35.5 32 29H16Z" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] sm:text-xs font-semibold mt-3">Great</span>
                                </button>
                            </div>

                            <!-- Conditional notes area -->
                            <div id="reflection-container" class="max-h-0 opacity-0 overflow-hidden transition-all duration-500 ease-in-out">
                                <div class="pt-4 border-t border-[#EBE8E0]">
                                    <div class="flex items-center justify-between mb-2">
                                        <label for="mood-notes" class="block text-xs font-bold text-brand-slate uppercase tracking-wider font-outfit">
                                            Would you like to share what's impacting your mood?
                                        </label>
                                        <button type="button" id="transcribe-mood-notes" class="flex items-center gap-1.5 text-xs font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-3 py-1 rounded-xl border border-brand-sage/10 transition-all select-none">
                                            <svg class="h-3.5 w-3.5 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                            </svg>
                                            <span>Transcribe Voice</span>
                                        </button>
                                    </div>
                                    <textarea 
                                        id="mood-notes" 
                                        name="mood_notes" 
                                        rows="3" 
                                        class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg p-4 text-brand-slate placeholder-gray-400 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 text-sm transition-all"
                                        placeholder="Write your thoughts here..."
                                    ></textarea>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-2xl bg-brand-sage text-white font-bold text-xs shadow-md hover:bg-brand-sageHover active:scale-95 transition-all">
                                        Save Daily Check-in
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>

            <!-- Right: Emotional Health Index (Col-span 1) -->
            <div class="md:col-span-1">
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] h-full flex flex-col justify-between items-center text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-2 bg-brand-sage"></div>
                    
                    <div class="w-full">
                        <h3 class="text-md font-bold font-outfit text-brand-slate mb-1">Emotional Health Index™</h3>
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-wider mb-4">7-Day Real-Time Wellbeing Score</p>
                        
                        <!-- SVG Gauge -->
                        <div class="relative flex items-center justify-center h-32 w-32 mx-auto mb-2">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="40" stroke="#F1EFEA" stroke-width="8" fill="transparent" />
                                <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-width="8" fill="transparent" 
                                    stroke-dasharray="251.2" 
                                    stroke-dashoffset="<?php echo 251.2 - (251.2 * $ehi['score'] / 100); ?>"
                                    class="transition-all duration-1000 ease-out" 
                                    style="stroke: <?php 
                                        if ($ehi['color'] === 'brand-coral') echo '#E76F51';
                                        elseif ($ehi['color'] === 'brand-sky') echo '#8ECAE6';
                                        else echo '#5E8C71'; 
                                    ?>;"
                                />
                            </svg>
                            <div class="absolute flex flex-col items-center">
                                <span class="text-3xl font-extrabold font-outfit text-brand-slate"><?php echo $ehi['score']; ?></span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase mt-0.5">Index</span>
                            </div>
                        </div>
                    </div>

                    <div class="w-full">
                        <!-- Label and Description -->
                        <div class="inline-block px-3 py-0.5 rounded-md text-[10px] font-bold uppercase mb-2 <?php 
                            if ($ehi['color'] === 'brand-coral') echo 'bg-brand-coralLight text-brand-coral';
                            elseif ($ehi['color'] === 'brand-sky') echo 'bg-brand-skyLight text-brand-slate';
                            else echo 'bg-brand-sageLight text-brand-sage'; 
                        ?>">
                            Status: <?php echo $ehi['label']; ?>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-relaxed font-medium">
                            Calculated dynamically based on your mood check-ins and activity engagement.
                        </p>
                        
                        <div class="mt-4 pt-3.5 border-t border-gray-150 grid grid-cols-2 gap-2 text-left">
                            <div>
                                <span class="text-[8px] text-gray-400 font-bold uppercase block">Check-ins</span>
                                <span class="text-xs font-bold text-brand-slate font-outfit"><?php echo $ehi['checkins_count']; ?> logged</span>
                            </div>
                            <div>
                                <span class="text-[8px] text-gray-400 font-bold uppercase block">Interactions</span>
                                <span class="text-xs font-bold text-brand-slate font-outfit"><?php echo ($ehi['respite_count'] + $ehi['posts_count'] + $ehi['burnout_count']); ?> total</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ECOSYSTEM LAYERS -->
        <section class="mt-16">
            <div class="mb-6">
                <h3 class="text-2xl font-bold font-outfit text-brand-slate tracking-tight">Our Core Ecosystem</h3>
                <p class="text-sm text-gray-500 mt-1">Expanding services to provide continuous, high-quality care layers.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Card 1: Caregiver Hub -->
                <div class="relative group bg-white rounded-3xl p-6 shadow-card border border-[#EBE8E0]/70 overflow-hidden transition-all duration-300 hover:shadow-soft hover:-translate-y-1">
                    <div class="absolute top-4 right-4 flex items-center gap-1.5 rounded-full bg-[#E8EFEA] px-2.5 py-1 text-[10px] font-bold text-brand-sage uppercase">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-sage"></span>
                        <span>Active Portal</span>
                    </div>
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sage/10 text-brand-sage">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21a9.01 9.01 0 0 0 2.42-17.5M12 21a9.01 9.01 0 0 1-2.42-17.5M12 21c-1.2 0-2.5-1.7-2.5-5.5S10.8 5 12 5s2.5 1.7 2.5 5.5-1.3 5.5-2.5 5.5z"/><path d="M12 2v2M12 20v2"/></svg>
                    </div>
                    <h4 class="text-lg font-bold font-outfit text-brand-slate">Caregiver Wellbeing Hub</h4>
                    <p class="text-xs text-gray-500 mt-2 leading-relaxed">Dedicated respite toolsets, burnout trackers, and expert-led support structures for family caregivers holding up their loved ones.</p>
                    <a href="caregiver_hub.php" class="mt-6 flex items-center text-xs font-semibold text-brand-sage hover:text-brand-sageHover transition-colors">
                        <span>Enter Caregiver Hub</span>
                        <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>

                <!-- Card 2: Community Support -->
                <div class="relative group bg-white rounded-3xl p-6 shadow-card border border-[#EBE8E0]/70 overflow-hidden transition-all duration-300 hover:shadow-soft hover:-translate-y-1">
                    <div class="absolute top-4 right-4 flex items-center gap-1.5 rounded-full bg-[#E8EFEA] px-2.5 py-1 text-[10px] font-bold text-brand-sage uppercase">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-sage"></span>
                        <span>Active Portal</span>
                    </div>
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sage/10 text-brand-sage">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h4 class="text-lg font-bold font-outfit text-brand-slate">Community & Peer Support</h4>
                    <p class="text-xs text-gray-500 mt-2 leading-relaxed">Safe, fully-moderated sharing circles, thematic interest boards, and anonymous forums ensuring mutual care and social solidarity.</p>
                    <a href="community_hub.php" class="mt-6 flex items-center text-xs font-semibold text-brand-sage hover:text-brand-sageHover transition-colors">
                        <span>Enter Community Hub</span>
                        <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>

                <!-- Card 3: Teletherapy Marketplace -->
                <div class="relative group bg-white rounded-3xl p-6 shadow-card border border-[#EBE8E0]/70 overflow-hidden transition-all duration-300 hover:shadow-soft hover:-translate-y-1">
                    <div class="absolute top-4 right-4 flex items-center gap-1.5 rounded-full bg-[#E8EFEA] px-2.5 py-1 text-[10px] font-bold text-brand-sage uppercase">
                        <span class="h-1.5 w-1.5 rounded-full bg-brand-sage"></span>
                        <span>Active Portal</span>
                    </div>
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sage/10 text-brand-sage">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                    <h4 class="text-lg font-bold font-outfit text-brand-slate">Teletherapy Marketplace</h4>
                    <p class="text-xs text-gray-500 mt-2 leading-relaxed">Connect with qualified, licensed specialists tailored to your demographic, with direct booking mechanisms and insurance compatibility checkups.</p>
                    <a href="teletherapy_hub.php" class="mt-6 flex items-center text-xs font-semibold text-brand-sage hover:text-brand-sageHover transition-colors">
                        <span>Enter Marketplace</span>
                        <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>

            </div>
        </section>

    </main>

    <!-- MOBILE NAVIGATION BAR (Sticky Bottom) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-[#EBE8E0] bg-white/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <!-- Home -->
            <a href="#" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <!-- Journal -->
            <a href="#" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Journal</span>
            </a>
            <!-- Community -->
            <a href="#" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Community</span>
            </a>
            <!-- Profile -->
            <a href="#" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Profile</span>
            </a>
        </div>
    </nav>

    <!-- EMERGENCY MODAL -->
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

    <!-- CONTEXTUAL ONBOARDING MODAL -->
    <?php if ($user_archetype === null): ?>
    <div id="onboarding-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-md"></div>
        <div class="relative bg-white w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl border border-gray-100 transform scale-100 transition-all duration-300">
            <div class="text-center mb-6">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-20 w-auto mx-auto mb-3">
                <h3 class="text-xl font-bold font-outfit text-brand-slate">Personalize Your Quiet Sanctuary</h3>
                <p class="text-xs text-gray-500 mt-1 leading-normal">
                    Please answer a quick question to help us tailor resources and support hubs to your direct care circumstances.
                </p>
            </div>

            <form method="POST" action="dashboard.php" class="space-y-4">
                <input type="hidden" name="action" value="save_onboarding">
                
                <span class="block text-xs font-bold text-brand-slate uppercase tracking-wider font-outfit mb-2">Select the profile that fits you best:</span>
                
                <div class="space-y-3">
                    <!-- Option 1: Dementia Carer -->
                    <label class="flex items-start gap-3 p-4 rounded-2xl border-2 border-gray-100 hover:border-brand-sage hover:bg-brand-sageLight/20 cursor-pointer transition-all select-none">
                        <input type="radio" name="archetype" value="dementia_carer" required class="mt-1 text-brand-sage focus:ring-brand-sage">
                        <div>
                            <span class="block text-xs font-bold text-brand-slate">Dementia Carer</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5 leading-relaxed">
                                Caring for a spouse, parent, or family member dealing with Alzheimers or dementia.
                            </span>
                        </div>
                    </label>

                    <!-- Option 2: Stressed Student -->
                    <label class="flex items-start gap-3 p-4 rounded-2xl border-2 border-gray-100 hover:border-brand-sage hover:bg-brand-sageLight/20 cursor-pointer transition-all select-none">
                        <input type="radio" name="archetype" value="stressed_student" class="mt-1 text-brand-sage focus:ring-brand-sage">
                        <div>
                            <span class="block text-xs font-bold text-brand-slate">Stressed Student</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5 leading-relaxed">
                                Balancing exams, university lectures, research workload, and personal coping pressures.
                            </span>
                        </div>
                    </label>

                    <!-- Option 3: General Wellbeing -->
                    <label class="flex items-start gap-3 p-4 rounded-2xl border-2 border-gray-100 hover:border-brand-sage hover:bg-brand-sageLight/20 cursor-pointer transition-all select-none">
                        <input type="radio" name="archetype" value="general_wellbeing" class="mt-1 text-brand-sage focus:ring-brand-sage">
                        <div>
                            <span class="block text-xs font-bold text-brand-slate">General Wellbeing</span>
                            <span class="block text-[10px] text-gray-500 mt-0.5 leading-relaxed">
                                Seeking standard burnout support, mindfulness toolkits, and daily positive habit routines.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95 text-center">
                        Save Preferences & Enter Hub
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

            <form method="POST" action="dashboard.php">
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
        // Mood Button selection
        const moodButtons = document.querySelectorAll('.mood-btn');
        const moodValueInput = document.getElementById('mood-value');
        const reflectionContainer = document.getElementById('reflection-container');
        const moodNotesArea = document.getElementById('mood-notes');

        moodButtons.forEach(button => {
            button.addEventListener('click', () => {
                const mood = button.getAttribute('data-mood');
                moodValueInput.value = mood;
                
                moodButtons.forEach(btn => {
                    btn.classList.remove('border-brand-sage', 'bg-brand-sageLight', 'text-brand-slate', 'scale-105', 'shadow-active');
                    btn.classList.add('border-gray-100', 'bg-[#FAF9F6]', 'text-gray-400');
                    btn.querySelector('.mood-svg-container').classList.remove('text-brand-sage');
                    btn.querySelector('.mood-svg-container').classList.add('text-gray-400');
                });

                button.classList.remove('border-gray-100', 'bg-[#FAF9F6]', 'text-gray-400');
                button.classList.add('border-brand-sage', 'bg-brand-sageLight', 'text-brand-slate', 'scale-105', 'shadow-active');
                
                const activeSvg = button.querySelector('.mood-svg-container');
                activeSvg.classList.remove('text-gray-400');
                activeSvg.classList.add('text-brand-sage');

                showReflectionBox();
            });
        });

        function showReflectionBox() {
            reflectionContainer.style.maxHeight = '500px';
            reflectionContainer.style.opacity = '1';
            reflectionContainer.style.overflow = 'visible';
            reflectionContainer.classList.remove('opacity-0');
            reflectionContainer.classList.add('mt-6', 'opacity-100');
            setTimeout(() => { moodNotesArea.focus(); }, 300);
        }

        // Emergency Modal actions
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
                    <svg class="h-3 w-3 text-brand-coral animate-pulse" fill="currentColor" viewBox="0 0 24 24">
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
                    <svg class="h-3.5 w-3.5 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
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
            initVoiceTranscription('mood-notes', 'transcribe-mood-notes');
        });
    </script>
</body>
</html>
