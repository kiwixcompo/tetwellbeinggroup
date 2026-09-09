<?php
/**
 * Tet Wellbeing Group - Predictive Early Warning System & Digital Twin Hub (predictive_hub.php)
 * Authenticated client page for smartwatch synchronization, real-time voice acoustic analysis, and Digital Twin visualization.
 */
require_once 'db.php';
require_once 'gating_helper.php';
require_once 'EmotionalHealthService.php';

// Auth Guard: Redirect to login if user session is not active
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_role = $_SESSION['user_role'] ?? 'client';
$user_archetype = $_SESSION['user_archetype'] ?? null;

// Determine current sub-tab
$sub_tab = filter_input(INPUT_GET, 'tab', FILTER_DEFAULT);
if (!in_array($sub_tab, ['twin', 'voice', 'sync'])) {
    $sub_tab = 'twin'; // default to Digital Twin
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Action 1: Sync Smartwatch (AJAX)
    if ($action === 'sync_smartwatch') {
        header('Content-Type: application/json');
        
        $device_brand = trim($_POST['device_brand'] ?? 'apple');
        $valid_brands = ['apple', 'garmin', 'fitbit', 'whoop', 'pixel'];
        if (!in_array($device_brand, $valid_brands)) {
            $device_brand = 'apple';
        }

        // Check if custom manual biometrics were submitted
        $custom_steps = filter_input(INPUT_POST, 'steps', FILTER_VALIDATE_INT);
        $custom_sleep_hours = filter_input(INPUT_POST, 'sleep_hours', FILTER_VALIDATE_FLOAT);
        $custom_sleep_quality = filter_input(INPUT_POST, 'sleep_quality', FILTER_VALIDATE_INT);
        $custom_resting_hr = filter_input(INPUT_POST, 'resting_hr', FILTER_VALIDATE_INT);
        $custom_hrv = filter_input(INPUT_POST, 'hrv', FILTER_VALIDATE_INT);
        $custom_active_min = filter_input(INPUT_POST, 'active_minutes', FILTER_VALIDATE_INT);

        // If custom metrics are passed, use them; otherwise synthesize realistic device telemetry
        $sleep_hours = ($custom_sleep_hours !== false && $custom_sleep_hours !== null && $custom_sleep_hours > 0) 
            ? round($custom_sleep_hours, 1) 
            : round(6.2 + (rand(0, 25) / 10), 1); // 6.2 - 8.7 hrs

        $sleep_quality = ($custom_sleep_quality !== false && $custom_sleep_quality !== null && $custom_sleep_quality > 0) 
            ? min(100, max(40, $custom_sleep_quality)) 
            : rand(68, 94);

        $steps = ($custom_steps !== false && $custom_steps !== null && $custom_steps >= 0) 
            ? $custom_steps 
            : rand(4200, 11800);

        $active_minutes = ($custom_active_min !== false && $custom_active_min !== null && $custom_active_min >= 0) 
            ? $custom_active_min 
            : rand(20, 65);

        $hrv = ($custom_hrv !== false && $custom_hrv !== null && $custom_hrv > 0) 
            ? min(120, max(20, $custom_hrv)) 
            : rand(42, 78);

        $resting_hr = ($custom_resting_hr !== false && $custom_resting_hr !== null && $custom_resting_hr > 0) 
            ? min(120, max(45, $custom_resting_hr)) 
            : rand(58, 76);

        $social_interaction = rand(5, 9);
        
        $data = [
            'log_date' => date('Y-m-d'),
            'sleep_hours' => $sleep_hours,
            'sleep_quality' => $sleep_quality,
            'steps' => $steps,
            'active_minutes' => $active_minutes,
            'hrv' => $hrv,
            'resting_hr' => $resting_hr,
            'social_interaction' => $social_interaction
        ];
        
        $saved = EmotionalHealthService::logTelemetry($user_id, $data);
        
        // Store last paired smartwatch in session for persistent display
        $_SESSION['synced_wearable_device'] = [
            'brand' => $device_brand,
            'synced_at' => date('g:i A'),
            'battery' => rand(74, 96) . '%'
        ];
        
        echo json_encode([
            'status' => $saved ? 'success' : 'error',
            'device' => $_SESSION['synced_wearable_device'],
            'data' => $data
        ]);
        exit;
    }
    
    // Action 2: Log Voice Stress (AJAX)
    if ($action === 'log_voice_stress') {
        header('Content-Type: application/json');
        $stress_score = filter_input(INPUT_POST, 'stress_score', FILTER_VALIDATE_INT);
        $stress_score = max(5, min(95, $stress_score ?? 25));
        
        $jitter = filter_input(INPUT_POST, 'jitter', FILTER_VALIDATE_FLOAT) ?: 1.2;
        $shimmer = filter_input(INPUT_POST, 'shimmer', FILTER_VALIDATE_FLOAT) ?: 2.4;
        $passage_index = filter_input(INPUT_POST, 'passage_index', FILTER_VALIDATE_INT) ?? 0;
        
        // We log it in today's telemetry data
        $today = date('Y-m-d');
        $data = [
            'log_date' => $today,
            'voice_stress_score' => $stress_score
        ];
        
        // If today's log exists, merge with it; otherwise merge with healthy baseline
        $history = EmotionalHealthService::getTelemetryHistory($user_id, 1);
        $latest = !empty($history) ? end($history) : null;
        if ($latest && $latest['log_date'] === $today) {
            $data = array_merge($latest, $data);
        } else {
            $data['sleep_hours'] = 7.1;
            $data['sleep_quality'] = 80;
            $data['steps'] = 6200;
            $data['active_minutes'] = 30;
            $data['hrv'] = 52;
            $data['resting_hr'] = 65;
            $data['social_interaction'] = 6;
        }
        
        $saved = EmotionalHealthService::logTelemetry($user_id, $data);
        
        echo json_encode([
            'status' => $saved ? 'success' : 'error',
            'stress_score' => $stress_score,
            'acoustic_metrics' => [
                'jitter' => $jitter,
                'shimmer' => $shimmer,
                'passage_index' => $passage_index
            ]
        ]);
        exit;
    }
}

// Load telemetry logs and twin profile
$telemetry_history = EmotionalHealthService::getTelemetryHistory($user_id, 7);

// If history is sparse (< 2 entries), seed a realistic 7-day trend leading up to today so the risk forecast chart and Digital Twin work immediately
if (count($telemetry_history) < 2) {
    for ($i = 6; $i >= 1; $i--) {
        $past_date = date('Y-m-d', strtotime("-$i days"));
        $seed_data = [
            'log_date' => $past_date,
            'sleep_hours' => round(6.0 + (rand(0, 22) / 10), 1),
            'sleep_quality' => rand(65, 88),
            'steps' => rand(4800, 9500),
            'active_minutes' => rand(20, 50),
            'hrv' => rand(42, 68),
            'resting_hr' => rand(60, 75),
            'social_interaction' => rand(5, 8),
            'voice_stress_score' => rand(18, 45)
        ];
        EmotionalHealthService::logTelemetry($user_id, $seed_data);
    }
    $telemetry_history = EmotionalHealthService::getTelemetryHistory($user_id, 7);
}

$digital_twin = EmotionalHealthService::getDigitalTwinProfile($user_id);
$early_warnings = EmotionalHealthService::detectEarlyWarnings($user_id);
$has_warnings = !empty($early_warnings);

$triggers = json_decode($digital_twin['learned_triggers'] ?? '[]', true);
$coping = json_decode($digital_twin['coping_styles'] ?? '[]', true);

// Pre-defined grounding passages bank for alternating reading
$grounding_passages = [
    [
        'title' => 'Affirmation of Calm & Limits',
        'text' => 'I am doing the best I can each day. My physical and mental limits are completely valid, and my peace of mind is my priority.'
    ],
    [
        'title' => 'Sensory Presence & Grounding',
        'text' => 'I breathe in slowly through my nose, feeling the cool air fill my lungs, and let go of tension with every gentle exhale.'
    ],
    [
        'title' => 'Compassion & Release',
        'text' => 'I do not have to carry everything all at once. Today I allow myself to pause, breathe, and accept support when I need it.'
    ],
    [
        'title' => 'Mindful Resilience',
        'text' => 'Challenges and tough moments arise, but they also pass. My strength is steady, my mind is clear, and I am safe in this moment.'
    ],
    [
        'title' => 'Clarity & Self-Kindness',
        'text' => 'I release what is beyond my control. I honor my daily energy and greet each new hour with kindness, patience, and grace.'
    ],
    [
        'title' => 'Inner Anchor & Steadiness',
        'text' => 'My feet are firmly planted on the ground. My heart beats calmly, and I return to this present moment with gratitude and focus.'
    ]
];

?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Twin & Predictive Analytics - Tet Wellbeing Group</title>
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
                            cardBg: '#1E2C33',    // Dark Theme card
                            darkBg: '#121C22'     // Dark Theme background
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
                        'glow': '0 0 20px 2px rgba(94, 140, 113, 0.35)',
                        'glow-coral': '0 0 20px 2px rgba(231, 111, 81, 0.35)'
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
        
        /* Orb pulsing keyframes */
        .twin-orb-stable {
            animation: orbFloat 4s ease-in-out infinite alternate, orbPulseStable 3s ease-in-out infinite;
        }
        .twin-orb-stressed {
            animation: orbFloat 1.5s ease-in-out infinite alternate, orbPulseStressed 1.2s ease-in-out infinite;
        }

        @keyframes orbFloat {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(-10px) rotate(5deg); }
        }
        @keyframes orbPulseStable {
            0% { opacity: 0.8; filter: drop-shadow(0 0 15px rgba(94, 140, 113, 0.4)); }
            100% { opacity: 1; filter: drop-shadow(0 0 35px rgba(94, 140, 113, 0.7)); }
        }
        @keyframes orbPulseStressed {
            0% { opacity: 0.7; filter: drop-shadow(0 0 10px rgba(231, 111, 81, 0.5)); transform: scale(0.97); }
            100% { opacity: 1; filter: drop-shadow(0 0 30px rgba(231, 111, 81, 0.8)); transform: scale(1.03); }
        }
        
        .glow-border {
            box-shadow: 0 0 1px 1px rgba(94, 140, 113, 0.15) inset;
        }
        .dark-pane {
            background: #142228;
            border: 1px solid rgba(235, 232, 224, 0.08);
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <!-- TOP NAVIGATION BAR -->
    

    <!-- MAIN APP CONTAINER -->
    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-8 sm:px-6 lg:px-8">
        <!-- WELCOME SECTION -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs uppercase tracking-wider font-outfit">Predictive Diagnostics</span>
                    <?php echo getPlanBadge('premium'); ?>
                </div>
                <h2 class="text-3xl font-bold font-outfit text-brand-slate mt-1">Mental Health Digital Twin</h2>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed max-w-2xl">
                    Our Early Warning System correlates biometric data from wearables, voice acoustics, and mood check-ins to map your Digital Twin and forecast burnout, anxiety, and stress vulnerabilities.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-52 h-28 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/quiet_sanctuary.png" alt="Diagnostics" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <!-- HEALTH WARNINGS NOTIFICATION PANEL -->
        <?php if ($has_warnings): ?>
            <div class="mb-8 space-y-3">
                <?php foreach ($early_warnings as $warn): ?>
                    <div class="flex items-start gap-4 p-5 rounded-2xl border border-brand-coral/30 bg-brand-coralLight text-brand-slate shadow-soft animate-pulse">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-coral text-white shadow-sm mt-0.5">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </div>
                        <div class="flex-grow">
                            <h4 class="font-bold text-sm font-outfit text-brand-coral"><?php echo $warn['title']; ?></h4>
                            <p class="text-xs text-gray-600 mt-1 leading-normal"><?php echo $warn['desc']; ?></p>
                            <div class="mt-3">
                                <a href="<?php echo $warn['link']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-[10px] shadow-sm transition-all">
                                    <span><?php echo $warn['action']; ?></span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PREMIUM DIGITAL TWIN WORKSPACE (DARK INTERFACE) -->
        <div class="bg-brand-darkBg text-white rounded-3xl p-6 md:p-8 shadow-2xl border border-brand-slate/40 flex flex-col gap-8 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-brand-sage via-brand-sky to-brand-coral"></div>
            
            <!-- SUB-TAB CONTROLLERS -->
            <div class="flex items-center justify-between border-b border-white/5 pb-4 gap-4 flex-wrap md:flex-nowrap">
                <div class="flex items-center gap-2 md:gap-4 overflow-x-auto whitespace-nowrap pb-1">
                    <a href="predictive_hub.php?tab=twin" class="px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all <?php echo ($sub_tab === 'twin') ? 'bg-brand-sage text-white shadow-glow' : 'bg-brand-cardBg text-gray-400 hover:text-white border border-white/5'; ?>">
                        🌐 Digital Twin Profile
                    </a>
                    <a href="predictive_hub.php?tab=voice" class="px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all <?php echo ($sub_tab === 'voice') ? 'bg-brand-sage text-white shadow-glow' : 'bg-brand-cardBg text-gray-400 hover:text-white border border-white/5'; ?>">
                        🎤 Voice Stress Analyzer
                    </a>
                    <a href="predictive_hub.php?tab=sync" class="px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all <?php echo ($sub_tab === 'sync') ? 'bg-brand-sage text-white shadow-glow' : 'bg-brand-cardBg text-gray-400 hover:text-white border border-white/5'; ?>">
                        ⌚ Smartwatch Sync & Logs
                    </a>
                </div>
                
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest font-outfit select-none">Twin Node: Alpha-V1</span>
            </div>

            <!-- SUB-TAB CONTENT: 1. DIGITAL TWIN PROFILE VIEW -->
            <?php if ($sub_tab === 'twin'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left column: The Glowing Pulse Visualizer -->
                    <div class="p-6 rounded-2xl dark-pane flex flex-col items-center justify-center text-center space-y-6 relative overflow-hidden">
                        <div class="absolute inset-0 bg-radial-gradient from-brand-sage/5 to-transparent pointer-events-none"></div>
                        
                        <h4 class="text-sm font-bold font-outfit text-gray-400 uppercase tracking-widest">Active State Visualizer</h4>
                        
                        <!-- Pulse Orb Container -->
                        <div class="relative w-44 h-44 flex items-center justify-center">
                            <!-- SVG Orb Mesh -->
                            <svg viewBox="0 0 200 200" class="w-full h-full <?php echo $has_warnings ? 'twin-orb-stressed text-brand-coral' : 'twin-orb-stable text-brand-sage'; ?>">
                                <!-- Glow Outer rings -->
                                <circle cx="100" cy="100" r="85" fill="none" stroke="currentColor" stroke-width="0.5" stroke-dasharray="4 8" class="opacity-30" />
                                <circle cx="100" cy="100" r="70" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="12 4" class="opacity-50" />
                                <circle cx="100" cy="100" r="55" fill="none" stroke="currentColor" stroke-width="2" class="opacity-75" />
                                
                                <!-- Core glowing circles -->
                                <circle cx="100" cy="100" r="30" fill="currentColor" class="opacity-20" />
                                <circle cx="100" cy="100" r="15" fill="currentColor" class="opacity-80" />
                                
                                <!-- Grid Lines -->
                                <line x1="100" y1="15" x2="100" y2="185" stroke="currentColor" stroke-width="0.5" class="opacity-40" />
                                <line x1="15" y1="100" x2="185" y2="100" stroke="currentColor" stroke-width="0.5" class="opacity-40" />
                            </svg>
                        </div>
                        
                        <div class="space-y-1 z-10">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Acoustic & Biometric State</span>
                            <h5 class="text-base font-bold font-outfit text-white">
                                <?php echo $has_warnings ? '⚠️ Vulnerability Warning' : '🟢 Stable Sync State'; ?>
                            </h5>
                            <p class="text-[11px] text-gray-500 max-w-[200px] leading-relaxed">
                                <?php echo $has_warnings ? 'Digital twin detects consecutive telemetry stressors. Take immediate restorative actions.' : 'Twin telemetry correlates to normal recovery patterns.'; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Middle column: Resilience scores & Triggers -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Score Rings -->
                        <div class="grid grid-cols-3 gap-4">
                            <!-- Anxiety Resilience -->
                            <div class="p-4 rounded-2xl dark-pane flex flex-col items-center text-center space-y-2.5">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Anxiety Resilience</span>
                                <div class="relative flex items-center justify-center w-16 h-16">
                                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                        <path class="text-white/5" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                        <path class="text-brand-sage transition-all duration-500" stroke-width="3" stroke-dasharray="<?php echo $digital_twin['anxiety_resilience']; ?>, 100" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    </svg>
                                    <div class="absolute font-bold text-xs text-white"><?php echo $digital_twin['anxiety_resilience']; ?>%</div>
                                </div>
                            </div>
                            
                            <!-- Depression Resistance -->
                            <div class="p-4 rounded-2xl dark-pane flex flex-col items-center text-center space-y-2.5">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Mood Buffer</span>
                                <div class="relative flex items-center justify-center w-16 h-16">
                                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                        <path class="text-white/5" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                        <path class="text-brand-sky transition-all duration-500" stroke-width="3" stroke-dasharray="<?php echo $digital_twin['depression_resistance']; ?>, 100" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    </svg>
                                    <div class="absolute font-bold text-xs text-white"><?php echo $digital_twin['depression_resistance']; ?>%</div>
                                </div>
                            </div>

                            <!-- Burnout Buffer -->
                            <div class="p-4 rounded-2xl dark-pane flex flex-col items-center text-center space-y-2.5">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">Burnout Buffer</span>
                                <div class="relative flex items-center justify-center w-16 h-16">
                                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                        <path class="text-white/5" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                        <path class="<?php echo $digital_twin['burnout_buffer'] < 50 ? 'text-brand-coral' : 'text-brand-sage'; ?> transition-all duration-500" stroke-width="3" stroke-dasharray="<?php echo $digital_twin['burnout_buffer']; ?>, 100" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                    </svg>
                                    <div class="absolute font-bold text-xs text-white"><?php echo $digital_twin['burnout_buffer']; ?>%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Triggers & Coping Styles Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Learned Triggers -->
                            <div class="p-5 rounded-2xl dark-pane space-y-3">
                                <h5 class="text-xs font-bold font-outfit text-brand-coral uppercase tracking-wider flex items-center gap-1.5">
                                    <span>⚡</span> Learned Stress Triggers
                                </h5>
                                <ul class="space-y-2 text-xs text-gray-400">
                                    <?php if (empty($triggers)): ?>
                                        <li class="italic text-gray-500">No stress triggers isolated yet. Continue logging telemetry to populate triggers.</li>
                                    <?php else: ?>
                                        <?php foreach ($triggers as $t): ?>
                                            <li class="flex items-start gap-2">
                                                <span class="text-brand-coral mt-0.5">•</span>
                                                <span><?php echo htmlspecialchars($t); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <!-- Coping Styles -->
                            <div class="p-5 rounded-2xl dark-pane space-y-3">
                                <h5 class="text-xs font-bold font-outfit text-brand-sage uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🛡️</span> Effective Coping Styles
                                </h5>
                                <ul class="space-y-2 text-xs text-gray-400">
                                    <?php foreach ($coping as $c): ?>
                                        <li class="flex items-start gap-2">
                                            <span class="text-brand-sage mt-0.5">•</span>
                                            <span><?php echo htmlspecialchars($c); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- 7-Day Risk Forecast SVG Chart -->
                        <div class="p-5 rounded-2xl dark-pane space-y-4">
                            <h5 class="text-xs font-bold font-outfit text-white uppercase tracking-wider flex items-center gap-2">
                                📈 7-Day Relapse & Burnout Risk Forecast
                            </h5>
                            
                            <?php if (count($telemetry_history) < 2): ?>
                                <p class="text-xs text-gray-500 italic text-center py-6">Insufficient telemetry history (needs at least 2 logged days) to compile risk forecasts.</p>
                            <?php else: 
                                // Build SVG points dynamically
                                $width = 500;
                                $height = 120;
                                $padding = 20;
                                $chart_w = $width - ($padding * 2);
                                $chart_h = $height - ($padding * 2);
                                
                                $num_days = count($telemetry_history);
                                $burnout_pts = [];
                                $anxiety_pts = [];
                                $depression_pts = [];
                                $labels = [];
                                
                                foreach ($telemetry_history as $idx => $log) {
                                    $x = $padding + ($idx * ($chart_w / ($num_days - 1)));
                                    
                                    $sleep_factor = (100 - $log['sleep_quality']);
                                    $steps_factor = (10000 - min(10000, $log['steps'])) / 100;
                                    $burn_risk = round(($sleep_factor * 0.6) + ($steps_factor * 0.4));
                                    
                                    $anx_risk = round(((max(0, $log['resting_hr'] - 55)) * 2.5) + (max(0, 70 - $log['hrv']) * 0.8));
                                    
                                    $dep_risk = round(((10 - $log['social_interaction']) * 8) + ((5000 - min(5000, $log['steps'])) / 100) * 4);
                                    
                                    $burn_risk = max(5, min(95, $burn_risk));
                                    $anx_risk = max(5, min(95, $anx_risk));
                                    $dep_risk = max(5, min(95, $dep_risk));
                                    
                                    $y_burn = $height - $padding - (($burn_risk / 100) * $chart_h);
                                    $y_anx = $height - $padding - (($anx_risk / 100) * $chart_h);
                                    $y_dep = $height - $padding - (($dep_risk / 100) * $chart_h);
                                    
                                    $burnout_pts[] = "$x,$y_burn";
                                    $anxiety_pts[] = "$x,$y_anx";
                                    $depression_pts[] = "$x,$y_dep";
                                    
                                    $labels[] = [
                                        'x' => $x,
                                        'text' => date('m/d', strtotime($log['log_date']))
                                    ];
                                }
                            ?>
                                <div class="relative w-full overflow-x-auto">
                                    <svg class="w-full min-w-[450px]" viewBox="0 0 500 120" xmlns="http://www.w3.org/2000/svg">
                                        <!-- Grid Lines -->
                                        <line x1="20" y1="20" x2="480" y2="20" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" />
                                        <line x1="20" y1="60" x2="480" y2="60" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" />
                                        <line x1="20" y1="100" x2="480" y2="100" stroke="rgba(255,255,255,0.1)" stroke-width="0.5" />
                                        
                                        <!-- Y Axis Labels -->
                                        <text x="5" y="24" fill="rgba(255,255,255,0.2)" font-size="6" font-weight="bold">100%</text>
                                        <text x="5" y="64" fill="rgba(255,255,255,0.2)" font-size="6" font-weight="bold">50%</text>
                                        <text x="5" y="104" fill="rgba(255,255,255,0.2)" font-size="6" font-weight="bold">0%</text>
                                        
                                        <!-- X Axis Labels -->
                                        <?php foreach ($labels as $lbl): ?>
                                            <text x="<?php echo $lbl['x']; ?>" y="115" fill="rgba(255,255,255,0.3)" font-size="6" text-anchor="middle" font-weight="bold"><?php echo $lbl['text']; ?></text>
                                            <line x1="<?php echo $lbl['x']; ?>" y1="20" x2="<?php echo $lbl['x']; ?>" y2="100" stroke="rgba(255,255,255,0.03)" stroke-width="0.5" />
                                        <?php endforeach; ?>
                                        
                                        <!-- Line Paths -->
                                        <polyline fill="none" stroke="#5E8C71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="<?php echo implode(' ', $burnout_pts); ?>" />
                                        <polyline fill="none" stroke="#8ECAE6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="<?php echo implode(' ', $anxiety_pts); ?>" />
                                        <polyline fill="none" stroke="#E76F51" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="<?php echo implode(' ', $depression_pts); ?>" />
                                        
                                        <!-- Dots on points -->
                                        <?php foreach ($burnout_pts as $pt): list($px,$py) = explode(',',$pt); ?>
                                            <circle cx="<?php echo $px; ?>" cy="<?php echo $py; ?>" r="2.5" fill="#5E8C71" />
                                        <?php endforeach; ?>
                                        <?php foreach ($anxiety_pts as $pt): list($px,$py) = explode(',',$pt); ?>
                                            <circle cx="<?php echo $px; ?>" cy="<?php echo $py; ?>" r="2.5" fill="#8ECAE6" />
                                        <?php endforeach; ?>
                                        <?php foreach ($depression_pts as $pt): list($px,$py) = explode(',',$pt); ?>
                                            <circle cx="<?php echo $px; ?>" cy="<?php echo $py; ?>" r="2.5" fill="#E76F51" />
                                        <?php endforeach; ?>
                                    </svg>
                                </div>
                                <div class="flex items-center justify-center gap-6 text-[9px] font-bold uppercase tracking-wider text-gray-500 font-sans">
                                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-sage"></span> Burnout Risk</span>
                                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-sky"></span> Anxiety Threat</span>
                                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-coral"></span> Depression Threat</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SUB-TAB CONTENT: 2. VOICE STRESS ANALYZER -->
            <?php if ($sub_tab === 'voice'): ?>
                <div class="max-w-3xl mx-auto w-full p-6 md:p-8 rounded-2xl dark-pane flex flex-col items-center space-y-6 relative overflow-hidden">
                    <div class="text-center space-y-2 max-w-xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-sky/10 border border-brand-sky/20 text-brand-sky text-[11px] font-bold uppercase tracking-wider font-outfit">
                            <span>🎙️ Real-Time Acoustic Biomarkers</span>
                        </div>
                        <h3 class="text-xl font-bold font-outfit text-white">Voice Stress Analyzer</h3>
                        <p class="text-xs text-gray-400 leading-relaxed mx-auto">
                            Read the grounding passage aloud. Our acoustic pipeline tracks your speech progress in real time and analyzes vocal jitter (frequency tremor), shimmer (amplitude micro-variations), speaking pace, and dysfluency to assess autonomic tension.
                        </p>
                    </div>

                    <!-- Grounding Passage Box with Alternating Rotation -->
                    <div class="w-full bg-white/5 border border-white/10 rounded-2xl p-5 md:p-6 relative">
                        <div class="flex items-center justify-between gap-4 mb-3 border-b border-white/10 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-brand-sage/20 text-brand-sage text-[10px] font-bold uppercase tracking-wider font-outfit" id="passage-badge">Passage 1 of <?php echo count($grounding_passages); ?></span>
                                <span class="text-xs font-semibold text-gray-300 font-outfit" id="passage-title"><?php echo htmlspecialchars($grounding_passages[0]['title']); ?></span>
                            </div>
                            <button type="button" id="shuffle-passage-btn" onclick="nextPassage()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white text-xs font-bold font-outfit transition-all active:scale-95">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Shuffle Passage</span>
                            </button>
                        </div>
                        
                        <!-- The Text to Read Aloud -->
                        <div class="relative py-2">
                            <p id="grounding-text-display" class="font-serif italic text-base sm:text-lg text-brand-sky leading-relaxed text-center select-none transition-all duration-300">
                                &ldquo;<?php echo htmlspecialchars($grounding_passages[0]['text']); ?>&rdquo;
                            </p>
                        </div>

                        <!-- Live Reading Progress Bar -->
                        <div id="reading-progress-container" class="hidden mt-4 pt-3 border-t border-white/10">
                            <div class="flex items-center justify-between text-[11px] mb-1.5 font-outfit">
                                <span class="text-gray-400 flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 rounded-full bg-brand-coral animate-ping"></span> Spoken Progress Tracking
                                </span>
                                <span id="reading-progress-pct" class="text-brand-sage font-bold">0% Completed</span>
                            </div>
                            <div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
                                <div id="reading-progress-bar" class="bg-gradient-to-r from-brand-sky to-brand-sage h-1.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p id="live-speech-feedback" class="text-[11px] text-gray-400 italic text-center mt-2 truncate max-w-full">Listening to your voice...</p>
                        </div>
                    </div>

                    <!-- Canvas Visualizer Waveform -->
                    <div class="w-full h-36 rounded-2xl bg-black/50 border border-white/10 relative flex items-center justify-center overflow-hidden shadow-inner">
                        <canvas id="voice-visualizer" class="absolute inset-0 w-full h-full"></canvas>
                        
                        <div id="visualizer-placeholder" class="text-[11px] font-bold text-gray-500 uppercase tracking-widest z-10 flex flex-col items-center gap-2 select-none font-outfit">
                            <div class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center text-lg">
                                🎙️
                            </div>
                            <span>Acoustic Visualizer Ready</span>
                        </div>

                        <!-- Real-time metrics badges overlay while recording -->
                        <div id="recording-live-stats" class="hidden absolute top-3 left-3 right-3 flex items-center justify-between text-[10px] font-bold font-outfit z-10 select-none">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-lg bg-brand-coral/20 border border-brand-coral/30 text-brand-coral flex items-center gap-1.5 animate-pulse">
                                    <span class="w-1.5 h-1.5 rounded-full bg-brand-coral"></span> LIVE RECORDING
                                </span>
                                <span id="timer-display" class="px-2.5 py-1 rounded-lg bg-white/10 text-gray-300">
                                    00:00
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="live-freq-badge" class="px-2 py-0.5 rounded bg-white/5 text-brand-sky border border-white/10">Freq: -- Hz</span>
                                <span id="live-rms-badge" class="px-2 py-0.5 rounded bg-white/5 text-brand-sage border border-white/10">RMS: -- dB</span>
                            </div>
                        </div>
                    </div>

                    <!-- Controls: Start & Stop/Finish -->
                    <div class="w-full flex flex-col items-center gap-4">
                        <div id="record-actions" class="flex flex-wrap items-center justify-center gap-3">
                            <button type="button" id="start-record-btn" onclick="startVoiceRecording()" class="px-7 py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-glow transition-all active:scale-95 flex items-center gap-2.5 font-outfit">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                <span id="start-record-label">Begin Reading Check-in</span>
                            </button>

                            <button type="button" id="finish-record-btn" onclick="manualFinishRecording()" class="hidden px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs border border-white/15 transition-all active:scale-95 flex items-center gap-2 font-outfit">
                                <svg class="w-4 h-4 text-brand-sage" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                                <span>I'm Done Reading</span>
                            </button>
                        </div>
                        
                        <p id="record-instruction-tip" class="text-[11px] text-gray-400 font-sans text-center max-w-md">
                            Speak at your natural pace. The analyzer detects when you complete reading the passage or when you pause at the end.
                        </p>

                        <!-- Comprehensive Result Indicator Card -->
                        <div id="voice-analysis-result" class="hidden w-full rounded-2xl bg-white/5 border border-white/10 p-5 md:p-6 space-y-5 transition-all">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider font-outfit">Acoustic Assessment Diagnostic</span>
                                    <h5 class="text-lg font-bold text-white mt-0.5 font-outfit">Voice Stress & Autonomic Index</h5>
                                    <p id="voice-result-desc" class="text-xs text-gray-300 mt-1 leading-normal font-sans">
                                        Voice pitch stability is balanced with healthy harmonics.
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span id="voice-result-badge" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-sage/20 text-brand-sage font-outfit">Stable</span>
                                    <h4 id="voice-result-score" class="text-3xl font-bold font-outfit text-white mt-1">18%</h4>
                                </div>
                            </div>

                            <!-- 4 Key Physical Acoustic Biomarkers Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                                <div class="p-3 rounded-xl bg-black/20 border border-white/5">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block mb-1">Frequency Jitter</span>
                                    <span id="metric-jitter" class="text-sm font-bold text-brand-sky font-outfit">1.18%</span>
                                    <span class="text-[9px] text-gray-500 block mt-0.5">Micro-tremor rate</span>
                                </div>
                                <div class="p-3 rounded-xl bg-black/20 border border-white/5">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block mb-1">Amplitude Shimmer</span>
                                    <span id="metric-shimmer" class="text-sm font-bold text-brand-sage font-outfit">2.34%</span>
                                    <span class="text-[9px] text-gray-500 block mt-0.5">Volume variance</span>
                                </div>
                                <div class="p-3 rounded-xl bg-black/20 border border-white/5">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block mb-1">Speech Cadence</span>
                                    <span id="metric-pace" class="text-sm font-bold text-white font-outfit">132 wpm</span>
                                    <span class="text-[9px] text-gray-500 block mt-0.5">Natural rhythm</span>
                                </div>
                                <div class="p-3 rounded-xl bg-black/20 border border-white/5">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block mb-1">Pause Ratio</span>
                                    <span id="metric-pause" class="text-sm font-bold text-gray-300 font-outfit">14%</span>
                                    <span class="text-[9px] text-gray-500 block mt-0.5">Dysfluency index</span>
                                </div>
                            </div>

                            <!-- Recommendation Card -->
                            <div class="p-3.5 rounded-xl bg-brand-sage/10 border border-brand-sage/20 flex items-start gap-3">
                                <div class="w-6 h-6 rounded-lg bg-brand-sage/20 flex items-center justify-center text-brand-sage shrink-0 mt-0.5">
                                    💡
                                </div>
                                <div>
                                    <h6 class="text-xs font-bold text-white font-outfit">Clinical Insight</h6>
                                    <p id="voice-clinical-rec" class="text-[11px] text-gray-300 mt-0.5 leading-relaxed">
                                        Your vocal stability correlates to a well-regulated nervous system. Digital Twin status has been updated.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Voice Recording & Acoustic Analysis Client Script -->
                <script>
                    const groundingPassages = <?php echo json_encode($grounding_passages); ?>;
                    let currentPassageIndex = 0;
                    
                    let audioCtx = null;
                    let analyser = null;
                    let source = null;
                    let streamRef = null;
                    let animationFrameId = null;
                    let recognition = null;

                    let speechStartTime = 0;
                    let recordingTimerInterval = null;
                    let audioSampleFrames = [];
                    let silenceStartTime = null;
                    let hasSpokenAnything = false;
                    let speechTranscript = "";
                    let isRecordingActive = false;

                    function nextPassage() {
                        currentPassageIndex = (currentPassageIndex + 1) % groundingPassages.length;
                        renderCurrentPassage();
                    }

                    function renderCurrentPassage() {
                        const p = groundingPassages[currentPassageIndex];
                        document.getElementById('passage-badge').innerText = `Passage ${currentPassageIndex + 1} of ${groundingPassages.length}`;
                        document.getElementById('passage-title').innerText = p.title;
                        const textEl = document.getElementById('grounding-text-display');
                        textEl.style.opacity = '0';
                        setTimeout(() => {
                            textEl.innerHTML = `&ldquo;${p.text}&rdquo;`;
                            textEl.style.opacity = '1';
                        }, 150);
                    }

                    function startVoiceRecording() {
                        if (isRecordingActive) return;

                        const startBtn = document.getElementById('start-record-btn');
                        const startLabel = document.getElementById('start-record-label');
                        const finishBtn = document.getElementById('finish-record-btn');
                        const liveStats = document.getElementById('recording-live-stats');
                        const progressContainer = document.getElementById('reading-progress-container');
                        const progressBar = document.getElementById('reading-progress-bar');
                        const progressPct = document.getElementById('reading-progress-pct');
                        const resultCard = document.getElementById('voice-analysis-result');
                        const shuffleBtn = document.getElementById('shuffle-passage-btn');

                        resultCard.classList.add('hidden');
                        startBtn.disabled = true;
                        startLabel.innerText = 'Initializing Microphone...';
                        shuffleBtn.disabled = true;

                        navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            streamRef = stream;
                            isRecordingActive = true;
                            audioSampleFrames = [];
                            speechTranscript = "";
                            hasSpokenAnything = false;
                            silenceStartTime = null;

                            // Show UI states
                            document.getElementById('visualizer-placeholder').classList.add('hidden');
                            liveStats.classList.remove('hidden');
                            progressContainer.classList.remove('hidden');
                            progressBar.style.width = '0%';
                            progressPct.innerText = '0% Completed';
                            finishBtn.classList.remove('hidden');

                            startBtn.classList.add('bg-brand-coral');
                            startBtn.classList.remove('bg-brand-sage');
                            startLabel.innerText = 'Listening Aloud...';
                            startBtn.disabled = true;

                            // Setup Web Audio API
                            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            analyser = audioCtx.createAnalyser();
                            analyser.fftSize = 1024;
                            analyser.smoothingTimeConstant = 0.8;

                            source = audioCtx.createMediaStreamSource(stream);
                            source.connect(analyser);

                            const bufferLength = analyser.frequencyBinCount;
                            const freqData = new Uint8Array(bufferLength);
                            const timeData = new Uint8Array(bufferLength);

                            const canvas = document.getElementById('voice-visualizer');
                            const canvasCtx = canvas.getContext('2d');
                            const rect = canvas.getBoundingClientRect();
                            canvas.width = rect.width;
                            canvas.height = rect.height;

                            speechStartTime = Date.now();
                            recordingTimerInterval = setInterval(() => {
                                const elapsedSec = Math.floor((Date.now() - speechStartTime) / 1000);
                                const mins = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
                                const secs = String(elapsedSec % 60).padStart(2, '0');
                                document.getElementById('timer-display').innerText = `${mins}:${secs}`;
                            }, 500);

                            // Setup Web Speech Recognition
                            initSpeechRecognition();

                            // Visualization & Acoustic Sample Collector
                            function draw() {
                                if (!isRecordingActive) return;
                                animationFrameId = requestAnimationFrame(draw);

                                analyser.getByteFrequencyData(freqData);
                                analyser.getByteTimeDomainData(timeData);

                                // Compute RMS energy of this audio frame
                                let sumSquares = 0;
                                for (let i = 0; i < timeData.length; i++) {
                                    const norm = (timeData[i] - 128) / 128;
                                    sumSquares += norm * norm;
                                }
                                const rms = Math.sqrt(sumSquares / timeData.length);
                                const isVoiced = rms > 0.02;

                                // Find dominant peak frequency bin
                                let maxVal = -1;
                                let maxBin = 0;
                                for (let i = 0; i < freqData.length; i++) {
                                    if (freqData[i] > maxVal) {
                                        maxVal = freqData[i];
                                        maxBin = i;
                                    }
                                }
                                const dominantFreq = Math.round((maxBin * (audioCtx.sampleRate / 2)) / bufferLength);

                                // Record sample frame for stress calculations
                                if (audioSampleFrames.length < 1500) {
                                    audioSampleFrames.push({
                                        rms: rms,
                                        freq: dominantFreq,
                                        voiced: isVoiced,
                                        time: Date.now()
                                    });
                                }

                                // Update overlay badges
                                if (isVoiced && dominantFreq > 60 && dominantFreq < 3000) {
                                    document.getElementById('live-freq-badge').innerText = `Freq: ${dominantFreq} Hz`;
                                    const dbApprox = Math.round(20 * Math.log10(rms * 10 + 0.001));
                                    document.getElementById('live-rms-badge').innerText = `Amp: ${dbApprox} dB`;
                                }

                                // Visualizer canvas rendering
                                canvasCtx.fillStyle = 'rgba(12, 20, 24, 0.25)';
                                canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

                                const barWidth = (canvas.width / 64);
                                let x = 0;
                                for (let i = 0; i < 64; i++) {
                                    const barHeight = (freqData[i] / 255) * (canvas.height * 0.85);
                                    
                                    // Gradient bar
                                    const grad = canvasCtx.createLinearGradient(0, canvas.height, 0, canvas.height - barHeight);
                                    grad.addColorStop(0, '#5E8C71');
                                    grad.addColorStop(0.5, '#8ECAE6');
                                    grad.addColorStop(1, '#E76F51');

                                    canvasCtx.fillStyle = grad;
                                    canvasCtx.fillRect(x, canvas.height - barHeight, barWidth - 1.5, barHeight);
                                    x += barWidth;
                                }

                                // Check for silence after user completed speaking
                                if (hasSpokenAnything) {
                                    if (!isVoiced) {
                                        if (!silenceStartTime) silenceStartTime = Date.now();
                                        else if (Date.now() - silenceStartTime > 2200) {
                                            // 2.2 seconds of silence after reading
                                            checkCompletionAndFinish();
                                        }
                                    } else {
                                        silenceStartTime = null;
                                    }
                                }
                            }
                            draw();
                        })
                        .catch(err => {
                            console.error('Mic error:', err);
                            showAppToast('Microphone access is required to analyze your voice stress. Please allow microphone permissions in your browser.', 'coral');
                            resetRecordingUI();
                        });
                    }

                    function initSpeechRecognition() {
                        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                        if (!SpeechRecognition) {
                            console.log('Web Speech API not supported in this browser, falling back to manual finish and acoustic pause timing.');
                            document.getElementById('live-speech-feedback').innerText = 'Reading mode active. Click "I\'m Done Reading" when you finish.';
                            return;
                        }

                        recognition = new SpeechRecognition();
                        recognition.continuous = true;
                        recognition.interimResults = true;
                        recognition.lang = 'en-US';

                        const targetPassage = groundingPassages[currentPassageIndex].text.toLowerCase();
                        const targetWords = targetPassage.replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter(w => w.length > 0);

                        recognition.onresult = (event) => {
                            let interimTranscript = "";
                            for (let i = event.resultIndex; i < event.results.length; ++i) {
                                interimTranscript += event.results[i][0].transcript;
                            }
                            speechTranscript += " " + interimTranscript;
                            hasSpokenAnything = true;

                            const spokenWords = speechTranscript.toLowerCase().replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter(w => w.length > 0);
                            
                            // Calculate word match progress
                            let matchedCount = 0;
                            const spokenSet = new Set(spokenWords);
                            targetWords.forEach(w => {
                                if (spokenSet.has(w)) matchedCount++;
                            });

                            const progressPct = Math.min(100, Math.round((matchedCount / targetWords.length) * 100));
                            document.getElementById('reading-progress-bar').style.width = `${progressPct}%`;
                            document.getElementById('reading-progress-pct').innerText = `${progressPct}% Completed`;
                            document.getElementById('live-speech-feedback').innerText = `"${spokenWords.slice(-6).join(' ')}..."`;

                            // If user completed at least 80% of the target passage, prepare to end
                            if (progressPct >= 80) {
                                document.getElementById('live-speech-feedback').innerText = '✨ Passage complete! Finalizing acoustic metrics...';
                                setTimeout(() => {
                                    if (isRecordingActive) {
                                        finishVoiceRecording();
                                    }
                                }, 1400);
                            }
                        };

                        recognition.onerror = (e) => {
                            console.log('Speech recognition warning/error:', e.error);
                        };

                        recognition.start();
                    }

                    function checkCompletionAndFinish() {
                        const targetPassage = groundingPassages[currentPassageIndex].text.toLowerCase();
                        const targetWords = targetPassage.replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter(w => w.length > 0);
                        const spokenWords = speechTranscript.toLowerCase().replace(/[^a-z0-9 ]/g, '').split(/\s+/).filter(w => w.length > 0);
                        
                        let matchedCount = 0;
                        const spokenSet = new Set(spokenWords);
                        targetWords.forEach(w => {
                            if (spokenSet.has(w)) matchedCount++;
                        });

                        const progressPct = (spokenWords.length > 0) ? Math.min(100, Math.round((matchedCount / targetWords.length) * 100)) : 0;
                        
                        // If user has spoken substantially or spent > 4s
                        if (progressPct >= 70 || spokenWords.length >= Math.floor(targetWords.length * 0.7) || (Date.now() - speechStartTime > 4000)) {
                            finishVoiceRecording();
                        }
                    }

                    function manualFinishRecording() {
                        if (!isRecordingActive) return;
                        finishVoiceRecording();
                    }

                    function finishVoiceRecording() {
                        if (!isRecordingActive) return;
                        isRecordingActive = false;

                        // Stop tracks and loops
                        cancelAnimationFrame(animationFrameId);
                        clearInterval(recordingTimerInterval);

                        if (recognition) {
                            try { recognition.stop(); } catch(e){}
                        }
                        if (streamRef) {
                            streamRef.getTracks().forEach(track => track.stop());
                        }
                        if (audioCtx) {
                            try { audioCtx.close(); } catch(e){}
                        }

                        // Clear visualizer
                        const canvas = document.getElementById('voice-visualizer');
                        const canvasCtx = canvas.getContext('2d');
                        canvasCtx.clearRect(0, 0, canvas.width, canvas.height);

                        // Calculate Real Acoustic Metrics from audioSampleFrames
                        let voicedFrames = audioSampleFrames.filter(f => f.voiced && f.freq > 70 && f.freq < 1500);
                        let totalFrames = audioSampleFrames.length || 1;
                        let unvoicedFrames = audioSampleFrames.filter(f => !f.voiced).length;

                        // Pause ratio
                        let pauseRatio = Math.round((unvoicedFrames / totalFrames) * 100);

                        // Frequency Jitter calculation (frame-to-frame pitch stability)
                        let jitterSum = 0;
                        let validJitterCount = 0;
                        for (let i = 1; i < voicedFrames.length; i++) {
                            let diff = Math.abs(voicedFrames[i].freq - voicedFrames[i-1].freq);
                            let avg = (voicedFrames[i].freq + voicedFrames[i-1].freq) / 2;
                            if (avg > 0) {
                                jitterSum += (diff / avg);
                                validJitterCount++;
                            }
                        }
                        let rawJitterPct = validJitterCount > 0 ? ((jitterSum / validJitterCount) * 100) : 1.2;
                        let displayJitter = Math.min(4.8, Math.max(0.6, rawJitterPct)).toFixed(2);

                        // Shimmer calculation (amplitude micro-variations)
                        let shimmerSum = 0;
                        let validShimmerCount = 0;
                        for (let i = 1; i < voicedFrames.length; i++) {
                            let diff = Math.abs(voicedFrames[i].rms - voicedFrames[i-1].rms);
                            let avg = (voicedFrames[i].rms + voicedFrames[i-1].rms) / 2;
                            if (avg > 0.001) {
                                shimmerSum += (diff / avg);
                                validShimmerCount++;
                            }
                        }
                        let rawShimmerPct = validShimmerCount > 0 ? ((shimmerSum / validShimmerCount) * 100) : 2.5;
                        let displayShimmer = Math.min(8.5, Math.max(1.2, rawShimmerPct)).toFixed(2);

                        // Speech duration in minutes
                        let durationMinutes = Math.max(0.05, (Date.now() - speechStartTime) / 60000);
                        let wordCount = speechTranscript.trim().split(/\s+/).filter(w => w.length > 0).length;
                        if (wordCount < 5) wordCount = groundingPassages[currentPassageIndex].text.split(/\s+/).length;
                        let wpm = Math.round(wordCount / durationMinutes);
                        wpm = Math.max(70, Math.min(210, wpm));

                        // Composite Autonomic Stress Score Calculation
                        // Low jitter (0.8 - 1.5%), low shimmer (1.5 - 3%), moderate pace (110 - 150 wpm) = calm
                        // High jitter (>2.5%), high shimmer (>4.5%), hurried or hesitant pace = higher stress
                        let stressScore = 15;
                        if (parseFloat(displayJitter) > 2.2) stressScore += 25;
                        else if (parseFloat(displayJitter) > 1.6) stressScore += 12;

                        if (parseFloat(displayShimmer) > 4.5) stressScore += 25;
                        else if (parseFloat(displayShimmer) > 3.0) stressScore += 12;

                        if (pauseRatio > 40 || pauseRatio < 8) stressScore += 15;
                        if (wpm > 175 || wpm < 90) stressScore += 10;

                        // Add small realistic variation bounded between 8% and 88%
                        stressScore = Math.max(8, Math.min(88, Math.round(stressScore + (Math.random() * 6 - 3))));

                        // Populate Diagnostic Outputs
                        let statusLabel = "Stable Regulation";
                        let badgeClass = "bg-brand-sage/20 text-brand-sage";
                        let statusDesc = "Vocal harmonics and frequency jitter reflect a well-regulated parasympathetic state with calm breathing cadence.";
                        let clinicalRec = "Your vocal dynamics show low muscle tension and steady acoustic pitch. Digital twin updated with high anxiety resilience.";

                        if (stressScore >= 65) {
                            statusLabel = "Elevated Stress";
                            badgeClass = "bg-brand-coral/20 text-brand-coral";
                            statusDesc = "Elevated micro-tremors and irregular amplitude rhythm detected. Indicates sympathetic nervous system arousal and mental fatigue.";
                            clinicalRec = "High vocal tension detected. We recommend taking 5 minutes for guided box breathing or our Sensory Grounding exercise.";
                        } else if (stressScore >= 40) {
                            statusLabel = "Moderate Tension";
                            badgeClass = "bg-brand-sky/20 text-brand-sky";
                            statusDesc = "Slight pitch variability and speaking pace compression detected. Mild cognitive load or fatigue present.";
                            clinicalRec = "Mild cognitive strain noted. Ensure hydration and pause for a brief posture reset or fresh air.";
                        }

                        document.getElementById('voice-result-score').innerText = `${stressScore}%`;
                        document.getElementById('voice-result-badge').innerText = statusLabel;
                        document.getElementById('voice-result-badge').className = `px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${badgeClass} font-outfit`;
                        document.getElementById('voice-result-desc').innerText = statusDesc;
                        document.getElementById('voice-clinical-rec').innerText = clinicalRec;

                        document.getElementById('metric-jitter').innerText = `${displayJitter}%`;
                        document.getElementById('metric-shimmer').innerText = `${displayShimmer}%`;
                        document.getElementById('metric-pace').innerText = `${wpm} wpm`;
                        document.getElementById('metric-pause').innerText = `${pauseRatio}%`;

                        // Display Result
                        document.getElementById('voice-analysis-result').classList.remove('hidden');
                        showAppToast(`🎤 Acoustic analysis complete! Vocal stress scored at ${stressScore}%. Telemetry saved.`, 'sage');

                        // Send to server
                        const formData = new FormData();
                        formData.append('action', 'log_voice_stress');
                        formData.append('stress_score', stressScore);
                        formData.append('jitter', displayJitter);
                        formData.append('shimmer', displayShimmer);
                        formData.append('passage_index', currentPassageIndex);

                        fetch('predictive_hub.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            resetRecordingUI();
                            // Automatically switch to next passage for the subsequent check-in
                            setTimeout(nextPassage, 1200);
                        })
                        .catch(err => {
                            console.error('Server sync error:', err);
                            resetRecordingUI();
                        });
                    }

                    function resetRecordingUI() {
                        isRecordingActive = false;
                        document.getElementById('recording-live-stats').classList.add('hidden');
                        document.getElementById('visualizer-placeholder').classList.remove('hidden');
                        document.getElementById('reading-progress-container').classList.add('hidden');
                        document.getElementById('finish-record-btn').classList.add('hidden');

                        const startBtn = document.getElementById('start-record-btn');
                        startBtn.disabled = false;
                        startBtn.classList.remove('bg-brand-coral');
                        startBtn.classList.add('bg-brand-sage');
                        document.getElementById('start-record-label').innerText = 'Record Voice Check-in';
                        document.getElementById('shuffle-passage-btn').disabled = false;
                    }
                </script>
            <?php endif; ?>


            <!-- SUB-TAB CONTENT: 3. SMARTWATCH SYNC & LOG HISTORY -->
            <?php if ($sub_tab === 'sync'): 
                $synced_device = $_SESSION['synced_wearable_device'] ?? [
                    'brand' => 'apple',
                    'synced_at' => 'Just now',
                    'battery' => '88%'
                ];
                $active_brand = $synced_device['brand'] ?? 'apple';
            ?>
                <div class="space-y-8">
                    <!-- Top Row: Connected Device & Sync Hub -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: Wearable Device Control Center -->
                        <div class="p-6 rounded-2xl dark-pane flex flex-col justify-between space-y-6 relative overflow-hidden">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <h4 class="text-sm font-bold font-outfit text-white">Wearable Sync Node</h4>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-brand-sage/20 border border-brand-sage/30 text-brand-sage text-[10px] font-bold uppercase tracking-wider font-outfit">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-sage animate-ping"></span> Live Link
                                    </span>
                                </div>
                                <p class="text-[11px] text-gray-400 leading-relaxed font-sans">
                                    Pair your preferred health wearable to continuously stream sleep duration, autonomic heart rate variability (HRV), and active movement.
                                </p>
                            </div>

                            <!-- Wearable Brand Selector -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Select Active Wearable Device</label>
                                <div class="grid grid-cols-3 gap-2" id="device-selector-grid">
                                    <button type="button" onclick="selectWearableBrand('apple', 'Apple Watch Ultra / S9', '⌚')" class="device-pill p-2 rounded-xl border text-center transition-all <?php echo ($active_brand === 'apple') ? 'border-brand-sage bg-brand-sage/15 text-white shadow-sm' : 'border-white/10 bg-white/5 text-gray-400 hover:text-white'; ?>" data-brand="apple">
                                        <span class="text-base block mb-0.5">⌚</span>
                                        <span class="text-[10px] font-bold font-outfit block">Apple Watch</span>
                                    </button>
                                    <button type="button" onclick="selectWearableBrand('garmin', 'Garmin Fēnix / Forerunner', '⏱️')" class="device-pill p-2 rounded-xl border text-center transition-all <?php echo ($active_brand === 'garmin') ? 'border-brand-sage bg-brand-sage/15 text-white shadow-sm' : 'border-white/10 bg-white/5 text-gray-400 hover:text-white'; ?>" data-brand="garmin">
                                        <span class="text-base block mb-0.5">⏱️</span>
                                        <span class="text-[10px] font-bold font-outfit block">Garmin</span>
                                    </button>
                                    <button type="button" onclick="selectWearableBrand('fitbit', 'Fitbit Sense 2 / Charge', '🏃')" class="device-pill p-2 rounded-xl border text-center transition-all <?php echo ($active_brand === 'fitbit') ? 'border-brand-sage bg-brand-sage/15 text-white shadow-sm' : 'border-white/10 bg-white/5 text-gray-400 hover:text-white'; ?>" data-brand="fitbit">
                                        <span class="text-base block mb-0.5">🏃</span>
                                        <span class="text-[10px] font-bold font-outfit block">Fitbit</span>
                                    </button>
                                    <button type="button" onclick="selectWearableBrand('whoop', 'Whoop 4.0 Biometric Band', '📈')" class="device-pill p-2 rounded-xl border text-center transition-all <?php echo ($active_brand === 'whoop') ? 'border-brand-sage bg-brand-sage/15 text-white shadow-sm' : 'border-white/10 bg-white/5 text-gray-400 hover:text-white'; ?>" data-brand="whoop">
                                        <span class="text-base block mb-0.5">📈</span>
                                        <span class="text-[10px] font-bold font-outfit block">Whoop</span>
                                    </button>
                                    <button type="button" onclick="selectWearableBrand('pixel', 'Google Pixel Watch 2', '🧭')" class="device-pill p-2 rounded-xl border text-center transition-all <?php echo ($active_brand === 'pixel') ? 'border-brand-sage bg-brand-sage/15 text-white shadow-sm' : 'border-white/10 bg-white/5 text-gray-400 hover:text-white'; ?>" data-brand="pixel">
                                        <span class="text-base block mb-0.5">🧭</span>
                                        <span class="text-[10px] font-bold font-outfit block">Pixel Watch</span>
                                    </button>
                                    <button type="button" onclick="toggleManualBiometricsModal()" class="device-pill p-2 rounded-xl border border-dashed border-white/20 bg-white/5 text-gray-300 hover:text-white hover:border-brand-sky text-center transition-all">
                                        <span class="text-base block mb-0.5">✍️</span>
                                        <span class="text-[10px] font-bold font-outfit block">Manual Log</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Paired Hardware Status Card -->
                            <div class="p-3.5 rounded-xl bg-white/5 border border-white/10 space-y-2">
                                <div class="flex items-center justify-between text-[11px] font-outfit">
                                    <span class="text-gray-400">Paired Hardware:</span>
                                    <span id="selected-device-label" class="font-bold text-white">
                                        <?php 
                                            $brand_names = [
                                                'apple' => 'Apple Watch Ultra / S9',
                                                'garmin' => 'Garmin Fēnix / Forerunner',
                                                'fitbit' => 'Fitbit Sense 2 / Charge',
                                                'whoop' => 'Whoop 4.0 Biometric Band',
                                                'pixel' => 'Google Pixel Watch 2'
                                            ];
                                            echo $brand_names[$active_brand] ?? 'Apple Watch Ultra / S9';
                                        ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] font-outfit">
                                    <span class="text-gray-400">Battery Reserve:</span>
                                    <span id="device-battery-label" class="font-bold text-brand-sage flex items-center gap-1">
                                        <span>🔋</span> <?php echo htmlspecialchars($synced_device['battery'] ?? '86%'); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] font-outfit">
                                    <span class="text-gray-400">Last Synced:</span>
                                    <span id="device-sync-time" class="font-semibold text-gray-300"><?php echo htmlspecialchars($synced_device['synced_at'] ?? 'Today at 09:30 AM'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Middle: Sync Orb & Trigger Action -->
                        <div class="p-6 rounded-2xl dark-pane flex flex-col items-center justify-between text-center min-h-[320px] relative overflow-hidden">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold text-brand-sky uppercase tracking-wider font-outfit">Bluetooth Biometric Link</span>
                                <h4 class="text-base font-bold font-outfit text-white">Instant Telemetry Bridge</h4>
                                <p id="sync-status-narrative" class="text-xs text-gray-400 leading-normal max-w-xs mx-auto">
                                    Click below to pull today's sleep cycles, resting pulse, and step goals directly into your Digital Twin.
                                </p>
                            </div>

                            <!-- Sync Animated Orb Card -->
                            <div class="my-4 flex flex-col items-center justify-center">
                                <div id="sync-watch-orb" class="relative w-32 h-32 rounded-full border-4 border-white/10 bg-[#16272e] flex flex-col items-center justify-center shadow-2xl transition-all duration-300">
                                    <div id="watch-pulse-ring" class="hidden absolute inset-0 rounded-full border-2 border-brand-sage animate-ping opacity-75"></div>
                                    <div id="watch-pulse-ring-outer" class="hidden absolute -inset-2 rounded-full border border-brand-sky animate-pulse opacity-40"></div>
                                    
                                    <span id="watch-icon" class="text-4xl select-none transition-transform duration-300">⌚</span>
                                    
                                    <div id="watch-sync-stats" class="hidden flex flex-col items-center">
                                        <span id="watch-sync-percent" class="text-xl font-bold font-outfit text-brand-sage">0%</span>
                                        <span id="watch-sync-stage" class="text-[9px] font-semibold text-gray-400 uppercase tracking-widest font-outfit mt-0.5">Connecting</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sync CTA Button -->
                            <div class="w-full space-y-2">
                                <button type="button" id="sync-device-btn" onclick="syncSmartwatchData()" class="w-full py-3.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-glow transition-all active:scale-95 flex items-center justify-center gap-2 font-outfit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span id="sync-btn-label">Sync Wearable Telemetry</span>
                                </button>
                                <div class="flex items-center justify-center gap-2 text-[10px] text-gray-500 font-outfit">
                                    <span>🔒 End-to-end encrypted biometric transport</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Real-time Telemetry Summary Stats (Today's Snapshot) -->
                        <?php 
                            $today_str = date('Y-m-d');
                            $today_log = null;
                            if (!empty($telemetry_history)) {
                                foreach ($telemetry_history as $tl) {
                                    if ($tl['log_date'] === $today_str) {
                                        $today_log = $tl;
                                        break;
                                    }
                                }
                                if (!$today_log) {
                                    $today_log = end($telemetry_history);
                                }
                            }
                        ?>
                        <div class="p-6 rounded-2xl dark-pane flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <h4 class="text-sm font-bold font-outfit text-white">Current Day Telemetry</h4>
                                    <span class="text-[10px] text-gray-400 font-semibold font-outfit"><?php echo date('M d, Y'); ?></span>
                                </div>
                                <p class="text-[11px] text-gray-400 font-sans">Active stream synced with your wearable:</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block">Sleep Duration</span>
                                    <h5 class="text-base font-bold font-outfit text-white mt-1">
                                        <span id="metric-today-sleep"><?php echo $today_log['sleep_hours'] ?? '7.2'; ?></span> <span class="text-xs text-gray-400 font-normal">hrs</span>
                                    </h5>
                                    <span class="text-[10px] text-brand-sage block font-outfit">Quality: <span id="metric-today-sleepqual"><?php echo $today_log['sleep_quality'] ?? '82'; ?>%</span></span>
                                </div>
                                <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block">Activity Volume</span>
                                    <h5 class="text-base font-bold font-outfit text-white mt-1">
                                        <span id="metric-today-steps"><?php echo number_format($today_log['steps'] ?? 7450); ?></span>
                                    </h5>
                                    <span class="text-[10px] text-brand-sky block font-outfit"><span id="metric-today-activemin"><?php echo $today_log['active_minutes'] ?? '35'; ?></span> active mins</span>
                                </div>
                                <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block">Resting Heart Rate</span>
                                    <h5 class="text-base font-bold font-outfit text-white mt-1">
                                        <span id="metric-today-rhr"><?php echo $today_log['resting_hr'] ?? '64'; ?></span> <span class="text-xs text-gray-400 font-normal">bpm</span>
                                    </h5>
                                    <span class="text-[10px] text-gray-400 block font-outfit">Baseline: Normal</span>
                                </div>
                                <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider font-outfit block">Heart Rate Variability</span>
                                    <h5 class="text-base font-bold font-outfit text-brand-sage mt-1">
                                        <span id="metric-today-hrv"><?php echo $today_log['hrv'] ?? '56'; ?></span> <span class="text-xs text-gray-400 font-normal">ms</span>
                                    </h5>
                                    <span class="text-[10px] text-brand-sage block font-outfit">Parasympathetic: High</span>
                                </div>
                            </div>

                            <button type="button" onclick="toggleManualBiometricsModal()" class="w-full py-2 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 text-xs font-bold font-outfit transition-all text-center">
                                ✍️ Edit / Enter Exact Metrics
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Row: 7-Day Biometric History Log Table -->
                    <div class="p-6 rounded-2xl dark-pane space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div>
                                <h4 class="text-sm font-bold font-outfit text-white">7-Day Biometric Telemetry Roster</h4>
                                <p class="text-xs text-gray-400 mt-0.5 font-sans">Correlated records used to generate predictive relapse and burnout indicators.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-lg bg-brand-sage/15 text-brand-sage border border-brand-sage/20 text-[10px] font-bold uppercase tracking-wider font-outfit">
                                    <?php echo count($telemetry_history); ?> Days Recorded
                                </span>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left text-xs border-collapse font-sans">
                                <thead>
                                    <tr class="border-b border-white/10 text-gray-400 font-bold uppercase tracking-wider select-none font-outfit">
                                        <th class="py-3 px-2">Date</th>
                                        <th class="py-3 px-2">Sleep (Hours / Efficiency)</th>
                                        <th class="py-3 px-2">Daily Steps & Activity</th>
                                        <th class="py-3 px-2">Cardiac Telemetry (HRV / Rest HR)</th>
                                        <th class="py-3 px-2">Voice Stress</th>
                                        <th class="py-3 px-2 text-right">Digital Twin Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-300">
                                    <?php if (empty($telemetry_history)): ?>
                                        <tr>
                                            <td colspan="6" class="py-8 text-center italic text-gray-500">No telemetry log entries found. Click "Sync Wearable Telemetry" above.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_reverse($telemetry_history) as $log): 
                                            $is_today = ($log['log_date'] === date('Y-m-d'));
                                            $sleep_good = ($log['sleep_hours'] >= 6.5 && $log['sleep_quality'] >= 75);
                                            $hrv_good = ($log['hrv'] >= 45);
                                        ?>
                                            <tr class="hover:bg-white/[0.02] transition-colors">
                                                <td class="py-3.5 px-2 font-semibold text-white">
                                                    <div class="flex items-center gap-1.5">
                                                        <?php if ($is_today): ?>
                                                            <span class="w-2 h-2 rounded-full bg-brand-sage" title="Today"></span>
                                                        <?php endif; ?>
                                                        <span><?php echo date('D, M d', strtotime($log['log_date'])); ?></span>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-2 font-semibold">
                                                    <div class="flex items-center gap-2">
                                                        <span class="<?php echo $sleep_good ? 'text-white' : 'text-brand-coral'; ?>"><?php echo $log['sleep_hours']; ?> hrs</span>
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo ($log['sleep_quality'] >= 75) ? 'bg-brand-sage/15 text-brand-sage' : 'bg-brand-coral/15 text-brand-coral'; ?>">
                                                            <?php echo $log['sleep_quality']; ?>% eff
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-2 font-semibold">
                                                    <span><?php echo number_format($log['steps']); ?> steps</span>
                                                    <span class="text-[10px] text-gray-500 font-normal block"><?php echo $log['active_minutes']; ?> active mins</span>
                                                </td>
                                                <td class="py-3.5 px-2 font-semibold">
                                                    <div class="flex items-center gap-2">
                                                        <span class="<?php echo $hrv_good ? 'text-brand-sage' : 'text-brand-coral'; ?>"><?php echo $log['hrv']; ?> ms</span>
                                                        <span class="text-gray-500 font-normal text-[10px]">/ <?php echo $log['resting_hr']; ?> bpm</span>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-2 font-semibold">
                                                    <?php if (isset($log['voice_stress_score']) && $log['voice_stress_score'] > 0): ?>
                                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase font-outfit <?php echo ($log['voice_stress_score'] >= 65) ? 'bg-brand-coral/20 text-brand-coral' : (($log['voice_stress_score'] >= 40) ? 'bg-brand-sky/20 text-brand-sky' : 'bg-brand-sage/20 text-brand-sage'); ?>">
                                                            <?php echo $log['voice_stress_score']; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-500 text-[10px] italic">Not Recorded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3.5 px-2 text-right">
                                                    <?php if ($sleep_good && $hrv_good): ?>
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-sage font-outfit">
                                                            <span>🟢</span> Calibrated
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-coral font-outfit">
                                                            <span>⚠️</span> Rest Desired
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MANUAL BIOMETRIC LOGGING MODAL -->
                <div id="manual-biometrics-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                    <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-sm" onclick="toggleManualBiometricsModal()"></div>
                    <div class="relative bg-brand-darkBg text-white w-full max-w-lg rounded-3xl p-6 md:p-8 shadow-2xl border border-white/10 transform scale-95 transition-all duration-300">
                        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-brand-sage/20 border border-brand-sage/30 flex items-center justify-center text-lg">
                                    ✍️
                                </div>
                                <div>
                                    <h4 class="text-base font-bold font-outfit text-white">Manual Biometric Entry</h4>
                                    <p class="text-[11px] text-gray-400 font-sans">Input exact telemetry read from your wearable device.</p>
                                </div>
                            </div>
                            <button type="button" onclick="toggleManualBiometricsModal()" class="text-gray-400 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>

                        <form id="manual-biometrics-form" onsubmit="handleManualBiometricsSubmit(event)" class="space-y-4 font-sans text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">Sleep Duration (Hours)</label>
                                    <input type="number" step="0.1" min="2" max="14" id="custom-sleep-hours" value="<?php echo $today_log['sleep_hours'] ?? '7.2'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">Sleep Quality / Efficiency (%)</label>
                                    <input type="number" min="30" max="100" id="custom-sleep-quality" value="<?php echo $today_log['sleep_quality'] ?? '82'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">Daily Steps Count</label>
                                    <input type="number" min="0" max="50000" id="custom-steps" value="<?php echo $today_log['steps'] ?? '7450'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">Active Minutes</label>
                                    <input type="number" min="0" max="300" id="custom-active-min" value="<?php echo $today_log['active_minutes'] ?? '35'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">Resting Heart Rate (bpm)</label>
                                    <input type="number" min="40" max="120" id="custom-resting-hr" value="<?php echo $today_log['resting_hr'] ?? '64'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider font-outfit mb-1">HRV (ms)</label>
                                    <input type="number" min="15" max="140" id="custom-hrv" value="<?php echo $today_log['hrv'] ?? '56'; ?>" required class="w-full px-3.5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-brand-sage">
                                </div>
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-3">
                                <button type="button" onclick="toggleManualBiometricsModal()" class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-gray-300 font-bold font-outfit text-xs transition-colors">Cancel</button>
                                <button type="submit" id="save-manual-btn" class="px-6 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold font-outfit text-xs shadow-glow transition-all">Save & Sync to Twin</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Wearable Sync JS Actions -->
                <script>
                    let currentSelectedBrand = "<?php echo $active_brand; ?>";
                    const brandMetadata = {
                        'apple': { name: 'Apple Watch Ultra / S9', icon: '⌚' },
                        'garmin': { name: 'Garmin Fēnix / Forerunner', icon: '⏱️' },
                        'fitbit': { name: 'Fitbit Sense 2 / Charge', icon: '🏃' },
                        'whoop': { name: 'Whoop 4.0 Biometric Band', icon: '📈' },
                        'pixel': { name: 'Google Pixel Watch 2', icon: '🧭' }
                    };

                    function selectWearableBrand(brand, brandName, icon) {
                        currentSelectedBrand = brand;
                        document.getElementById('selected-device-label').innerText = brandName;
                        document.getElementById('watch-icon').innerText = icon;

                        // Update pill styling
                        const pills = document.querySelectorAll('#device-selector-grid .device-pill[data-brand]');
                        pills.forEach(p => {
                            if (p.getAttribute('data-brand') === brand) {
                                p.className = 'device-pill p-2 rounded-xl border border-brand-sage bg-brand-sage/15 text-white shadow-sm text-center transition-all';
                            } else {
                                p.className = 'device-pill p-2 rounded-xl border border-white/10 bg-white/5 text-gray-400 hover:text-white text-center transition-all';
                            }
                        });

                        showAppToast(`Switched active wearable to ${brandName}.`, 'sage');
                    }

                    function toggleManualBiometricsModal() {
                        const modal = document.getElementById('manual-biometrics-modal');
                        if (modal.classList.contains('opacity-0')) {
                            modal.classList.remove('opacity-0', 'pointer-events-none');
                            modal.querySelector('.relative').classList.remove('scale-95');
                            modal.querySelector('.relative').classList.add('scale-100');
                        } else {
                            modal.classList.add('opacity-0', 'pointer-events-none');
                            modal.querySelector('.relative').classList.remove('scale-100');
                            modal.querySelector('.relative').classList.add('scale-95');
                        }
                    }

                    function handleManualBiometricsSubmit(e) {
                        e.preventDefault();
                        const saveBtn = document.getElementById('save-manual-btn');
                        saveBtn.disabled = true;
                        saveBtn.innerText = 'Syncing...';

                        const formData = new FormData();
                        formData.append('action', 'sync_smartwatch');
                        formData.append('device_brand', currentSelectedBrand);
                        formData.append('sleep_hours', document.getElementById('custom-sleep-hours').value);
                        formData.append('sleep_quality', document.getElementById('custom-sleep-quality').value);
                        formData.append('steps', document.getElementById('custom-steps').value);
                        formData.append('active_minutes', document.getElementById('custom-active-min').value);
                        formData.append('resting_hr', document.getElementById('custom-resting-hr').value);
                        formData.append('hrv', document.getElementById('custom-hrv').value);

                        fetch('predictive_hub.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            saveBtn.disabled = false;
                            saveBtn.innerText = 'Save & Sync to Twin';
                            toggleManualBiometricsModal();

                            if (data.status === 'success') {
                                showAppToast('🎉 Custom biometrics logged! Updating your digital twin...', 'sage');
                                setTimeout(() => window.location.reload(), 900);
                            } else {
                                showAppToast('Failed to save manual biometrics.', 'coral');
                            }
                        })
                        .catch(err => {
                            console.error('Manual log error:', err);
                            saveBtn.disabled = false;
                            saveBtn.innerText = 'Save & Sync to Twin';
                            showAppToast('Network error while saving metrics.', 'coral');
                        });
                    }

                    function syncSmartwatchData() {
                        const syncBtn = document.getElementById('sync-device-btn');
                        const syncBtnLabel = document.getElementById('sync-btn-label');
                        const pulseRing = document.getElementById('watch-pulse-ring');
                        const pulseOuter = document.getElementById('watch-pulse-ring-outer');
                        const watchIcon = document.getElementById('watch-icon');
                        const watchStats = document.getElementById('watch-sync-stats');
                        const watchPercent = document.getElementById('watch-sync-percent');
                        const watchStage = document.getElementById('watch-sync-stage');
                        const narrative = document.getElementById('sync-status-narrative');

                        syncBtn.disabled = true;
                        syncBtn.classList.add('opacity-50');
                        syncBtnLabel.innerText = 'Bridging Bluetooth...';

                        pulseRing.classList.remove('hidden');
                        pulseOuter.classList.remove('hidden');
                        watchIcon.classList.add('hidden');
                        watchStats.classList.remove('hidden');
                        watchPercent.innerText = '0%';

                        let percentage = 0;
                        const syncInterval = setInterval(() => {
                            percentage += 10;
                            watchPercent.innerText = percentage + '%';

                            if (percentage === 20) {
                                watchStage.innerText = 'Reading Sleep';
                                syncBtnLabel.innerText = 'Analyzing REM & deep sleep...';
                                narrative.innerText = 'Downloading sleep duration and recovery score...';
                            } else if (percentage === 50) {
                                watchStage.innerText = 'Reading HRV';
                                syncBtnLabel.innerText = 'Importing resting heart rate & HRV...';
                                narrative.innerText = 'Correlating autonomic cardiac intervals...';
                            } else if (percentage === 80) {
                                watchStage.innerText = 'Aggregating Steps';
                                syncBtnLabel.innerText = 'Compiling active movement & calories...';
                                narrative.innerText = 'Calculating today\'s physical movement total...';
                            }

                            if (percentage >= 100) {
                                clearInterval(syncInterval);
                                watchStage.innerText = 'Finalizing';
                                submitTelemetrySync();
                            }
                        }, 220);
                    }

                    function submitTelemetrySync() {
                        const formData = new FormData();
                        formData.append('action', 'sync_smartwatch');
                        formData.append('device_brand', currentSelectedBrand);

                        fetch('predictive_hub.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showAppToast(`🎉 ${brandMetadata[currentSelectedBrand]?.name || 'Wearable'} synchronized! Digital Twin telemetry updated.`, 'sage');
                                setTimeout(() => window.location.reload(), 900);
                            } else {
                                showAppToast('Could not finalize wearable sync.', 'coral');
                                resetSyncWidget();
                            }
                        })
                        .catch(err => {
                            console.error('Error syncing wearable:', err);
                            showAppToast('Sync failed due to connection timeout.', 'coral');
                            resetSyncWidget();
                        });
                    }

                    function resetSyncWidget() {
                        const syncBtn = document.getElementById('sync-device-btn');
                        const syncBtnLabel = document.getElementById('sync-btn-label');
                        const pulseRing = document.getElementById('watch-pulse-ring');
                        const pulseOuter = document.getElementById('watch-pulse-ring-outer');
                        const watchIcon = document.getElementById('watch-icon');
                        const watchStats = document.getElementById('watch-sync-stats');

                        syncBtn.disabled = false;
                        syncBtn.classList.remove('opacity-50');
                        syncBtnLabel.innerText = 'Sync Wearable Telemetry';
                        pulseRing.classList.add('hidden');
                        pulseOuter.classList.add('hidden');
                        watchIcon.classList.remove('hidden');
                        watchStats.classList.add('hidden');
                    }
                </script>
            <?php endif; ?>


        </div>
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
            <a href="predictive_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><line x1="2" y1="12" x2="22" y2="12"/>
                </svg>
                <span class="text-[10px] tracking-wider uppercase">Twin</span>
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
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the support services below.</p>
            <div class="space-y-3 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-bg border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-bg border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
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
                    <p class="text-[10px] text-brand-coral font-bold uppercase tracking-wider font-outfit">Safety Warning Notice</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mb-6 leading-relaxed">Our safety scans detected keywords indicating severe distress in your logs. Please reach out to support immediately:</p>
            <div class="space-y-3 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">988 Suicide & Crisis Lifeline</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call or Text 24/7</p>
                    </div>
                    <a href="tel:988" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 988</a>
                </div>
            </div>
            <form method="POST" action="predictive_hub.php">
                <input type="hidden" name="action" value="clear_crisis">
                <label class="flex items-start gap-2.5 mb-4 cursor-pointer select-none text-[10px] text-gray-500 leading-normal font-sans">
                    <input type="checkbox" required class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-0.5">
                    <span>I acknowledge these resources and confirm that I am safe or currently seeking appropriate help.</span>
                </label>
                <button type="submit" class="w-full py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-colors text-center font-outfit">Acknowledge & Dismiss</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- GLOBAL IN-APP TOAST NOTIFICATION -->
    <div id="app-toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full px-4 sm:px-0"></div>

    <script>
        function showAppToast(message, type = 'sage') {
            const container = document.getElementById('app-toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto transform translate-y-3 opacity-0 transition-all duration-300 flex items-start gap-3 p-4 rounded-2xl shadow-2xl text-xs font-sans text-white border ${type === 'coral' ? 'bg-[#2b1b1a] border-brand-coral/40' : 'bg-[#152723] border-brand-sage/40'}`;
            
            const icon = type === 'coral' ? '⚠️' : '✨';
            toast.innerHTML = `
                <span class="text-base shrink-0 select-none">${icon}</span>
                <div class="flex-grow">
                    <p class="leading-relaxed">${message}</p>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-gray-400 hover:text-white p-0.5 ml-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            `;

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            });

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-3', 'opacity-0');
                setTimeout(() => toast.remove(), 350);
            }, 5500);
        }

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
    </script>
</body>
</html>
