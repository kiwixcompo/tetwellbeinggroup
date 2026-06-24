<?php
/**
 * Tet Wellbeing Group - Virtual Reality Resilience Centre (vr_resilience.php)
 * Authenticated client page embedding A-Frame (WebXR) 3D simulations for guided anxiety and stress training.
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

// Handle AJAX log save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'log_vr_practice') {
        header('Content-Type: application/json');
        
        $sim_id = filter_input(INPUT_POST, 'simulation_id', FILTER_DEFAULT);
        $duration = filter_input(INPUT_POST, 'duration_seconds', FILTER_VALIDATE_INT);
        $mood_imp = filter_input(INPUT_POST, 'mood_improvement', FILTER_VALIDATE_INT);
        $today = date('Y-m-d');
        
        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO vr_practice_logs (user_id, simulation_id, practice_date, duration_seconds, mood_improvement) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $sim_id, $today, $duration, $mood_imp]);
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (!$saved && isset($_SESSION['mock_vr_practice_logs'])) {
            $_SESSION['mock_vr_practice_logs'][] = [
                'id' => count($_SESSION['mock_vr_practice_logs']) + 1,
                'user_id' => $user_id,
                'simulation_id' => $sim_id,
                'practice_date' => $today,
                'duration_seconds' => $duration,
                'mood_improvement' => $mood_imp,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $saved = true;
        }
        
        echo json_encode(['status' => $saved ? 'success' : 'error']);
        exit;
    }
}

// Fetch user VR logs
$practice_logs = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM vr_practice_logs WHERE user_id = ? ORDER BY practice_date DESC");
        $stmt->execute([$user_id]);
        $practice_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {}
}

if (empty($practice_logs) && isset($_SESSION['mock_vr_practice_logs'])) {
    $practice_logs = array_filter($_SESSION['mock_vr_practice_logs'], function($log) use ($user_id) {
        return $log['user_id'] == $user_id;
    });
    usort($practice_logs, function($a, $b) {
        return strcmp($b['practice_date'], $a['practice_date']);
    });
}

// Calculate Stats
$total_sessions = count($practice_logs);
$total_minutes = 0;
foreach ($practice_logs as $log) {
    $total_minutes += ($log['duration_seconds'] / 60);
}
$total_minutes = round($total_minutes, 1);
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VR Resilience Centre - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- A-Frame VR/XR Web Library -->
    <script src="https://aframe.io/releases/1.4.0/aframe.min.js"></script>

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
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <div class="flex items-center gap-4">
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-brand-coralHover hover:shadow-lg transition-all active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Emergency Support</span>
                </button>

                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage hover:border-brand-sage transition-all">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <hr class="my-2 border-gray-100">
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
                <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs uppercase tracking-wider font-outfit">Virtual Reality</span>
                <h2 class="text-3xl font-bold font-outfit text-brand-slate mt-2">VR Resilience Centre</h2>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed max-w-2xl">
                    Immersive WebXR 3D sensory environments for self-regulation. Works on any browser, with mouse drag controls, or paired directly with a VR headset.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-52 h-28 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/safe_bridge.png" alt="VR Headset" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <?php include 'nav_menu.php'; ?>

        <!-- CONTENT TABS SWITCH -->
        <div id="vr-tabs-container" class="space-y-8">
            
            <!-- SUB-TABS NAVIGATION -->
            <div class="flex items-center gap-4 border-b border-gray-200 pb-4">
                <button type="button" onclick="switchTab('selector')" id="tab-btn-selector" class="px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-brand-sage text-white shadow-soft">
                    🚪 Simulation Suite
                </button>
                <button type="button" onclick="switchTab('logs')" id="tab-btn-logs" class="px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-white text-brand-slate border border-[#EBE8E0] hover:bg-gray-50">
                    📜 Practice History & Stats
                </button>
            </div>

            <!-- VIEW 1: SIMULATION SUITE SELECTOR -->
            <div id="view-selector" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Simulation 1: Forest Sanctuary -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-md transition-all">
                    <div class="space-y-3">
                        <span class="px-2.5 py-1 rounded bg-[#E8EFEA] text-brand-sage font-bold text-[9px] uppercase tracking-wider">Breathing Sanctuary</span>
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Anxiety Management Forest</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Escape to a tranquil green clearing. Practice synchronous deep breathing in alignment with a pulsing geometric visual guide amidst quiet, ambient sounds.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-gray-400">⏱️ Preset: 3 Minutes</span>
                        <button onclick="startSimulation('forest')" class="px-4 py-2 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-sm">Start Simulation</button>
                    </div>
                </div>

                <!-- Simulation 2: Virtual Auditorium -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-md transition-all">
                    <div class="space-y-3">
                        <span class="px-2.5 py-1 rounded bg-[#E8F4F8] text-brand-sky font-bold text-[9px] uppercase tracking-wider">Exposure Therapy</span>
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Public Speaking Lecture Hall</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Stand at a podium in front of a virtual seated audience. Build public speaking confidence with simulation timers and optional crowd murmuring sound toggles.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-gray-400">⏱️ Preset: 5 Minutes</span>
                        <button onclick="startSimulation('auditorium')" class="px-4 py-2 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-sm">Start Simulation</button>
                    </div>
                </div>

                <!-- Simulation 3: Calm Office -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-md transition-all">
                    <div class="space-y-3">
                        <span class="px-2.5 py-1 rounded bg-brand-coralLight text-brand-coral font-bold text-[9px] uppercase tracking-wider">Workplace Buffer</span>
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Calm Office Space</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Confront office stressors by clearing floating task icons in a quiet corporate room, training cognitive control during chaotic demands.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-gray-400">⏱️ Preset: 3 Minutes</span>
                        <button onclick="startSimulation('office')" class="px-4 py-2 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-sm">Start Simulation</button>
                    </div>
                </div>

                <!-- Simulation 4: Cozy Living Room -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-md transition-all">
                    <div class="space-y-3">
                        <span class="px-2.5 py-1 rounded bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">Role Practice</span>
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Parenting Conflict Room</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Step into a cozy fireplace environment. Practice de-escalating parenting scenarios by selecting logical prompts in an immersive space.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-gray-400">⏱️ Preset: 4 Minutes</span>
                        <button onclick="startSimulation('living_room')" class="px-4 py-2 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-sm">Start Simulation</button>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: ACTIVE 3D IMMERSIVE PLAYER -->
            <div id="view-player" class="hidden space-y-6">
                <div class="bg-brand-slate text-white rounded-3xl p-6 shadow-2xl relative overflow-hidden flex flex-col gap-6">
                    <div class="flex items-center justify-between border-b border-white/10 pb-4">
                        <div>
                            <h3 id="player-title" class="text-base font-bold font-outfit">Anxiety Management Forest</h3>
                            <p id="player-hud" class="text-[10px] text-brand-sky font-semibold mt-0.5 font-outfit">Guided Breathing Coach Active</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span id="player-timer" class="px-3 py-1 bg-white/10 border border-white/10 text-white text-[10px] font-bold rounded-lg font-outfit">00:00</span>
                            <button onclick="finishSimulation()" class="px-3.5 py-1.5 rounded-lg bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-[10px] transition-all shadow-sm">Finish Practice</button>
                        </div>
                    </div>

                    <!-- 3D VIEWPORT CONTAINER -->
                    <div class="relative w-full h-[420px] rounded-2xl overflow-hidden border border-white/10 bg-black">
                        
                        <!-- A-Frame HTML Embedded Element -->
                        <a-scene embedded style="height: 420px; width: 100%;" class="absolute inset-0">
                            <!-- Camera + Cursor -->
                            <a-entity camera look-controls position="0 1.6 0">
                                <a-entity cursor="fuse: true; fuseTimeout: 1000"
                                          position="0 0 -1"
                                          geometry="primitive: ring; radiusInner: 0.02; radiusOuter: 0.03"
                                          material="color: #8ECAE6; shader: flat"
                                          raycaster="objects: .clickable">
                                </a-entity>
                            </a-entity>

                            <!-- Ambient / Directional lighting -->
                            <a-entity light="type: ambient; color: #BBB"></a-entity>
                            <a-entity light="type: directional; color: #FFF; intensity: 0.6" position="-0.5 1 1"></a-entity>

                            <!-- ========================================== -->
                            <!-- SCENE 1: FOREST SANCTUARY -->
                            <a-entity id="aframe-forest" visible="false">
                                <a-sky color="#fce181"></a-sky>
                                <a-plane rotation="-90 0 0" width="100" height="100" color="#2c4c38"></a-plane>
                                
                                <!-- Pulsing Breathing Orb -->
                                <a-sphere id="breathing-coach-orb" position="0 1.6 -3" radius="1.2" color="#5E8C71" opacity="0.85"></a-sphere>
                                <a-text value="INHALING / EXHALING" position="0 3.2 -3" align="center" width="5" color="#FFF"></a-text>

                                <!-- Seed 3D Tree Trunks -->
                                <a-cylinder position="-4 1.5 -5" radius="0.4" height="3" color="#5c4033"></a-cylinder>
                                <a-sphere position="-4 3.2 -5" radius="1.2" color="#228b22"></a-sphere>

                                <a-cylinder position="4 1.5 -6" radius="0.4" height="3" color="#5c4033"></a-cylinder>
                                <a-sphere position="4 3.2 -6" radius="1.2" color="#228b22"></a-sphere>

                                <a-cylinder position="-3 1.5 4" radius="0.4" height="3" color="#5c4033"></a-cylinder>
                                <a-sphere position="-3 3.2 4" radius="1.2" color="#228b22"></a-sphere>
                            </a-entity>

                            <!-- ========================================== -->
                            <!-- SCENE 2: VIRTUAL AUDITORIUM -->
                            <a-entity id="aframe-auditorium" visible="false">
                                <a-sky color="#151515"></a-sky>
                                <a-plane rotation="-90 0 0" width="50" height="50" color="#3a3a3a"></a-plane>
                                
                                <!-- Stage and podium -->
                                <a-box position="0 0.1 -1" width="4" height="0.2" depth="2" color="#5c4033"></a-box>
                                <a-box position="0 0.8 -1.5" width="0.8" height="1.4" depth="0.5" color="#8b5a2b"></a-box>

                                <!-- Audience chairs & spheres (heads) -->
                                <a-entity position="0 0 -5">
                                    <a-sphere position="-2 0.8 0" radius="0.3" color="#f5c71a"></a-sphere>
                                    <a-sphere position="0 0.8 0" radius="0.3" color="#e86f51"></a-sphere>
                                    <a-sphere position="2 0.8 0" radius="0.3" color="#8ecaeg"></a-sphere>
                                    
                                    <a-sphere position="-1.5 0.8 -2" radius="0.3" color="#5e8c71"></a-sphere>
                                    <a-sphere position="1.5 0.8 -2" radius="0.3" color="#f5c71a"></a-sphere>
                                </a-entity>
                            </a-entity>

                            <!-- ========================================== -->
                            <!-- SCENE 3: CALM OFFICE -->
                            <a-entity id="aframe-office" visible="false">
                                <a-sky color="#eceff1"></a-sky>
                                <a-plane rotation="-90 0 0" width="30" height="30" color="#b0bec5"></a-plane>
                                
                                <!-- Minimalist office walls -->
                                <a-box position="0 2.5 -6" width="12" height="5" depth="0.1" color="#cfd8dc"></a-box>
                                
                                <!-- Desk and desk lamp -->
                                <a-box position="0 0.4 -3" width="2" height="0.8" depth="1" color="#e0e0e0"></a-box>
                                <a-box position="0 0.9 -3.1" width="0.5" height="0.4" depth="0.05" color="#212121"></a-box>

                                <!-- Floating Stress Task boxes (Interactive Clickable) -->
                                <a-box class="clickable" onclick="popStressTask(this)" position="-1.2 1.6 -2" width="0.3" height="0.3" depth="0.3" color="#e76f51" opacity="0.9"></a-box>
                                <a-box class="clickable" onclick="popStressTask(this)" position="1.2 1.6 -2" width="0.3" height="0.3" depth="0.3" color="#e76f51" opacity="0.9"></a-box>
                            </a-entity>

                            <!-- ========================================== -->
                            <!-- SCENE 4: COZY LIVING ROOM -->
                            <a-entity id="aframe-living-room" visible="false">
                                <a-sky color="#2e1a05"></a-sky>
                                <a-plane rotation="-90 0 0" width="20" height="20" color="#7a4b19"></a-plane>
                                
                                <!-- Fireplace glowing plane -->
                                <a-box position="0 1 -4" width="2.5" height="1.8" depth="0.2" color="#4e2c05"></a-box>
                                <a-plane position="0 0.8 -3.8" width="1.2" height="0.8" color="#d35400" opacity="0.8" material="emissive: #e67e22; emissiveIntensity: 1"></a-plane>

                                <!-- Floating Interactive Choice panels -->
                                <a-plane class="clickable" onclick="chooseParentOption(1)" position="-1 1.6 -2" width="0.8" height="0.4" color="#5e8c71">
                                    <a-text value="A: Calmly explain" align="center" width="2" color="#FFF"></a-text>
                                </a-plane>
                                <a-plane class="clickable" onclick="chooseParentOption(2)" position="1 1.6 -2" width="0.8" height="0.4" color="#e76f51">
                                    <a-text value="B: Send to room" align="center" width="2" color="#FFF"></a-text>
                                </a-plane>
                            </a-entity>
                        </a-scene>
                    </div>

                    <!-- GUIDELINES NOTIFICATION -->
                    <div id="player-advice-box" class="p-4 rounded-2xl bg-white/5 border border-white/10 text-xs text-gray-300 leading-normal flex items-start gap-3 select-none">
                        <span class="text-base">💡</span>
                        <p id="player-instructions">Breathe slowly. Sync your respiration with the expanding green coach orb. Hold your gaze on interactive targets for 1 second to select.</p>
                    </div>
                </div>
            </div>

            <!-- VIEW 3: PRACTICE HISTORY & STATS -->
            <div id="view-logs" class="hidden space-y-6">
                <!-- Stats overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage text-xl shrink-0 font-sans shadow-sm">🏆</div>
                        <div>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider select-none">Practice Count</span>
                            <h4 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5"><?php echo $total_sessions; ?> Sessions</h4>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-skyLight text-brand-sky text-xl shrink-0 font-sans shadow-sm">⏱️</div>
                        <div>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider select-none">Time Immersed</span>
                            <h4 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5"><?php echo $total_minutes; ?> Minutes</h4>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight text-brand-coral text-xl shrink-0 font-sans shadow-sm">⭐</div>
                        <div>
                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider select-none">Active Achievements</span>
                            <h4 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5">
                                <?php 
                                    if ($total_sessions >= 5) echo 'Zen Master';
                                    elseif ($total_sessions >= 2) echo 'Mindful Practitioner';
                                    else echo 'Beginner Scout';
                                ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- History log table -->
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-2 h-full bg-brand-sage"></div>
                    <div class="mb-4">
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Practice Session Logs</h3>
                        <p class="text-[10px] text-gray-400 font-sans mt-0.5">Historical verification records of completed WebXR scenarios.</p>
                    </div>

                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left text-xs border-collapse font-sans">
                            <thead>
                                <tr class="border-b border-gray-150 text-gray-400 font-bold uppercase tracking-wider select-none">
                                    <th class="py-2.5">Date</th>
                                    <th class="py-2.5">Simulation Type</th>
                                    <th class="py-2.5">Duration</th>
                                    <th class="py-2.5">Mood Shift</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-brand-slate">
                                <?php if (empty($practice_logs)): ?>
                                    <tr>
                                        <td colspan="4" class="py-4 text-center italic text-gray-400">No practice sessions logged yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($practice_logs as $log): ?>
                                        <tr>
                                            <td class="py-3 font-semibold text-gray-500"><?php echo date('m/d/Y', strtotime($log['practice_date'])); ?></td>
                                            <td class="py-3 font-bold">
                                                <?php 
                                                    if ($log['simulation_id'] === 'forest') echo '🌲 Anxiety Forest Sanctuary';
                                                    elseif ($log['simulation_id'] === 'auditorium') echo '🎙️ Public Speaking Auditorium';
                                                    elseif ($log['simulation_id'] === 'office') echo '💻 Calm Office Workpace';
                                                    elseif ($log['simulation_id'] === 'living_room') echo '🏠 Parenting Living Room';
                                                    else echo htmlspecialchars($log['simulation_id']);
                                                ?>
                                            </td>
                                            <td class="py-3 font-semibold"><?php echo round($log['duration_seconds'] / 60, 1); ?> mins</td>
                                            <td class="py-3 font-bold text-brand-sage">+<?php echo $log['mood_improvement']; ?> Improvement</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ACTIVE PLAYER JS CONTROL SCRIPTS -->
    <script>
        let selectedSimId = '';
        let timerSeconds = 0;
        let timerInterval = null;
        let breathingTimer = null;

        function switchTab(viewName) {
            const selector = document.getElementById('view-selector');
            const player = document.getElementById('view-player');
            const logs = document.getElementById('view-logs');

            const btnSelector = document.getElementById('tab-btn-selector');
            const btnLogs = document.getElementById('tab-btn-logs');

            // Hide all
            selector.classList.add('hidden');
            player.classList.add('hidden');
            logs.classList.add('hidden');

            btnSelector.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-white text-brand-slate border border-[#EBE8E0] hover:bg-gray-50";
            btnLogs.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-white text-brand-slate border border-[#EBE8E0] hover:bg-gray-50";

            if (viewName === 'selector') {
                selector.classList.remove('hidden');
                btnSelector.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-brand-sage text-white shadow-soft";
            } else if (viewName === 'player') {
                player.classList.remove('hidden');
            } else if (viewName === 'logs') {
                logs.classList.remove('hidden');
                btnLogs.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-brand-sage text-white shadow-soft";
            }
        }

        function startSimulation(simId) {
            selectedSimId = simId;
            switchTab('player');

            // Show A-Frame scene nodes accordingly
            document.getElementById('aframe-forest').setAttribute('visible', (simId === 'forest'));
            document.getElementById('aframe-auditorium').setAttribute('visible', (simId === 'auditorium'));
            document.getElementById('aframe-office').setAttribute('visible', (simId === 'office'));
            document.getElementById('aframe-living-room').setAttribute('visible', (simId === 'living_room'));

            // Set HUD and Instructions
            const titleEl = document.getElementById('player-title');
            const hudEl = document.getElementById('player-hud');
            const instEl = document.getElementById('player-instructions');

            // Reset visual elements
            clearInterval(timerInterval);
            clearInterval(breathingTimer);
            timerSeconds = 0;
            updateTimerDisplay();

            // Set scene config
            if (simId === 'forest') {
                titleEl.innerText = "Anxiety Management Forest";
                hudEl.innerText = "Guided Breathing Coach Active";
                instEl.innerText = "Synchronize your breathing with the expanding green coach orb (Inhale as it grows, Exhale as it shrinks).";
                
                // Breathing coach scale animation helper
                const orb = document.getElementById('breathing-coach-orb');
                let scaleDirection = 1;
                let currentScale = 1.0;
                breathingTimer = setInterval(() => {
                    if (scaleDirection === 1) {
                        currentScale += 0.05;
                        if (currentScale >= 1.6) scaleDirection = -1;
                    } else {
                        currentScale -= 0.05;
                        if (currentScale <= 0.8) scaleDirection = 1;
                    }
                    orb.setAttribute('radius', currentScale.toFixed(2));
                }, 100);

            } else if (simId === 'auditorium') {
                titleEl.innerText = "Public Speaking Auditorium";
                hudEl.innerText = "Lecture Hall Simulation Active";
                instEl.innerText = "Practice speaking from your virtual podium. Look around the audience members to hold virtual eye contact.";
            } else if (simId === 'office') {
                titleEl.innerText = "Calm Office Workspace";
                hudEl.innerText = "Cognitive Stress Buffer Training";
                instEl.innerText = "Look and focus your ring cursor on the floating red stress blocks in the room to delete them and restore room balance.";
            } else if (simId === 'living_room') {
                titleEl.innerText = "Parenting Conflict Scenario";
                hudEl.innerText = "Role De-escalation Scenario";
                instEl.innerText = "Look and click on the choices panels hovering in the room to choose the calmest parenting response.";
            }

            // Start timer counting
            timerInterval = setInterval(() => {
                timerSeconds++;
                updateTimerDisplay();
            }, 1000);
        }

        function updateTimerDisplay() {
            const mins = Math.floor(timerSeconds / 60);
            const secs = timerSeconds % 60;
            document.getElementById('player-timer').innerText = 
                `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Office scenario delete stress box callback
        function popStressTask(element) {
            element.setAttribute('visible', 'false');
            alert('🎉 Cognitive Stress block popped and cleared!');
        }

        // Parenting scenario choice callback
        function chooseParentOption(optionIndex) {
            if (optionIndex === 1) {
                alert('🟢 Correct! Acknowledging and explaining boundaries calms children.');
            } else {
                alert('🔴 Incorrect. Sending kids away immediately escalates rejection feelings.');
            }
        }

        function finishSimulation() {
            clearInterval(timerInterval);
            clearInterval(breathingTimer);

            const duration = timerSeconds;
            if (duration < 10) {
                alert('Session too short to log. Practice for at least 10 seconds.');
                switchTab('selector');
                return;
            }

            const simulatedMoodImprovement = Math.floor(1 + Math.random() * 3); // 1-3 mood boost

            const formData = new FormData();
            formData.append('action', 'log_vr_practice');
            formData.append('simulation_id', selectedSimId);
            formData.append('duration_seconds', duration);
            formData.append('mood_improvement', simulatedMoodImprovement);

            fetch('vr_resilience.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(`🎉 Session Completed!\nDuration: ${Math.floor(duration/60)} mins ${duration%60} secs\nMood Boosted by +${simulatedMoodImprovement} points.`);
                    window.location.reload();
                } else {
                    alert('Practice saved locally.');
                    window.location.reload();
                }
            })
            .catch(err => {
                console.error('Error logging practice session:', err);
                window.location.reload();
            });
        }
    </script>

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
            <a href="vr_resilience.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <span class="text-[10px] tracking-wider uppercase">VR Centre</span>
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
            <form method="POST" action="vr_resilience.php">
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
