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

    // Action 3: Submit Client Feedback
    if ($action === 'submit_feedback') {
        $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        $feedback = filter_input(INPUT_POST, 'feedback', FILTER_DEFAULT);
        
        if ($booking_id && $feedback) {
            if ($db_connected && $pdo) {
                try {
                    $upd = $pdo->prepare("UPDATE teletherapy_bookings SET client_feedback = ? WHERE id = ? AND user_id = ?");
                    $upd->execute([$feedback, $booking_id, $user_id]);
                    $success_message = "Feedback submitted successfully.";
                } catch (PDOException $e) {}
            } else {
                if (isset($_SESSION['mock_bookings'])) {
                    foreach ($_SESSION['mock_bookings'] as &$bk) {
                        if (($bk['id'] ?? 0) == $booking_id && ($bk['user_id'] ?? 0) == $user_id) {
                            $bk['client_feedback'] = $feedback;
                            $success_message = "Feedback submitted successfully.";
                            break;
                        }
                    }
                    unset($bk);
                }
            }
        }
    }

    // Action 4: Log Daily Check-in
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

// Daily Routine Logic
$routine_title = "";
$routine_desc = "";
$routine_link = "";
$routine_action = "";
$routine_icon = "";
$routine_color = "";

if ($hour < 12) {
    $routine_title = "Morning Routine";
    $routine_desc = "Start your day with intention. Try 3 minutes of Box Breathing to center yourself.";
    $routine_link = "breathing_exercises.php";
    $routine_action = "Start Breathing";
    $routine_icon = "🌅";
    $routine_color = "brand-sage";
} elseif ($hour < 17) {
    $routine_title = "Afternoon Reset";
    $routine_desc = "Beat the mid-day slump. A quick 6-2 energizing breath will restore your focus.";
    $routine_link = "breathing_exercises.php";
    $routine_action = "Energize Now";
    $routine_icon = "☀️";
    $routine_color = "brand-sky";
} else {
    $routine_title = "Evening Wind Down";
    $routine_desc = "Soothe your nerves before bed. Blend some rain and fire sounds in the Ambient Mixer.";
    $routine_link = "ambient_mixer.php";
    $routine_action = "Open Mixer";
    $routine_icon = "🌙";
    $routine_color = "brand-slate";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <?php include 'nav_menu.php'; ?>

    <!-- MAIN BODY CONTENT (Phase 2 Grid Layout) -->
    <main class="flex-grow mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-12 fade-in">
        
        <!-- HERO SECTION (Full Width) -->
        <div class="mb-8 relative w-full h-[250px] rounded-[2rem] overflow-hidden shadow-soft flex items-center p-8">
            <img src="images/landing_hero.png" class="absolute inset-0 w-full h-full object-cover" alt="Calming Background">
            <div class="absolute inset-0 bg-gradient-to-r from-brand-slate/80 to-transparent"></div>
            
            <div class="relative z-10 text-white max-w-xl">
                <p class="text-sm font-bold tracking-wider uppercase text-brand-sageLight mb-2"><?php echo $today_date; ?></p>
                <h1 class="text-3xl md:text-5xl font-extrabold font-outfit tracking-tight mb-4">
                    <?php echo $greeting; ?>, <?php echo htmlspecialchars($user_name); ?>
                </h1>
                <p class="text-brand-sageLight text-lg">Welcome to your central command center.</p>
            </div>

            <!-- Embedded TET Navigator Card -->
            <div class="hidden lg:block absolute right-8 top-1/2 -translate-y-1/2 w-80 bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 text-white shadow-xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-10 w-10 rounded-full bg-brand-sage flex items-center justify-center">
                        <i class="fa-solid fa-compass"></i>
                    </div>
                    <h3 class="font-bold text-lg">TET Navigator</h3>
                </div>
                <p class="text-sm text-gray-200 mb-4">Next Step: Complete your daily check-in to update your wellbeing score.</p>
                <button class="w-full py-2 bg-white text-brand-slate font-bold rounded-xl hover:bg-brand-sageLight transition-colors">Begin Navigator</button>
            </div>
        </div>

        <!-- TWO COLUMN GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN (60%) -->
            <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-8">
                
                <!-- Daily Summary & Check-in (Re-using existing check-in logic UI) -->
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0]">
                    <h2 class="text-2xl font-bold font-outfit text-brand-slate mb-6">Daily Summary & Check-in</h2>
                    <form method="POST" id="checkin-form" class="space-y-6">
                        <input type="hidden" name="mood_value" id="mood-value" value="">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <button type="button" data-mood="overwhelmed" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">😟</span><span class="text-xs font-semibold">Overwhelmed</span></button>
                            <button type="button" data-mood="low" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">😔</span><span class="text-xs font-semibold">Low</span></button>
                            <button type="button" data-mood="anxious" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">😰</span><span class="text-xs font-semibold">Anxious</span></button>
                            <button type="button" data-mood="exhausted" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">😴</span><span class="text-xs font-semibold">Exhausted</span></button>
                            <button type="button" data-mood="frustrated" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">😡</span><span class="text-xs font-semibold">Frustrated</span></button>
                            <button type="button" data-mood="okay" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">🙂</span><span class="text-xs font-semibold">Okay</span></button>
                            <button type="button" data-mood="grow" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">🌱</span><span class="text-xs font-semibold">Grow</span></button>
                            <button type="button" data-mood="talk" class="mood-btn flex flex-col items-center justify-center p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6] text-gray-500 hover:border-brand-sage hover:bg-white transition-all"><span class="text-3xl mb-2">🤝</span><span class="text-xs font-semibold">Talk</span></button>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="px-6 py-2.5 rounded-2xl bg-brand-sage text-white font-bold text-sm shadow-md hover:bg-brand-sageHover transition-all">Save Check-in</button>
                        </div>
                    </form>
                </section>

                <!-- Wellbeing Passport Widget -->
                <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold text-brand-slate font-outfit mb-2">Wellbeing Passport</h3>
                        <p class="text-sm text-gray-500">Your gamified progress tracker.</p>
                        <a href="wellbeing_passport.php" class="inline-block mt-4 text-sm font-bold text-brand-sage hover:text-brand-sageHover">View Full Passport &rarr;</a>
                    </div>
                    <div class="relative h-24 w-24">
                        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" stroke="#F1EFEA" stroke-width="8" fill="transparent" />
                            <circle cx="50" cy="50" r="40" stroke="#5E8C71" stroke-width="8" fill="transparent" stroke-dasharray="251.2" stroke-dashoffset="60" class="transition-all duration-1000" />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center flex-col">
                            <span class="text-xl font-bold text-brand-slate">Lvl 4</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- RIGHT COLUMN (40%) -->
            <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-8">
                
                <!-- Digital Twin / AI Insights -->
                <section class="bg-gradient-to-br from-brand-slate to-gray-800 rounded-3xl p-6 md:p-8 shadow-2xl text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <i class="fa-solid fa-dna text-9xl"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="h-12 w-12 rounded-xl bg-white/10 flex items-center justify-center backdrop-blur-sm border border-white/20">
                                <img src="images/hub_ai.png" alt="Digital Twin" class="h-8 w-8 object-cover rounded-md opacity-80 mix-blend-screen">
                            </div>
                            <h3 class="text-2xl font-bold font-outfit tracking-tight">AI Insights</h3>
                        </div>
                        <p class="text-sm text-gray-300 mb-6 leading-relaxed">Your Digital Twin has processed your latest data. Your stress patterns indicate a peak on weekends. Consider scheduling a preventative session.</p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm border border-white/10">
                                <span class="block text-xs text-brand-sageLight font-bold uppercase mb-1">Emotional Health Index</span>
                                <span class="text-xl font-bold">78 / 100</span>
                            </div>
                            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm border border-white/10">
                                <span class="block text-xs text-brand-sageLight font-bold uppercase mb-1">Burnout Risk</span>
                                <span class="text-xl font-bold text-green-400">Low</span>
                            </div>
                        </div>

                        <a href="predictive_hub.php" class="block w-full text-center py-3 bg-white text-brand-slate font-bold rounded-xl hover:bg-brand-sageLight transition-colors">
                            Open Digital Twin
                        </a>
                    </div>
                </section>
                
            </div>
            
        </div>
    </main>
    
    <script>
        // Form Logic
        document.querySelectorAll('.mood-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.mood-btn').forEach(b => {
                    b.classList.remove('border-brand-sage', 'bg-brand-sageLight');
                    b.classList.add('border-gray-100', 'bg-[#FAF9F6]');
                });
                btn.classList.remove('border-gray-100', 'bg-[#FAF9F6]');
                btn.classList.add('border-brand-sage', 'bg-brand-sageLight');
                document.getElementById('mood-value').value = btn.getAttribute('data-mood');
            });
        });
    </script>

    <!-- ONBOARDING TOUR MODAL -->
    <div id="onboarding-modal" class="fixed inset-0 z-50 bg-brand-slate/80 backdrop-blur-sm hidden items-center justify-center p-4 opacity-0 transition-opacity duration-500">
        <div class="bg-white rounded-3xl max-w-2xl w-full overflow-hidden shadow-2xl transform scale-95 transition-transform duration-500" id="onboarding-content">
            <div class="relative h-48 bg-brand-sageLight">
                <img src="images/landing_hero.png" class="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-30">
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="h-20 w-20 bg-white rounded-full flex items-center justify-center shadow-lg">
                        <img src="logo.svg" class="h-12 w-auto">
                    </div>
                </div>
            </div>
            
            <div class="p-8 md:p-12">
                <div id="tour-step-1">
                    <span class="text-xs font-bold text-brand-sage uppercase tracking-wider">Welcome to the new</span>
                    <h2 class="text-3xl font-extrabold font-outfit text-brand-slate mt-1 mb-4">TET Wellbeing Architecture</h2>
                    <p class="text-gray-500 mb-6 leading-relaxed">We've completely redesigned your experience to be cleaner, deeper, and more intuitive. Your favorite tools haven't disappeared—they are now neatly organized into 4 core pillars.</p>
                    <button onclick="nextTourStep(2)" class="w-full py-3 bg-brand-sage text-white font-bold rounded-2xl shadow-md hover:bg-brand-sageHover transition-all">Show Me Around</button>
                </div>
                
                <div id="tour-step-2" class="hidden">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="h-12 w-12 rounded-xl bg-brand-sageLight text-brand-sage flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-leaf text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold font-outfit text-brand-slate">MindBloom (Self-Care)</h3>
                            <p class="text-sm text-gray-500 mt-1">Your private sanctuary. You'll find Journaling (TrueVent), Assessments, and Immersive Tools (VR & Ambient) here.</p>
                        </div>
                    </div>
                    <button onclick="nextTourStep(3)" class="w-full mt-6 py-3 bg-brand-slate text-white font-bold rounded-2xl shadow-md hover:bg-gray-700 transition-all">Next</button>
                </div>

                <div id="tour-step-3" class="hidden">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="h-12 w-12 rounded-xl bg-brand-skyLight text-brand-slate flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-users text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold font-outfit text-brand-slate">Community & Care</h3>
                            <p class="text-sm text-gray-500 mt-1">Connect with others and professionals. The Community Feed, Teletherapy, Befrienders, and Caregiver Hub live here.</p>
                        </div>
                    </div>
                    <button onclick="nextTourStep(4)" class="w-full mt-6 py-3 bg-brand-slate text-white font-bold rounded-2xl shadow-md hover:bg-gray-700 transition-all">Next</button>
                </div>

                <div id="tour-step-4" class="hidden">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="h-12 w-12 rounded-xl bg-brand-coralLight text-brand-coral flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-graduation-cap text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold font-outfit text-brand-slate">Learning Center</h3>
                            <p class="text-sm text-gray-500 mt-1">Expand your knowledge. Access TET Academy courses, the Streaming Hub, and the Research Centre.</p>
                        </div>
                    </div>
                    <button onclick="closeTour()" class="w-full mt-6 py-3 bg-brand-sage text-white font-bold rounded-2xl shadow-md hover:bg-brand-sageHover transition-all">Got It, Let's Begin!</button>
                </div>
            </div>
            
            <div class="bg-gray-50 px-8 py-4 border-t border-gray-100 flex justify-center gap-2" id="tour-dots">
                <div class="h-2 w-2 rounded-full bg-brand-sage transition-all" id="dot-1"></div>
                <div class="h-2 w-2 rounded-full bg-gray-300 transition-all" id="dot-2"></div>
                <div class="h-2 w-2 rounded-full bg-gray-300 transition-all" id="dot-3"></div>
                <div class="h-2 w-2 rounded-full bg-gray-300 transition-all" id="dot-4"></div>
            </div>
        </div>
    </div>


    <script>
        // Onboarding Tour Logic
        document.addEventListener('DOMContentLoaded', () => {
            if (!localStorage.getItem('tet_tour_completed')) {
                const modal = document.getElementById('onboarding-modal');
                const content = document.getElementById('onboarding-content');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                
                // Trigger animation
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    content.classList.remove('scale-95');
                }, 50);
            }
        });

        function nextTourStep(step) {
            // Hide all steps
            for(let i=1; i<=4; i++) {
                document.getElementById('tour-step-'+i).classList.add('hidden');
                document.getElementById('dot-'+i).classList.replace('bg-brand-sage', 'bg-gray-300');
            }
            // Show target step
            document.getElementById('tour-step-'+step).classList.remove('hidden');
            document.getElementById('dot-'+step).classList.replace('bg-gray-300', 'bg-brand-sage');
        }

        function closeTour() {
            const modal = document.getElementById('onboarding-modal');
            const content = document.getElementById('onboarding-content');
            
            modal.classList.add('opacity-0');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
                localStorage.setItem('tet_tour_completed', 'true');
            }, 500);
        }
    </script>
</body>

</html>
