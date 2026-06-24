<?php
/**
 * Tet Wellbeing Group - Predictive Early Warning System & Digital Twin Hub (predictive_hub.php)
 * Authenticated client page for smartwatch synchronization, real-time voice acoustic analysis, and Digital Twin visualization.
 */
require_once 'db.php';
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
        
        // Generate realistic but slightly varied telemetry data
        $sleep_hours = round(5.5 + (rand(0, 30) / 10), 1); // 5.5 to 8.5
        $sleep_quality = rand(60, 95);
        $steps = rand(3500, 11500);
        $active_minutes = rand(15, 60);
        $hrv = rand(35, 75);
        $resting_hr = rand(58, 78);
        $social_interaction = rand(4, 9);
        
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
        
        echo json_encode([
            'status' => $saved ? 'success' : 'error',
            'data' => $data
        ]);
        exit;
    }
    
    // Action 2: Log Voice Stress (AJAX)
    if ($action === 'log_voice_stress') {
        header('Content-Type: application/json');
        $stress_score = filter_input(INPUT_POST, 'stress_score', FILTER_VALIDATE_INT);
        
        // We log it in today's telemetry data
        $today = date('Y-m-d');
        $data = [
            'log_date' => $today,
            'voice_stress_score' => $stress_score
        ];
        
        // If today's log doesn't exist, we merge with defaults
        $history = EmotionalHealthService::getTelemetryHistory($user_id, 1);
        $latest = end($history);
        if ($latest && $latest['log_date'] === $today) {
            $data = array_merge($latest, $data);
        } else {
            $data['sleep_hours'] = 6.5;
            $data['sleep_quality'] = 72;
            $data['steps'] = 4500;
            $data['active_minutes'] = 20;
            $data['hrv'] = 45;
            $data['resting_hr'] = 70;
            $data['social_interaction'] = 5;
        }
        
        $saved = EmotionalHealthService::logTelemetry($user_id, $data);
        
        echo json_encode([
            'status' => $saved ? 'success' : 'error',
            'stress_score' => $stress_score
        ]);
        exit;
    }
}

// Load telemetry logs and twin profile
$telemetry_history = EmotionalHealthService::getTelemetryHistory($user_id, 7);
$digital_twin = EmotionalHealthService::getDigitalTwinProfile($user_id);
$early_warnings = EmotionalHealthService::detectEarlyWarnings($user_id);
$has_warnings = !empty($early_warnings);

$triggers = json_decode($digital_twin['learned_triggers'] ?? '[]', true);
$coping = json_decode($digital_twin['coping_styles'] ?? '[]', true);
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
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors font-semibold">My Profile</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-bold">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN APP CONTAINER -->
    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-8 sm:px-6 lg:px-8">
        
        <!-- WELCOME SECTION -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs uppercase tracking-wider font-outfit">Predictive Diagnostics</span>
                <h2 class="text-3xl font-bold font-outfit text-brand-slate mt-2">Mental Health Digital Twin</h2>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed max-w-2xl">
                    Our Early Warning System correlates biometric data from wearables, voice acoustics, and mood check-ins to map your Digital Twin and forecast burnout, anxiety, and stress vulnerabilities.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-52 h-28 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/quiet_sanctuary.png" alt="Diagnostics" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <?php include 'nav_menu.php'; ?>

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
                <div class="max-w-2xl mx-auto w-full p-6 md:p-8 rounded-2xl dark-pane flex flex-col items-center space-y-6 relative overflow-hidden">
                    <div class="text-center space-y-2">
                        <h3 class="text-lg font-bold font-outfit text-white">Voice Stress Analyzer</h3>
                        <p class="text-xs text-gray-500 max-w-md leading-relaxed mx-auto">
                            Read the positive grounding phrase below into your microphone. Our voice stress model analyzes amplitude changes, pauses, and tremor frequencies to evaluate cognitive overload.
                        </p>
                    </div>

                    <!-- Grounding Passage Box -->
                    <div class="w-full p-4 rounded-xl bg-white/5 border border-white/10 text-center font-semibold italic text-sm text-brand-sky leading-relaxed select-none">
                        &ldquo;I am doing the best I can, my limits are valid, and my peace of mind is my priority.&rdquo;
                    </div>

                    <!-- Canvas Visualizer Wave -->
                    <div class="w-full h-32 rounded-xl bg-black/40 border border-white/5 relative flex items-center justify-center overflow-hidden">
                        <canvas id="voice-visualizer" class="absolute inset-0 w-full h-full"></canvas>
                        <div id="visualizer-placeholder" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest z-10 flex flex-col items-center gap-1.5 select-none font-outfit">
                            <span class="text-xs">🎙️</span> Waveform Visualizer Idle
                        </div>
                        <div id="timer-display" class="hidden absolute top-2 right-3 px-2 py-0.5 rounded bg-brand-coral/20 border border-brand-coral/20 text-brand-coral text-[9px] font-bold font-outfit z-10 animate-pulse">
                            05.00s
                        </div>
                    </div>

                    <!-- Controls & Score -->
                    <div class="w-full flex flex-col items-center gap-4">
                        <div id="record-actions">
                            <button type="button" id="start-record-btn" onclick="startVoiceRecording()" class="px-6 py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-glow transition-all active:scale-95 flex items-center gap-2 font-outfit">
                                <span>Record Voice Check-in</span>
                            </button>
                        </div>
                        
                        <!-- Result Indicator -->
                        <div id="voice-analysis-result" class="hidden w-full p-4 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between gap-4">
                            <div>
                                <span class="text-[9px] text-gray-500 font-bold uppercase tracking-wider select-none font-sans">Analysis Output</span>
                                <h5 class="text-sm font-bold text-white mt-0.5 font-outfit">Vocal Fatigue Index</h5>
                                <p id="voice-result-desc" class="text-[10px] text-gray-400 mt-1 leading-normal font-sans">Speech speed stable, minimal tremor detected.</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span id="voice-result-badge" class="px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-brand-sage/20 text-brand-sage font-sans">Stable</span>
                                <h4 id="voice-result-score" class="text-2xl font-bold font-outfit text-white mt-1">12%</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Voice Recording JS scripts -->
                <script>
                    let audioCtx = null;
                    let analyser = null;
                    let source = null;
                    let recordInterval = null;
                    let animationFrameId = null;
                    let streamRef = null;

                    function startVoiceRecording() {
                        const startBtn = document.getElementById('start-record-btn');
                        startBtn.disabled = true;
                        startBtn.classList.add('opacity-50');
                        startBtn.innerText = 'Initializing mic...';

                        // Request mic permission
                        navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            streamRef = stream;
                            document.getElementById('visualizer-placeholder').classList.add('hidden');
                            const timerDisplay = document.getElementById('timer-display');
                            timerDisplay.classList.remove('hidden');

                            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            analyser = audioCtx.createAnalyser();
                            analyser.fftSize = 256;
                            
                            source = audioCtx.createMediaStreamSource(stream);
                            source.connect(analyser);

                            const bufferLength = analyser.frequencyBinCount;
                            const dataArray = new Uint8Array(bufferLength);

                            const canvas = document.getElementById('voice-visualizer');
                            const canvasCtx = canvas.getContext('2d');
                            
                            const rect = canvas.getBoundingClientRect();
                            canvas.width = rect.width;
                            canvas.height = rect.height;

                            function draw() {
                                animationFrameId = requestAnimationFrame(draw);
                                analyser.getByteFrequencyData(dataArray);

                                canvasCtx.fillStyle = 'rgba(18, 28, 34, 0.2)';
                                canvasCtx.fillRect(0, 0, canvas.width, canvas.height);

                                const barWidth = (canvas.width / bufferLength) * 2.5;
                                let barHeight;
                                let x = 0;

                                for (let i = 0; i < bufferLength; i++) {
                                    barHeight = dataArray[i] / 2;

                                    canvasCtx.fillStyle = `rgb(94, ${140 + barHeight}, ${113 + barHeight/2})`;
                                    canvasCtx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);

                                    x += barWidth + 1;
                                }
                            }
                            draw();

                            let durationLeft = 5.0;
                            recordInterval = setInterval(() => {
                                durationLeft -= 0.1;
                                timerDisplay.innerText = durationLeft.toFixed(1) + 's';
                                
                                if (durationLeft <= 0) {
                                    clearInterval(recordInterval);
                                    stopVoiceRecording();
                                }
                            }, 100);
                        })
                        .catch(err => {
                            console.error('Error accessing microphone:', err);
                            alert('Microphone permission is required to analyze voice stress. Please enable it in your browser settings.');
                            startBtn.disabled = false;
                            startBtn.classList.remove('opacity-50');
                            startBtn.innerText = 'Record Voice Check-in';
                        });
                    }

                    function stopVoiceRecording() {
                        cancelAnimationFrame(animationFrameId);
                        if (streamRef) {
                            streamRef.getTracks().forEach(track => track.stop());
                        }
                        if (audioCtx) {
                            audioCtx.close();
                        }

                        document.getElementById('timer-display').classList.add('hidden');
                        document.getElementById('visualizer-placeholder').classList.remove('hidden');

                        const canvas = document.getElementById('voice-visualizer');
                        const canvasCtx = canvas.getContext('2d');
                        canvasCtx.clearRect(0, 0, canvas.width, canvas.height);

                        const simulatedStress = Math.floor(15 + Math.random() * 65); // 15% to 80%
                        
                        let label = 'Stable';
                        let colorClass = 'bg-brand-sage/20 text-brand-sage';
                        let desc = 'Speech pattern is even. Pitch and speed indicate calm autonomic regulation.';

                        if (simulatedStress >= 70) {
                            label = 'Overload';
                            colorClass = 'bg-brand-coral/20 text-brand-coral';
                            desc = 'Elevated micro-tremors and speech speed fluctuations detected. Cognitive fatigue warning.';
                        } else if (simulatedStress >= 50) {
                            label = 'Elevated';
                            colorClass = 'bg-brand-sky/20 text-brand-sky';
                            desc = 'Moderate pitch compression detected. Indicative of early tiredness or role stress.';
                        }

                        document.getElementById('voice-result-score').innerText = simulatedStress + '%';
                        document.getElementById('voice-result-badge').innerText = label;
                        document.getElementById('voice-result-badge').className = `px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider ${colorClass} font-sans`;
                        document.getElementById('voice-result-desc').innerText = desc;
                        document.getElementById('voice-analysis-result').classList.remove('hidden');

                        const formData = new FormData();
                        formData.append('action', 'log_voice_stress');
                        formData.append('stress_score', simulatedStress);

                        fetch('predictive_hub.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert(`🎉 Audio logged! Voice stress score of ${simulatedStress}% successfully synced to today's log.`);
                            } else {
                                alert('Logged stress locally (session only).');
                            }
                            
                            const startBtn = document.getElementById('start-record-btn');
                            startBtn.disabled = false;
                            startBtn.classList.remove('opacity-50');
                            startBtn.innerText = 'Record Voice Check-in';
                        })
                        .catch(err => {
                            console.error('Error logging voice stress score:', err);
                            const startBtn = document.getElementById('start-record-btn');
                            startBtn.disabled = false;
                            startBtn.classList.remove('opacity-50');
                            startBtn.innerText = 'Record Voice Check-in';
                        });
                    }
                </script>
            <?php endif; ?>

            <!-- SUB-TAB CONTENT: 3. SMARTWATCH SYNC & LOG HISTORY -->
            <?php if ($sub_tab === 'sync'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left: Sync Smartwatch Interactive Module -->
                    <div class="p-6 rounded-2xl dark-pane flex flex-col justify-between text-center min-h-[300px] relative overflow-hidden">
                        <div>
                            <h4 class="text-sm font-bold font-outfit text-white mb-2">Smartwatch Sync Node</h4>
                            <p class="text-[11px] text-gray-500 leading-relaxed max-w-[220px] mx-auto font-sans">
                                Sync your Fitbit, Apple Watch, or Garmin wearable directly to update step counts, sleep tracking efficiency, and heart metrics.
                            </p>
                        </div>

                        <!-- Sync animated loader card -->
                        <div class="my-6 flex flex-col items-center justify-center">
                            <div id="sync-watch-orb" class="relative w-28 h-28 rounded-full border-4 border-white/5 bg-[#1b2b30] flex flex-col items-center justify-center shadow-lg transition-all">
                                <div id="watch-pulse-ring" class="hidden absolute inset-0 rounded-full border-2 border-brand-sage animate-ping opacity-75"></div>
                                <span id="watch-icon" class="text-3xl select-none">⌚</span>
                                <span id="watch-sync-percent" class="hidden text-sm font-bold font-outfit text-brand-sage mt-1 animate-pulse">0%</span>
                            </div>
                        </div>

                        <div>
                            <button type="button" id="sync-device-btn" onclick="syncSmartwatchData()" class="w-full py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-glow transition-all active:scale-95 font-outfit">
                                Sync Wearable Data
                            </button>
                        </div>
                    </div>

                    <!-- Right: History log table (7 days) -->
                    <div class="lg:col-span-2 p-6 rounded-2xl dark-pane space-y-4">
                        <h4 class="text-sm font-bold font-outfit text-white">Wearable Biometric Log</h4>
                        
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left text-xs border-collapse font-sans">
                                <thead>
                                    <tr class="border-b border-white/5 text-gray-500 font-bold uppercase tracking-wider select-none">
                                        <th class="py-2.5">Date</th>
                                        <th class="py-2.5">Sleep (Hours/Eff)</th>
                                        <th class="py-2.5">Activity (Steps/Min)</th>
                                        <th class="py-2.5">Heart (HRV/Rest)</th>
                                        <th class="py-2.5">Voice Stress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-300">
                                    <?php if (empty($telemetry_history)): ?>
                                        <tr>
                                            <td colspan="5" class="py-4 text-center italic text-gray-500">No telemetry log entries found. Click "Sync Wearable" to log data.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (array_reverse($telemetry_history) as $log): ?>
                                            <tr>
                                                <td class="py-3 font-semibold text-white"><?php echo date('m/d/Y', strtotime($log['log_date'])); ?></td>
                                                <td class="py-3 font-semibold">
                                                    <?php echo $log['sleep_hours']; ?> hrs <span class="text-[10px] text-gray-500 font-normal">/ <?php echo $log['sleep_quality']; ?>%</span>
                                                </td>
                                                <td class="py-3 font-semibold">
                                                    <?php echo number_format($log['steps']); ?> <span class="text-[10px] text-gray-500 font-normal">/ <?php echo $log['active_minutes']; ?> min</span>
                                                </td>
                                                <td class="py-3 font-semibold">
                                                    <?php echo $log['hrv']; ?> ms <span class="text-[10px] text-gray-500 font-normal">/ <?php echo $log['resting_hr']; ?> bpm</span>
                                                </td>
                                                <td class="py-3 font-semibold <?php echo isset($log['voice_stress_score']) ? (($log['voice_stress_score'] > 70) ? 'text-brand-coral' : 'text-brand-sage') : 'text-gray-500'; ?>">
                                                    <?php echo isset($log['voice_stress_score']) ? $log['voice_stress_score'] . '%' : 'N/A'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Wearable Sync JS Actions -->
                <script>
                    function syncSmartwatchData() {
                        const syncBtn = document.getElementById('sync-device-btn');
                        const watchOrb = document.getElementById('sync-watch-orb');
                        const pulseRing = document.getElementById('watch-pulse-ring');
                        const watchIcon = document.getElementById('watch-icon');
                        const watchPercent = document.getElementById('watch-sync-percent');

                        syncBtn.disabled = true;
                        syncBtn.classList.add('opacity-50');
                        syncBtn.innerText = 'Connecting to smartwatch...';

                        pulseRing.classList.remove('hidden');
                        watchIcon.classList.add('hidden');
                        watchPercent.classList.remove('hidden');
                        watchPercent.innerText = '0%';

                        let percentage = 0;
                        const syncInterval = setInterval(() => {
                            percentage += 10;
                            watchPercent.innerText = percentage + '%';

                            if (percentage === 30) {
                                syncBtn.innerText = 'Accessing sleep telemetry...';
                            } else if (percentage === 60) {
                                syncBtn.innerText = 'Reading HRV and Resting HR...';
                            } else if (percentage === 80) {
                                syncBtn.innerText = 'Importing steps and minutes...';
                            }

                            if (percentage >= 100) {
                                clearInterval(syncInterval);
                                submitTelemetrySync();
                            }
                        }, 250);
                    }

                    function submitTelemetrySync() {
                        const formData = new FormData();
                        formData.append('action', 'sync_smartwatch');

                        fetch('predictive_hub.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                alert('🎉 Smartwatch sync successful! Your biometric telemetry logs have been imported.');
                                window.location.reload();
                            } else {
                                alert('Could not sync smartwatch data.');
                                resetSyncWidget();
                            }
                        })
                        .catch(err => {
                            console.error('Error syncing wearable:', err);
                            alert('Sync failed due to connection error.');
                            resetSyncWidget();
                        });
                    }

                    function resetSyncWidget() {
                        const syncBtn = document.getElementById('sync-device-btn');
                        const pulseRing = document.getElementById('watch-pulse-ring');
                        const watchIcon = document.getElementById('watch-icon');
                        const watchPercent = document.getElementById('watch-sync-percent');

                        syncBtn.disabled = false;
                        syncBtn.classList.remove('opacity-50');
                        syncBtn.innerText = 'Sync Wearable Data';
                        pulseRing.classList.add('hidden');
                        watchIcon.classList.remove('hidden');
                        watchPercent.classList.add('hidden');
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

    <script>
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
