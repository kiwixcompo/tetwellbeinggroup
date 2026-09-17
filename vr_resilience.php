<?php
/**
 * Tet Wellbeing Group - Virtual Reality Resilience Centre (vr_resilience.php)
 * Authenticated client page embedding A-Frame (WebXR) 3D simulations for guided anxiety and stress training.
 */
require_once 'db.php';
require_once 'gating_helper.php';

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

        if ($saved) {
            // Increment Wellbeing Passport Activity Count
            if (!isset($_SESSION['user_activity_count'])) {
                $_SESSION['user_activity_count'] = [];
            }
            $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        }
        
        echo json_encode(['status' => $saved ? 'success' : 'error', 'activity_count' => $_SESSION['user_activity_count'][$user_id] ?? null]);
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
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <!-- TOP NAVIGATION BAR -->
    <?php if (!isset($_GET['embed'])): ?>

<?php endif; ?>

    <!-- MAIN APP CONTAINER -->
    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-8 sm:px-6 lg:px-8">
        <!-- WELCOME SECTION -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-xs uppercase tracking-wider font-outfit">Virtual Reality</span>
                    <?php echo getPlanBadge('premium'); ?>
                </div>
                <h2 class="text-3xl font-bold font-outfit text-brand-slate mt-1">VR Resilience Centre</h2>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed max-w-2xl">
                    Immersive WebXR 3D sensory environments for self-regulation. Works on any browser, with mouse drag controls, or paired directly with a VR headset.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-52 h-28 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/safe_bridge.png" alt="VR Headset" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
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
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-xl transition-all group relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-36 h-36 bg-brand-sage/10 rounded-full blur-2xl group-hover:bg-brand-sage/20 transition-all pointer-events-none"></div>
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-[#E8EFEA] text-brand-sage font-bold text-[9px] uppercase tracking-wider font-outfit">🌿 Dynamic Sanctuary</span>
                            <span class="text-[10px] text-brand-sage font-bold flex items-center gap-1">✨ Spatial Breeze & Water</span>
                        </div>
                        <h3 class="text-xl font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">Anxiety Management Forest</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Escape into an organic alpine grove surrounded by dynamic pine canopies, bio-luminescent fireflies, stone riverbeds, and a harmonic celestial breathing orb synced to 4-4-6 cardiac coherence pacing.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 font-outfit">⏱️ Preset: 3 Mins</span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold">Coherence + Calm</span>
                        </div>
                        <button onclick="startSimulation('forest')" class="px-5 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-1.5 font-outfit">
                            <span>Enter VR</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Simulation 2: Virtual Auditorium -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-xl transition-all group relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-36 h-36 bg-brand-sky/10 rounded-full blur-2xl group-hover:bg-brand-sky/20 transition-all pointer-events-none"></div>
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-[#E8F4F8] text-brand-sky font-bold text-[9px] uppercase tracking-wider font-outfit">🎙️ Exposure Therapy</span>
                            <span class="text-[10px] text-brand-sky font-bold flex items-center gap-1">👥 Realistic Audience</span>
                        </div>
                        <h3 class="text-xl font-bold font-outfit text-brand-slate group-hover:text-brand-sky transition-colors">Public Speaking Lecture Hall</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Take the virtual stage at a spotlighted hardwood podium with teleprompter notes. Engage with a multi-row 3D seated audience practicing realistic idle breathing, eye contact, and attentive postures.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 font-outfit">⏱️ Preset: 5 Mins</span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-semibold">Confidence Builder</span>
                        </div>
                        <button onclick="startSimulation('auditorium')" class="px-5 py-2.5 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-1.5 font-outfit">
                            <span>Enter VR</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Simulation 3: Calm Office -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-xl transition-all group relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-36 h-36 bg-brand-coral/10 rounded-full blur-2xl group-hover:bg-brand-coral/20 transition-all pointer-events-none"></div>
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-brand-coralLight text-brand-coral font-bold text-[9px] uppercase tracking-wider font-outfit">💻 Executive Buffer</span>
                            <span class="text-[10px] text-brand-coral font-bold flex items-center gap-1">🏙️ Skyline Panorama</span>
                        </div>
                        <h3 class="text-xl font-bold font-outfit text-brand-slate group-hover:text-brand-coral transition-colors">Modern Panoramic Office</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Relax inside an architect-designed Scandinavian executive suite overlooking an expansive skyline. De-escalate chaotic work overwhelm by dissolving floating holographic task overload nodes into calming sparkles.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 font-outfit">⏱️ Preset: 3 Mins</span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-semibold">Cognitive Reset</span>
                        </div>
                        <button onclick="startSimulation('office')" class="px-5 py-2.5 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-1.5 font-outfit">
                            <span>Enter VR</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Simulation 4: Cozy Living Room -->
                <div class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] flex flex-col justify-between hover:shadow-xl transition-all group relative overflow-hidden">
                    <div class="absolute -top-10 -right-10 w-36 h-36 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all pointer-events-none"></div>
                    <div class="space-y-3 relative z-10">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[9px] uppercase tracking-wider font-outfit">🔥 Hearth Grounding</span>
                            <span class="text-[10px] text-amber-700 font-bold flex items-center gap-1">🪵 Crackling Fireplace</span>
                        </div>
                        <h3 class="text-xl font-bold font-outfit text-brand-slate group-hover:text-amber-700 transition-colors">Cozy Fireplace Timber Cabin</h3>
                        <p class="text-xs text-gray-500 leading-relaxed font-sans">
                            Immerse in a warm mountain cabin with realistic flickering wood hearth, drifting glowing embers, ambient acoustic warmth, and compassionate communication roleplay choice nodes.
                        </p>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 font-outfit">⏱️ Preset: 4 Mins</span>
                            <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">Empathy & Calm</span>
                        </div>
                        <button onclick="startSimulation('living_room')" class="px-5 py-2.5 rounded-xl bg-brand-slate hover:bg-brand-slate/90 text-white font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-1.5 font-outfit">
                            <span>Enter VR</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: ACTIVE 3D IMMERSIVE PLAYER -->
            <div id="view-player" class="hidden space-y-6">
                <div class="bg-brand-slate text-white rounded-3xl p-6 shadow-2xl relative overflow-hidden flex flex-col gap-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-white/10 pb-4 gap-4">
                        <div class="flex items-center gap-3">
                            <button onclick="switchTab('selector')" class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition-all text-xs flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <span>Exit</span>
                            </button>
                            <div>
                                <h3 id="player-title" class="text-base font-bold font-outfit text-white">Anxiety Management Forest</h3>
                                <p id="player-hud" class="text-[11px] text-brand-sky font-semibold mt-0.5 font-outfit flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                    <span>Guided Breathing Coach Active</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Spatial Sound Toggle -->
                            <button id="vr-audio-toggle" onclick="toggleVRAudio()" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 text-white text-xs font-bold rounded-xl font-outfit flex items-center gap-1.5 transition-all">
                                <span id="vr-audio-icon">🔊</span>
                                <span id="vr-audio-text">Audio On</span>
                            </button>
                            <!-- Fullscreen Toggle -->
                            <button onclick="toggleVRFullscreen()" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 border border-white/10 text-white text-xs font-bold rounded-xl font-outfit flex items-center gap-1.5 transition-all" title="Toggle Fullscreen">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                                <span class="hidden sm:inline">Fullscreen</span>
                            </button>
                            <!-- Session Timer -->
                            <span id="player-timer" class="px-3.5 py-1.5 bg-white/10 border border-white/10 text-emerald-400 text-xs font-mono font-bold rounded-xl shadow-inner">00:00</span>
                            <button onclick="finishSimulation()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-brand-coral to-rose-600 hover:from-brand-coralHover hover:to-rose-700 text-white font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-1.5 font-outfit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <span>Complete Practice</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3D VIEWPORT CONTAINER -->
                    <div id="vr-viewport-container" class="relative w-full h-[520px] rounded-3xl overflow-hidden border border-white/15 bg-black shadow-2xl">
                        
                        <!-- A-Frame HTML Embedded Element -->
                        <a-scene embedded style="height: 520px; width: 100%;" class="absolute inset-0">
                            
                            <!-- Camera Rig + Smooth Controls + Animated Reticle -->
                            <a-entity id="camera-rig" position="0 1.6 0">
                                <a-entity camera look-controls="pointerLockEnabled: false" position="0 0 0">
                                    <a-entity cursor="fuse: true; fuseTimeout: 1000"
                                              position="0 0 -1"
                                              geometry="primitive: ring; radiusInner: 0.015; radiusOuter: 0.025"
                                              material="color: #8ECAE6; shader: flat; opacity: 0.85"
                                              raycaster="objects: .clickable">
                                        <a-animation attribute="scale" begin="cursor-fusing" easing="ease-in" dur="1000" fill="backwards" from="1 1 1" to="0.3 0.3 0.3"></a-animation>
                                    </a-entity>
                                </a-entity>
                            </a-entity>

                            <!-- Lighting Setup: Warm Key + Ambient Fill -->
                            <a-entity light="type: ambient; color: #ECEFF1; intensity: 0.7"></a-entity>
                            <a-entity id="main-sunlight" light="type: directional; color: #FFF7ED; intensity: 0.9; castShadow: true" position="-2 4 3"></a-entity>

                            <!-- ============================================================== -->
                            <!-- SCENE 1: PHOTOREALISTIC ANXIETY FOREST SANCTUARY -->
                            <!-- ============================================================== -->
                            <a-entity id="aframe-forest" visible="false">
                                <!-- Atmospheric Gradient Sky with Horizon Glow -->
                                <a-sky color="#6CB2EB" radius="80"></a-sky>
                                
                                <!-- Rolling Forest Ground with Natural Moss Texturing -->
                                <a-plane rotation="-90 0 0" width="120" height="120" color="#26412D" material="roughness: 0.9; metalness: 0.1"></a-plane>
                                
                                <!-- Serene Center Clearing Platform & Polished Stepping River Stones -->
                                <a-cylinder position="0 0.04 0" radius="4" height="0.08" color="#35553C" material="roughness: 0.8"></a-cylinder>
                                <a-cylinder position="0 0.06 -3" radius="2.8" height="0.08" color="#2A4832"></a-cylinder>
                                <a-cylinder position="0 0.08 -3" radius="2.2" height="0.06" color="#1A3322" material="roughness: 0.2; metalness: 0.8"></a-cylinder>

                                <!-- Stepping Stones Path -->
                                <a-cylinder position="-0.6 0.08 -1.2" radius="0.35" height="0.06" color="#64748B" rotation="0 25 0"></a-cylinder>
                                <a-cylinder position="0.5 0.08 -1.8" radius="0.4" height="0.06" color="#475569" rotation="0 -15 0"></a-cylinder>
                                <a-cylinder position="-0.4 0.08 -2.4" radius="0.32" height="0.06" color="#64748B" rotation="0 10 0"></a-cylinder>

                                <!-- CELESTIAL GUIDED BREATHING ORB (Multi-layer Refractive Aura) -->
                                <a-entity id="breathing-orb-rig" position="0 1.9 -3.5">
                                    <!-- Inner Core Pulsing Glow -->
                                    <a-sphere id="breathing-coach-orb" radius="0.65" color="#5E8C71" material="emissive: #5E8C71; emissiveIntensity: 0.85; opacity: 0.92; roughness: 0.3"></a-sphere>
                                    <!-- Outer Translucent Halo Energy Ring -->
                                    <a-torus id="breathing-halo" radius="0.9" radius-tubular="0.02" color="#8ECAE6" material="emissive: #8ECAE6; emissiveIntensity: 0.9; opacity: 0.75" rotation="60 45 0"></a-torus>
                                    <a-torus id="breathing-halo-2" radius="0.85" radius-tubular="0.015" color="#F4E0A5" material="emissive: #F4E0A5; emissiveIntensity: 0.6; opacity: 0.6" rotation="-45 60 0"></a-torus>
                                    <!-- Dynamic Breathing Floating Text -->
                                    <a-text id="breathing-text-prompt" value="INHALE SLOWLY" position="0 1.2 0" align="center" width="4.5" color="#FFFFFF" font="sourcecodepro"></a-text>
                                    <a-text id="breathing-counter" value="4s" position="0 -1.0 0" align="center" width="3.5" color="#A7F3D0" font="sourcecodepro"></a-text>
                                </a-entity>

                                <!-- Organic Pine & Evergreen Trees (Layered Cones + Detailed Trunks) -->
                                <!-- Tree Cluster Left Fore -->
                                <a-entity id="tree-cluster-1" position="-4.5 0 -4.5">
                                    <a-cylinder position="0 1.2 0" radius="0.25" height="2.4" color="#4A2E18" material="roughness: 0.95"></a-cylinder>
                                    <a-cone position="0 2.6 0" radius-bottom="1.4" radius-top="0.1" height="2.2" color="#1F4D2B" material="roughness: 0.85"></a-cone>
                                    <a-cone position="0 3.8 0" radius-bottom="1.1" radius-top="0.05" height="2.0" color="#265C34" material="roughness: 0.8"></a-cone>
                                    <a-cone position="0 4.8 0" radius-bottom="0.75" radius-top="0" height="1.6" color="#316D3F"></a-cone>
                                </a-entity>
                                <!-- Tree Cluster Right Fore -->
                                <a-entity id="tree-cluster-2" position="4.8 0 -5.2">
                                    <a-cylinder position="0 1.4 0" radius="0.28" height="2.8" color="#3D2513" material="roughness: 0.95"></a-cylinder>
                                    <a-cone position="0 2.8 0" radius-bottom="1.5" radius-top="0.1" height="2.4" color="#193B22"></a-cone>
                                    <a-cone position="0 4.1 0" radius-bottom="1.2" radius-top="0.05" height="2.0" color="#23502E"></a-cone>
                                    <a-cone position="0 5.2 0" radius-bottom="0.8" radius-top="0" height="1.7" color="#2C643A"></a-cone>
                                </a-entity>
                                <!-- Tree Cluster Mid Left -->
                                <a-entity id="tree-cluster-3" position="-7 0 -1.5">
                                    <a-cylinder position="0 1.5 0" radius="0.32" height="3" color="#4A2E18"></a-cylinder>
                                    <a-cone position="0 3.2 0" radius-bottom="1.7" radius-top="0.1" height="2.6" color="#1F4D2B"></a-cone>
                                    <a-cone position="0 4.6 0" radius-bottom="1.3" radius-top="0.05" height="2.2" color="#2B6B3C"></a-cone>
                                </a-entity>
                                <!-- Tree Cluster Mid Right -->
                                <a-entity id="tree-cluster-4" position="6.5 0 -1.8">
                                    <a-cylinder position="0 1.3 0" radius="0.26" height="2.6" color="#382110"></a-cylinder>
                                    <a-cone position="0 2.7 0" radius-bottom="1.4" radius-top="0.1" height="2.2" color="#1B4225"></a-cone>
                                    <a-cone position="0 4.0 0" radius-bottom="1.0" radius-top="0.05" height="1.9" color="#275C34"></a-cone>
                                </a-entity>
                                <!-- Background Ridge Trees -->
                                <a-entity position="0 0 -14">
                                    <a-cone position="-5 4 0" radius-bottom="2.0" radius-top="0.1" height="4.5" color="#15331C"></a-cone>
                                    <a-cone position="-1.5 4.5 0" radius-bottom="2.2" radius-top="0.1" height="5.0" color="#1A3E23"></a-cone>
                                    <a-cone position="2 4.2 0" radius-bottom="2.1" radius-top="0.1" height="4.8" color="#17391F"></a-cone>
                                    <a-cone position="6 3.8 0" radius-bottom="1.9" radius-top="0.1" height="4.2" color="#15331C"></a-cone>
                                </a-entity>

                                <!-- Bio-luminescent Floating Firefly Particles -->
                                <a-sphere class="firefly-p1" position="-1.5 1.4 -2.5" radius="0.04" color="#FDE047" material="emissive: #FEF08A; emissiveIntensity: 1.5"></a-sphere>
                                <a-sphere class="firefly-p2" position="1.8 1.8 -3.2" radius="0.05" color="#A7F3D0" material="emissive: #6EE7B7; emissiveIntensity: 1.8"></a-sphere>
                                <a-sphere class="firefly-p3" position="-2.2 2.1 -4" radius="0.04" color="#FDE047" material="emissive: #FEF08A; emissiveIntensity: 1.6"></a-sphere>
                                <a-sphere class="firefly-p4" position="0.8 1.2 -1.8" radius="0.03" color="#67E8F9" material="emissive: #A5F3FC; emissiveIntensity: 2.0"></a-sphere>
                                <a-sphere class="firefly-p5" position="2.4 2.3 -4.5" radius="0.04" color="#FDE047" material="emissive: #FEF08A; emissiveIntensity: 1.5"></a-sphere>
                                <a-sphere class="firefly-p6" position="-0.5 2.5 -2.8" radius="0.035" color="#A7F3D0" material="emissive: #6EE7B7; emissiveIntensity: 1.7"></a-sphere>
                            </a-entity>

                            <!-- ============================================================== -->
                            <!-- SCENE 2: PHOTOREALISTIC PUBLIC SPEAKING AUDITORIUM -->
                            <!-- ============================================================== -->
                            <a-entity id="aframe-auditorium" visible="false">
                                <!-- Moody Theater Sky & Ambient Shell -->
                                <a-sky color="#0B0F19" radius="80"></a-sky>
                                
                                <!-- Stage Hardwood Flooring -->
                                <a-plane rotation="-90 0 0" width="30" height="20" color="#3B2313" material="roughness: 0.6; metalness: 0.1" position="0 0 0"></a-plane>
                                <!-- Auditorium Hall Carpet -->
                                <a-plane rotation="-90 0 0" width="40" height="40" color="#1E293B" material="roughness: 0.9" position="0 -0.8 -12"></a-plane>

                                <!-- Stage Step-Down Lip -->
                                <a-box position="0 -0.4 -2.8" width="16" height="0.8" depth="0.2" color="#26170B"></a-box>

                                <!-- Speaker's Hardwood Podium -->
                                <a-box position="0 0.6 -1.6" width="1.1" height="1.2" depth="0.7" color="#52321D" material="roughness: 0.5"></a-box>
                                <!-- Slanted Lectern Top -->
                                <a-box position="0 1.22 -1.65" width="1.15" height="0.08" depth="0.75" rotation="15 0 0" color="#3E2413"></a-box>
                                
                                <!-- Podium Microphone Stem & Capsule -->
                                <a-cylinder position="0.3 1.32 -1.6" radius="0.012" height="0.35" rotation="25 -10 0" color="#94A3B8" material="metalness: 0.9; roughness: 0.2"></a-cylinder>
                                <a-sphere position="0.25 1.48 -1.45" radius="0.03" color="#1E293B"></a-sphere>

                                <!-- Glowing Teleprompter Tablet -->
                                <a-plane position="0 1.27 -1.6" rotation="-75 0 0" width="0.7" height="0.45" color="#0F172A" material="emissive: #1E293B; emissiveIntensity: 0.5">
                                    <a-text value="SPEAK CLEARLY & SLOWLY\nTake deep breaths • 3 key points:\n1. Purpose  2. Impact  3. Next Steps" align="center" width="1.6" color="#38BDF8" position="0 0 0.01"></a-text>
                                </a-plane>

                                <!-- Dramatic Stage Spotlight Cone -->
                                <a-entity light="type: spot; color: #FFFBEB; intensity: 1.4; angle: 42; penumbra: 0.4; distance: 10" position="0 4.5 0.5" rotation="-75 0 0"></a-entity>
                                <a-entity light="type: spot; color: #38BDF8; intensity: 0.5; angle: 60" position="0 3.8 -4" rotation="-30 0 0"></a-entity>

                                <!-- Audience Feedback Teleprompter HUD Floating Near Stage Edge -->
                                <a-plane position="0 2.6 -3.2" width="2.6" height="0.55" color="#0F172A" opacity="0.85" material="emissive: #0284C7; emissiveIntensity: 0.2">
                                    <a-text id="audience-status-text" value="Audience Engagement: 94% • High Attention" align="center" width="3.2" color="#38BDF8"></a-text>
                                </a-plane>

                                <!-- Multi-Row Detailed 3D Audience (Seated, Varied Outfits, Realistic Idles) -->
                                <!-- Row 1: Distance -5.5 -->
                                <a-entity id="aud-row-1" position="0 -0.8 -5.5">
                                    <!-- Person 1 (Navy Suit) -->
                                    <a-entity id="aud-person-1" position="-2.6 0 0">
                                        <a-box position="0 0.45 0" width="0.6" height="0.5" depth="0.5" color="#1E3A8A"></a-box>
                                        <a-box position="0 0.9 0" width="0.5" height="0.6" depth="0.35" color="#1E293B"></a-box>
                                        <a-sphere id="aud-head-1" position="0 1.35 0" radius="0.18" color="#FBBF24"></a-sphere>
                                    </a-entity>
                                    <!-- Person 2 (Olive Jacket) -->
                                    <a-entity id="aud-person-2" position="-1.3 0 0">
                                        <a-box position="0 0.45 0" width="0.6" height="0.5" depth="0.5" color="#365314"></a-box>
                                        <a-box position="0 0.9 0" width="0.5" height="0.6" depth="0.35" color="#4D7C0F"></a-box>
                                        <a-sphere id="aud-head-2" position="0 1.35 0" radius="0.18" color="#D97706"></a-sphere>
                                    </a-entity>
                                    <!-- Person 3 (Burgundy Coat) -->
                                    <a-entity id="aud-person-3" position="0 0 0">
                                        <a-box position="0 0.45 0" width="0.6" height="0.5" depth="0.5" color="#881337"></a-box>
                                        <a-box position="0 0.9 0" width="0.5" height="0.6" depth="0.35" color="#9F1239"></a-box>
                                        <a-sphere id="aud-head-3" position="0 1.35 0" radius="0.18" color="#F59E0B"></a-sphere>
                                    </a-entity>
                                    <!-- Person 4 (Slate Gray) -->
                                    <a-entity id="aud-person-4" position="1.3 0 0">
                                        <a-box position="0 0.45 0" width="0.6" height="0.5" depth="0.5" color="#334155"></a-box>
                                        <a-box position="0 0.9 0" width="0.5" height="0.6" depth="0.35" color="#475569"></a-box>
                                        <a-sphere id="aud-head-4" position="0 1.35 0" radius="0.18" color="#FCD34D"></a-sphere>
                                    </a-entity>
                                    <!-- Person 5 (Teal Blazer) -->
                                    <a-entity id="aud-person-5" position="2.6 0 0">
                                        <a-box position="0 0.45 0" width="0.6" height="0.5" depth="0.5" color="#115E59"></a-box>
                                        <a-box position="0 0.9 0" width="0.5" height="0.6" depth="0.35" color="#0F766E"></a-box>
                                        <a-sphere id="aud-head-5" position="0 1.35 0" radius="0.18" color="#EAB308"></a-sphere>
                                    </a-entity>
                                </a-entity>

                                <!-- Row 2: Distance -8.0 (Slightly Elevated Seating) -->
                                <a-entity id="aud-row-2" position="0 -0.4 -8.0">
                                    <a-box position="0 0.3 0" width="10" height="0.6" depth="1.2" color="#0F172A"></a-box>
                                    <!-- Person 6 -->
                                    <a-entity position="-3.0 0.4 0">
                                        <a-box position="0 0.7 0" width="0.5" height="0.6" depth="0.3" color="#374151"></a-box>
                                        <a-sphere id="aud-head-6" position="0 1.15 0" radius="0.17" color="#FBBF24"></a-sphere>
                                    </a-entity>
                                    <!-- Person 7 -->
                                    <a-entity position="-1.5 0.4 0">
                                        <a-box position="0 0.7 0" width="0.5" height="0.6" depth="0.3" color="#1E3A8A"></a-box>
                                        <a-sphere id="aud-head-7" position="0 1.15 0" radius="0.17" color="#F59E0B"></a-sphere>
                                    </a-entity>
                                    <!-- Person 8 -->
                                    <a-entity position="0 0.4 0">
                                        <a-box position="0 0.7 0" width="0.5" height="0.6" depth="0.3" color="#065F46"></a-box>
                                        <a-sphere id="aud-head-8" position="0 1.15 0" radius="0.17" color="#D97706"></a-sphere>
                                    </a-entity>
                                    <!-- Person 9 -->
                                    <a-entity position="1.5 0.4 0">
                                        <a-box position="0 0.7 0" width="0.5" height="0.6" depth="0.3" color="#701A75"></a-box>
                                        <a-sphere id="aud-head-9" position="0 1.15 0" radius="0.17" color="#FCD34D"></a-sphere>
                                    </a-entity>
                                    <!-- Person 10 -->
                                    <a-entity position="3.0 0.4 0">
                                        <a-box position="0 0.7 0" width="0.5" height="0.6" depth="0.3" color="#1F2937"></a-box>
                                        <a-sphere id="aud-head-10" position="0 1.15 0" radius="0.17" color="#EAB308"></a-sphere>
                                    </a-entity>
                                </a-entity>
                            </a-entity>

                            <!-- ============================================================== -->
                            <!-- SCENE 3: PHOTOREALISTIC PANORAMIC CALM OFFICE -->
                            <!-- ============================================================== -->
                            <a-entity id="aframe-office" visible="false">
                                <!-- Sky Atmosphere Outside Window -->
                                <a-sky color="#93C5FD" radius="70"></a-sky>

                                <!-- Modern Hardwood Office Floor -->
                                <a-plane rotation="-90 0 0" width="30" height="30" color="#D4A373" material="roughness: 0.4; metalness: 0.05"></a-plane>
                                
                                <!-- Scandinavian Off-White Walls -->
                                <a-box position="0 3 -6" width="16" height="6" depth="0.2" color="#F8FAFC"></a-box>
                                <a-box position="-6 3 0" width="0.2" height="6" depth="12" color="#F1F5F9"></a-box>
                                
                                <!-- Floor-to-Ceiling Panoramic Window on Right Looking at Skyline -->
                                <a-box position="5.9 3 0" width="0.1" height="6" depth="12" color="#38BDF8" opacity="0.25" material="roughness: 0.1"></a-box>
                                <a-box position="5.9 0.1 0" width="0.3" height="0.2" depth="12" color="#0F172A"></a-box>
                                <a-box position="5.9 3 0" width="0.2" height="0.1" depth="12" color="#0F172A"></a-box>
                                <!-- Skyline Towers Visible Outside Window -->
                                <a-entity position="12 0 -2">
                                    <a-box position="0 4 -4" width="3.5" height="12" depth="3.5" color="#64748B"></a-box>
                                    <a-box position="2 6 -1" width="3" height="15" depth="3" color="#475569"></a-box>
                                    <a-box position="-2 5 3" width="4" height="13" depth="4" color="#94A3B8"></a-box>
                                </a-entity>

                                <!-- Designer Office Desk (Light Ash Oak + Matte Black Legs) -->
                                <a-box position="0 0.74 -2.2" width="2.4" height="0.08" depth="1.1" color="#E2E8F0" material="roughness: 0.5"></a-box>
                                <a-cylinder position="-1.05 0.36 -2.2" radius="0.03" height="0.72" color="#0F172A"></a-cylinder>
                                <a-cylinder position="1.05 0.36 -2.2" radius="0.03" height="0.72" color="#0F172A"></a-cylinder>
                                
                                <!-- Sleek Laptop on Desk -->
                                <a-box position="0 0.79 -2.1" width="0.55" height="0.02" depth="0.38" color="#CBD5E1"></a-box>
                                <a-box position="0 0.96 -2.28" width="0.55" height="0.35" depth="0.02" rotation="-15 0 0" color="#0F172A"></a-box>
                                <a-plane position="0 0.96 -2.26" width="0.52" height="0.32" rotation="-15 0 0" color="#0284C7" material="emissive: #38BDF8; emissiveIntensity: 0.4">
                                    <a-text value="FOCUS MODE\nNotifications Silenced" align="center" width="1.2" color="#FFFFFF" position="0 0 0.01"></a-text>
                                </a-plane>

                                <!-- Modern Desk Lamp with Warm Light -->
                                <a-cylinder position="-0.85 0.88 -2.4" radius="0.08" height="0.02" color="#1E293B"></a-cylinder>
                                <a-cylinder position="-0.85 1.05 -2.4" radius="0.015" height="0.35" color="#94A3B8"></a-cylinder>
                                <a-cone position="-0.85 1.25 -2.3" radius-bottom="0.12" radius-top="0.04" height="0.12" rotation="-30 0 0" color="#1E293B"></a-cone>
                                <a-entity light="type: point; color: #FEF3C7; intensity: 0.8; distance: 2.5" position="-0.85 1.2 -2.3"></a-entity>

                                <!-- Steaming Coffee Mug -->
                                <a-cylinder position="0.7 0.84 -1.9" radius="0.05" height="0.1" color="#F8FAFC"></a-cylinder>
                                <a-sphere id="coffee-steam" position="0.7 0.94 -1.9" radius="0.03" color="#FFFFFF" opacity="0.4"></a-sphere>

                                <!-- Indoor Potted Plant (Monstera / Ficus) -->
                                <a-cylinder position="1.8 0.4 -2.8" radius="0.3" height="0.8" color="#E2E8F0"></a-cylinder>
                                <a-sphere position="1.8 1.0 -2.8" radius="0.45" color="#166534" material="roughness: 0.8"></a-sphere>
                                <a-sphere position="1.6 1.2 -2.7" radius="0.3" color="#15803D"></a-sphere>
                                <a-sphere position="2.0 1.1 -2.9" radius="0.35" color="#22C55E"></a-sphere>

                                <!-- Floating Holographic Stress Nodes (Clickable to Dissolve) -->
                                <a-entity id="stress-node-1" class="clickable stress-bubble" onclick="popStressTask(this)" position="-1.3 1.7 -1.8">
                                    <a-sphere radius="0.22" color="#EF4444" material="emissive: #DC2626; emissiveIntensity: 0.8; opacity: 0.85"></a-sphere>
                                    <a-torus radius="0.28" radius-tubular="0.01" color="#FCA5A5" rotation="45 45 0"></a-torus>
                                    <a-text value="Urgent Inbox\n(Click to clear)" align="center" width="2" color="#FFFFFF" position="0 -0.32 0"></a-text>
                                </a-entity>

                                <a-entity id="stress-node-2" class="clickable stress-bubble" onclick="popStressTask(this)" position="1.3 1.8 -1.9">
                                    <a-sphere radius="0.22" color="#F97316" material="emissive: #EA580C; emissiveIntensity: 0.8; opacity: 0.85"></a-sphere>
                                    <a-torus radius="0.28" radius-tubular="0.01" color="#FDBA74" rotation="-30 60 0"></a-torus>
                                    <a-text value="Overlapping Deadlines\n(Click to clear)" align="center" width="2" color="#FFFFFF" position="0 -0.32 0"></a-text>
                                </a-entity>

                                <a-entity id="stress-node-3" class="clickable stress-bubble" onclick="popStressTask(this)" position="0 2.2 -1.7">
                                    <a-sphere radius="0.2" color="#DC2626" material="emissive: #991B1B; emissiveIntensity: 0.8; opacity: 0.85"></a-sphere>
                                    <a-torus radius="0.26" radius-tubular="0.01" color="#FECACA" rotation="90 0 0"></a-torus>
                                    <a-text value="Multitasking Fatigue\n(Click to clear)" align="center" width="2" color="#FFFFFF" position="0 -0.3 0"></a-text>
                                </a-entity>
                            </a-entity>

                            <!-- ============================================================== -->
                            <!-- SCENE 4: PHOTOREALISTIC COZY FIREPLACE TIMBER CABIN -->
                            <!-- ============================================================== -->
                            <a-entity id="aframe-living-room" visible="false">
                                <!-- Night Mountain Sky Outside -->
                                <a-sky color="#030712" radius="70"></a-sky>

                                <!-- Rustic Timber Wood Floor -->
                                <a-plane rotation="-90 0 0" width="24" height="24" color="#451A03" material="roughness: 0.7; metalness: 0.05"></a-plane>

                                <!-- Warm Cabin Timber Walls & Exposed Beams -->
                                <a-box position="0 2.8 -5" width="14" height="5.6" depth="0.2" color="#78350F" material="roughness: 0.8"></a-box>
                                <a-box position="-5 2.8 0" width="0.2" height="5.6" depth="12" color="#92400E"></a-box>
                                <a-box position="5 2.8 0" width="0.2" height="5.6" depth="12" color="#92400E"></a-box>
                                <!-- Ceiling Beams -->
                                <a-box position="0 5.4 -2" width="10" height="0.3" depth="0.4" color="#451A03"></a-box>
                                <a-box position="0 5.4 -4" width="10" height="0.3" depth="0.4" color="#451A03"></a-box>

                                <!-- Soft Wool Center Area Rug -->
                                <a-cylinder position="0 0.02 -2.5" radius="2.4" height="0.02" color="#D97706" material="roughness: 0.95"></a-cylinder>
                                <a-cylinder position="0 0.03 -2.5" radius="2.0" height="0.02" color="#FDE68A" material="roughness: 0.95"></a-cylinder>

                                <!-- Stone Hearth Fireplace Mantle -->
                                <a-box position="0 1.2 -4.8" width="3.2" height="2.4" depth="0.7" color="#334155" material="roughness: 0.9"></a-box>
                                <a-box position="0 2.45 -4.7" width="3.6" height="0.15" depth="0.9" color="#78350F"></a-box>
                                <!-- Dark Firebox Recess -->
                                <a-box position="0 0.75 -4.5" width="1.8" height="1.3" depth="0.5" color="#0F172A"></a-box>

                                <!-- Multi-Layer Animated Fireplace Flame Mesh -->
                                <a-entity id="fireplace-flames" position="0 0.35 -4.3">
                                    <!-- Birch Firewood Logs -->
                                    <a-cylinder position="-0.35 0.08 0" radius="0.09" height="0.9" rotation="20 45 60" color="#78350F"></a-cylinder>
                                    <a-cylinder position="0.35 0.08 0" radius="0.09" height="0.9" rotation="-20 -45 60" color="#451A03"></a-cylinder>
                                    <a-cylinder position="0 0.16 0" radius="0.08" height="0.8" rotation="0 0 90" color="#78350F"></a-cylinder>
                                    
                                    <!-- Glowing Coals Base -->
                                    <a-box position="0 0.06 0" width="1.1" height="0.1" depth="0.5" color="#DC2626" material="emissive: #EA580C; emissiveIntensity: 1.5"></a-box>
                                    
                                    <!-- Layered Flickering Flame Cones -->
                                    <a-cone id="flame-core-1" position="-0.15 0.45 0" radius-bottom="0.25" radius-top="0.02" height="0.7" color="#F59E0B" material="emissive: #FEF08A; emissiveIntensity: 2; opacity: 0.95"></a-cone>
                                    <a-cone id="flame-core-2" position="0.15 0.5 0" radius-bottom="0.22" radius-top="0.02" height="0.8" color="#EA580C" material="emissive: #F97316; emissiveIntensity: 2.2; opacity: 0.9"></a-cone>
                                    <a-cone id="flame-core-3" position="0 0.6 0" radius-bottom="0.18" radius-top="0" height="0.9" color="#EF4444" material="emissive: #FDE047; emissiveIntensity: 2.5; opacity: 0.85"></a-cone>
                                    
                                    <!-- Drifting Hearth Embers -->
                                    <a-sphere id="ember-1" position="-0.1 0.8 0" radius="0.02" color="#FDE047" material="emissive: #FDE047; emissiveIntensity: 3"></a-sphere>
                                    <a-sphere id="ember-2" position="0.15 0.95 0" radius="0.025" color="#F97316" material="emissive: #F97316; emissiveIntensity: 3"></a-sphere>
                                    <a-sphere id="ember-3" position="0.05 1.1 0" radius="0.015" color="#FEF08A" material="emissive: #FEF08A; emissiveIntensity: 3"></a-sphere>
                                    
                                    <!-- Realistic Dynamic Warm Flickering Fire Point Light -->
                                    <a-entity id="fire-light" light="type: point; color: #F97316; intensity: 1.8; distance: 6; castShadow: true" position="0 0.7 0.3"></a-entity>
                                </a-entity>

                                <!-- Floating Interactive Parenting De-escalation Scenario Cards -->
                                <a-entity id="parenting-ui-container" position="0 1.9 -2.5">
                                    <a-plane position="0 0.65 0" width="2.8" height="0.45" color="#0F172A" opacity="0.9" material="emissive: #F59E0B; emissiveIntensity: 0.3">
                                        <a-text value="Child Overwhelmed: 'I will not clean my toys!'" align="center" width="3.2" color="#FEF3C7"></a-text>
                                    </a-plane>

                                    <!-- Choice A: Compassionate Connection (Recommended) -->
                                    <a-plane class="clickable hover-card" onclick="chooseParentOption(1)" position="-1.1 0 0" width="1.4" height="0.65" color="#065F46" material="roughness: 0.2">
                                        <a-text value="Choice A (Calm):\n'You seem frustrated.\nLet's do the first two together.'" align="center" width="2" color="#FFFFFF" position="0 0 0.02"></a-text>
                                    </a-plane>

                                    <!-- Choice B: Authoritarian Escalation -->
                                    <a-plane class="clickable hover-card" onclick="chooseParentOption(2)" position="1.1 0 0" width="1.4" height="0.65" color="#991B1B" material="roughness: 0.2">
                                        <a-text value="Choice B (Reactive):\n'Go to your room right now\nuntil everything is clean!'" align="center" width="2" color="#FFFFFF" position="0 0 0.02"></a-text>
                                    </a-plane>
                                </a-entity>
                            </a-entity>
                        </a-scene>
                    </div>

                    <!-- GUIDELINES NOTIFICATION -->
                    <div id="player-advice-box" class="p-4 rounded-2xl bg-white/5 border border-white/10 text-xs text-gray-300 leading-normal flex items-start gap-3 select-none">
                        <span class="text-base">💡</span>
                        <p id="player-instructions">Breathe slowly. Sync your respiration with the expanding celestial coach orb. Hold your ring cursor on interactive targets for 1 second or click directly to interact.</p>
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

    <!-- ACTIVE PLAYER JS CONTROL & REALISTIC ANIMATION SCRIPTS -->
    <script>
        let selectedSimId = '';
        let timerSeconds = 0;
        let timerInterval = null;
        let animFrameId = null;
        let isAudioMuted = false;

        // Web Audio API Procedural Spatial Soundscape Engine
        let vrAudioCtx = null;
        let vrMasterGain = null;
        let vrSceneGain = null;
        let vrActiveNodes = [];

        function initVRAudio() {
            if (vrAudioCtx) return;
            try {
                vrAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                vrMasterGain = vrAudioCtx.createGain();
                vrMasterGain.gain.value = 0.6;
                vrMasterGain.connect(vrAudioCtx.destination);
            } catch (e) {
                console.warn('Web Audio not supported or blocked:', e);
            }
        }

        function stopVRAudio() {
            if (vrActiveNodes && vrActiveNodes.length) {
                vrActiveNodes.forEach(n => {
                    try { if (n.stop) n.stop(); n.disconnect(); } catch (e) {}
                });
                vrActiveNodes = [];
            }
            if (vrSceneGain) {
                try { vrSceneGain.disconnect(); } catch (e) {}
                vrSceneGain = null;
            }
        }

        function playSceneSoundscape(simId) {
            initVRAudio();
            if (!vrAudioCtx) return;
            if (vrAudioCtx.state === 'suspended') {
                vrAudioCtx.resume();
            }
            stopVRAudio();

            vrSceneGain = vrAudioCtx.createGain();
            vrSceneGain.gain.value = isAudioMuted ? 0 : 0.6;
            vrSceneGain.connect(vrMasterGain);

            if (simId === 'forest') {
                // 1. Gentle Mountain Forest Breeze (Filtered Pink Noise with LFO)
                const bufferSize = 2 * vrAudioCtx.sampleRate;
                const buffer = vrAudioCtx.createBuffer(1, bufferSize, vrAudioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                let b0=0, b1=0, b2=0, b3=0, b4=0, b5=0, b6=0;
                for (let i = 0; i < bufferSize; i++) {
                    let white = Math.random() * 2 - 1;
                    b0 = 0.99886 * b0 + white * 0.0555179;
                    b1 = 0.99332 * b1 + white * 0.0750759;
                    b2 = 0.96900 * b2 + white * 0.1538520;
                    b3 = 0.86650 * b3 + white * 0.3104856;
                    b4 = 0.55000 * b4 + white * 0.5329522;
                    b5 = -0.7616 * b5 - white * 0.0168980;
                    data[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.07;
                    b6 = white * 0.115926;
                }
                const noise = vrAudioCtx.createBufferSource();
                noise.buffer = buffer;
                noise.loop = true;

                const filter = vrAudioCtx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = 520;

                // Subtle slow wind sway modulation
                const lfo = vrAudioCtx.createOscillator();
                const lfoGain = vrAudioCtx.createGain();
                lfo.frequency.value = 0.18;
                lfoGain.gain.value = 180;
                lfo.connect(filter.frequency);
                lfo.start();

                noise.connect(filter);
                filter.connect(vrSceneGain);
                noise.start(0);

                // 2. Harmonic Celestial Resonance Drone (432Hz Calm Theta tuning)
                const oscDrone = vrAudioCtx.createOscillator();
                const oscDroneGain = vrAudioCtx.createGain();
                oscDrone.type = 'sine';
                oscDrone.frequency.value = 216; // 432Hz sub-harmonic
                oscDroneGain.gain.value = 0.08;
                oscDrone.connect(oscDroneGain);
                oscDroneGain.connect(vrSceneGain);
                oscDrone.start();

                vrActiveNodes.push(noise, filter, lfo, lfoGain, oscDrone, oscDroneGain);

            } else if (simId === 'auditorium') {
                // Room Tone & Soft Attentive Murmur (Filtered Low Tone)
                const oscTone = vrAudioCtx.createOscillator();
                const oscGain = vrAudioCtx.createGain();
                oscTone.type = 'triangle';
                oscTone.frequency.value = 110;
                oscGain.gain.value = 0.03;
                oscTone.connect(oscGain);
                oscGain.connect(vrSceneGain);
                oscTone.start();

                // Gentle room air filter
                const bufferSize = 2 * vrAudioCtx.sampleRate;
                const buffer = vrAudioCtx.createBuffer(1, bufferSize, vrAudioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) data[i] = (Math.random() * 2 - 1) * 0.02;
                const roomNoise = vrAudioCtx.createBufferSource();
                roomNoise.buffer = buffer;
                roomNoise.loop = true;
                const lowpass = vrAudioCtx.createBiquadFilter();
                lowpass.type = 'lowpass';
                lowpass.frequency.value = 350;
                roomNoise.connect(lowpass);
                lowpass.connect(vrSceneGain);
                roomNoise.start(0);

                vrActiveNodes.push(oscTone, oscGain, roomNoise, lowpass);

            } else if (simId === 'office') {
                // Gentle Scandinavian Office Acoustic White Noise
                const bufferSize = 2 * vrAudioCtx.sampleRate;
                const buffer = vrAudioCtx.createBuffer(1, bufferSize, vrAudioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) data[i] = (Math.random() * 2 - 1) * 0.015;
                const air = vrAudioCtx.createBufferSource();
                air.buffer = buffer;
                air.loop = true;
                const airFilter = vrAudioCtx.createBiquadFilter();
                airFilter.type = 'bandpass';
                airFilter.frequency.value = 750;
                airFilter.Q.value = 0.6;
                air.connect(airFilter);
                airFilter.connect(vrSceneGain);
                air.start(0);

                vrActiveNodes.push(air, airFilter);

            } else if (simId === 'living_room') {
                // Real Mountain Cabin Hearth Crackling Fire (Brown noise + High frequency crackles)
                const bufferSize = 2 * vrAudioCtx.sampleRate;
                const buffer = vrAudioCtx.createBuffer(1, bufferSize, vrAudioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                let lastOut = 0;
                for (let i = 0; i < bufferSize; i++) {
                    let white = Math.random() * 2 - 1;
                    // Intermittent crackle spikes
                    let crackle = (Math.random() > 0.997) ? (Math.random() * 0.8) : 0;
                    data[i] = ((lastOut + (0.02 * white)) / 1.02) * 1.5 + crackle;
                    lastOut = data[i];
                }
                const fireNoise = vrAudioCtx.createBufferSource();
                fireNoise.buffer = buffer;
                fireNoise.loop = true;
                const fireFilter = vrAudioCtx.createBiquadFilter();
                fireFilter.type = 'lowpass';
                fireFilter.frequency.value = 850;
                fireNoise.connect(fireFilter);
                fireFilter.connect(vrSceneGain);
                fireNoise.start(0);

                vrActiveNodes.push(fireNoise, fireFilter);
            }
        }

        function toggleVRAudio() {
            isAudioMuted = !isAudioMuted;
            const iconEl = document.getElementById('vr-audio-icon');
            const textEl = document.getElementById('vr-audio-text');
            if (isAudioMuted) {
                if (vrMasterGain) vrMasterGain.gain.setTargetAtTime(0, vrAudioCtx.currentTime, 0.05);
                iconEl.innerText = '🔇';
                textEl.innerText = 'Muted';
            } else {
                if (vrMasterGain && vrAudioCtx) vrMasterGain.gain.setTargetAtTime(0.6, vrAudioCtx.currentTime, 0.05);
                iconEl.innerText = '🔊';
                textEl.innerText = 'Audio On';
            }
        }

        function toggleVRFullscreen() {
            const container = document.getElementById('vr-viewport-container');
            if (!document.fullscreenElement) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

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
                stopVRAudio();
                if (animFrameId) cancelAnimationFrame(animFrameId);
                selector.classList.remove('hidden');
                btnSelector.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-brand-sage text-white shadow-soft";
            } else if (viewName === 'player') {
                player.classList.remove('hidden');
            } else if (viewName === 'logs') {
                stopVRAudio();
                if (animFrameId) cancelAnimationFrame(animFrameId);
                logs.classList.remove('hidden');
                btnLogs.className = "px-4 py-2 rounded-xl text-xs font-bold font-outfit transition-all bg-brand-sage text-white shadow-soft";
            }
        }

        function startSimulation(simId) {
            selectedSimId = simId;
            switchTab('player');

            // Toggle A-Frame entities visibility
            document.getElementById('aframe-forest').setAttribute('visible', (simId === 'forest'));
            document.getElementById('aframe-auditorium').setAttribute('visible', (simId === 'auditorium'));
            document.getElementById('aframe-office').setAttribute('visible', (simId === 'office'));
            document.getElementById('aframe-living-room').setAttribute('visible', (simId === 'living_room'));

            // Set HUD and Instructions
            const titleEl = document.getElementById('player-title');
            const hudEl = document.getElementById('player-hud');
            const instEl = document.getElementById('player-instructions');

            // Reset timers & animations
            clearInterval(timerInterval);
            if (animFrameId) cancelAnimationFrame(animFrameId);
            timerSeconds = 0;
            updateTimerDisplay();

            // Start Procedural Spatial Audio
            playSceneSoundscape(simId);

            if (simId === 'forest') {
                titleEl.innerText = "Anxiety Management Forest Sanctuary";
                hudEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span><span>4-4-6 Cardiac Coherence Breathing Active</span>';
                instEl.innerText = "Follow the expanding celestial breathing orb. Inhale deeply through your nose as the orb swells (4s), hold gently (4s), and release slowly through your mouth (6s). Look around the clearing to observe the bio-luminescent fireflies.";
            } else if (simId === 'auditorium') {
                titleEl.innerText = "Public Speaking Auditorium";
                hudEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span><span>Audience Immersion & Speech Teleprompter Active</span>';
                instEl.innerText = "Stand tall at your spotlighted podium. Read your teleprompter notes and practice sweeping your gaze across audience members to maintain calm, confident eye contact.";
            } else if (simId === 'office') {
                titleEl.innerText = "Modern Panoramic Calm Office";
                hudEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span><span>Executive De-escalation & Skyline Reset</span>';
                instEl.innerText = "Breathe with the peaceful panoramic vista. Aim your ring reticle or click on the floating stress task bubbles (Urgent Inbox, Deadlines) to pop and dissolve them into calm particles.";
            } else if (simId === 'living_room') {
                titleEl.innerText = "Cozy Fireplace Timber Cabin";
                hudEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span><span>Hearth Grounding & Empathetic Communication Practice</span>';
                instEl.innerText = "Absorb the flickering warmth of the wood hearth. Review the parenting challenge prompt floating in front of you and choose the calmest response panel.";
            }

            // Start Animation Engine
            startRealisticAnimationLoop(simId);

            // Start session clock
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

        // Realistic Real-Time Physics & Animation Engine
        function startRealisticAnimationLoop(simId) {
            let startTime = performance.now();

            function loop(now) {
                const elapsed = (now - startTime) / 1000; // seconds

                if (simId === 'forest') {
                    // 1. 4-4-6 Cardiac Coherence Breathing Orb Pacing (14s total cycle)
                    const cycleTime = elapsed % 14.0;
                    const orb = document.getElementById('breathing-coach-orb');
                    const halo = document.getElementById('breathing-halo');
                    const halo2 = document.getElementById('breathing-halo-2');
                    const promptEl = document.getElementById('breathing-text-prompt');
                    const countEl = document.getElementById('breathing-counter');

                    let scale = 0.65;
                    let haloRot = (elapsed * 25) % 360;

                    if (cycleTime < 4.0) {
                        // Inhale phase (0s -> 4s)
                        const progress = cycleTime / 4.0;
                        scale = 0.65 + 0.65 * Math.sin(progress * (Math.PI / 2));
                        if (promptEl) promptEl.setAttribute('value', 'INHALE SLOWLY');
                        if (countEl) countEl.setAttribute('value', (4 - Math.floor(cycleTime)) + 's');
                        if (orb) orb.setAttribute('color', '#5E8C71');
                    } else if (cycleTime < 8.0) {
                        // Hold phase (4s -> 8s)
                        scale = 1.30 + 0.05 * Math.sin((cycleTime - 4.0) * Math.PI);
                        if (promptEl) promptEl.setAttribute('value', 'GENTLY HOLD');
                        if (countEl) countEl.setAttribute('value', (8 - Math.floor(cycleTime)) + 's');
                        if (orb) orb.setAttribute('color', '#8ECAE6');
                    } else {
                        // Exhale phase (8s -> 14s)
                        const progress = (cycleTime - 8.0) / 6.0;
                        scale = 1.30 - 0.65 * Math.sin(progress * (Math.PI / 2));
                        if (promptEl) promptEl.setAttribute('value', 'EXHALE & RELEASE');
                        if (countEl) countEl.setAttribute('value', (14 - Math.floor(cycleTime)) + 's');
                        if (orb) orb.setAttribute('color', '#A7F3D0');
                    }

                    if (orb) orb.setAttribute('radius', scale.toFixed(3));
                    if (halo) halo.setAttribute('rotation', `${60 + Math.sin(elapsed)*15} ${haloRot.toFixed(1)} 0`);
                    if (halo2) halo2.setAttribute('rotation', `${-45 - Math.cos(elapsed)*15} ${(-haloRot).toFixed(1)} 0`);

                    // 2. Organic Wind Sway for Trees
                    const sway1 = Math.sin(elapsed * 1.2) * 2.2;
                    const sway2 = Math.cos(elapsed * 0.9) * 1.8;
                    const t1 = document.getElementById('tree-cluster-1');
                    const t2 = document.getElementById('tree-cluster-2');
                    if (t1) t1.setAttribute('rotation', `0 0 ${sway1.toFixed(2)}`);
                    if (t2) t2.setAttribute('rotation', `0 0 ${sway2.toFixed(2)}`);

                    // 3. Bio-luminescent Floating Fireflies Motion
                    const p1 = document.querySelector('.firefly-p1');
                    const p2 = document.querySelector('.firefly-p2');
                    const p3 = document.querySelector('.firefly-p3');
                    const p4 = document.querySelector('.firefly-p4');
                    if (p1) p1.setAttribute('position', `${-1.5 + Math.sin(elapsed*0.8)*0.5} ${1.4 + Math.sin(elapsed*1.5)*0.3} ${-2.5 + Math.cos(elapsed*0.7)*0.4}`);
                    if (p2) p2.setAttribute('position', `${1.8 + Math.cos(elapsed*0.6)*0.4} ${1.8 + Math.sin(elapsed*1.2)*0.3} ${-3.2 + Math.sin(elapsed*0.5)*0.5}`);
                    if (p3) p3.setAttribute('position', `${-2.2 + Math.sin(elapsed*0.5)*0.6} ${2.1 + Math.cos(elapsed*1.1)*0.35} ${-4.0 + Math.sin(elapsed*0.9)*0.4}`);
                    if (p4) p4.setAttribute('position', `${0.8 + Math.cos(elapsed*1.1)*0.5} ${1.2 + Math.sin(elapsed*1.8)*0.25} ${-1.8 + Math.cos(elapsed*0.8)*0.3}`);

                } else if (simId === 'auditorium') {
                    // Realistic Audience Idle Postures & Head Tilts
                    const h1 = document.getElementById('aud-head-1');
                    const h2 = document.getElementById('aud-head-2');
                    const h3 = document.getElementById('aud-head-3');
                    const h4 = document.getElementById('aud-head-4');
                    const h5 = document.getElementById('aud-head-5');

                    if (h1) h1.setAttribute('rotation', `${Math.sin(elapsed * 0.9) * 5} ${Math.cos(elapsed * 0.7) * 8} 0`);
                    if (h2) h2.setAttribute('rotation', `${Math.sin(elapsed * 1.1 + 1) * 6} ${Math.sin(elapsed * 0.5) * 6} 0`);
                    if (h3) h3.setAttribute('rotation', `${Math.cos(elapsed * 0.8 + 2) * 4} ${Math.sin(elapsed * 0.6) * 10} 0`);
                    if (h4) h4.setAttribute('rotation', `${Math.sin(elapsed * 0.7 + 3) * 5} ${Math.cos(elapsed * 0.8) * 7} 0`);
                    if (h5) h5.setAttribute('rotation', `${Math.cos(elapsed * 1.2 + 4) * 6} ${Math.sin(elapsed * 0.5) * 9} 0`);

                } else if (simId === 'office') {
                    // Floating stress nodes harmonic bobbing
                    const sn1 = document.getElementById('stress-node-1');
                    const sn2 = document.getElementById('stress-node-2');
                    const sn3 = document.getElementById('stress-node-3');
                    const steam = document.getElementById('coffee-steam');

                    if (sn1 && sn1.getAttribute('visible') !== 'false') {
                        sn1.setAttribute('position', `-1.3 ${1.7 + Math.sin(elapsed * 1.5) * 0.08} -1.8`);
                        sn1.setAttribute('rotation', `0 ${(elapsed * 30) % 360} 0`);
                    }
                    if (sn2 && sn2.getAttribute('visible') !== 'false') {
                        sn2.setAttribute('position', `1.3 ${1.8 + Math.cos(elapsed * 1.4) * 0.08} -1.9`);
                        sn2.setAttribute('rotation', `0 ${(-elapsed * 25) % 360} 0`);
                    }
                    if (sn3 && sn3.getAttribute('visible') !== 'false') {
                        sn3.setAttribute('position', `0 ${2.2 + Math.sin(elapsed * 1.8) * 0.07} -1.7`);
                    }
                    if (steam) {
                        steam.setAttribute('position', `0.7 ${0.94 + Math.sin(elapsed * 2) * 0.04} -1.9`);
                        steam.setAttribute('opacity', (0.3 + Math.sin(elapsed * 3) * 0.15).toFixed(2));
                    }

                } else if (simId === 'living_room') {
                    // Procedural Wood Hearth Fireplace Flicker & Dancing Point Light
                    const f1 = document.getElementById('flame-core-1');
                    const f2 = document.getElementById('flame-core-2');
                    const f3 = document.getElementById('flame-core-3');
                    const fireLight = document.getElementById('fire-light');
                    const ember1 = document.getElementById('ember-1');
                    const ember2 = document.getElementById('ember-2');

                    const flicker1 = 0.6 + Math.sin(elapsed * 12) * 0.12 + Math.cos(elapsed * 19) * 0.08;
                    const flicker2 = 0.7 + Math.cos(elapsed * 14) * 0.14 + Math.sin(elapsed * 22) * 0.06;
                    const flicker3 = 0.8 + Math.sin(elapsed * 16) * 0.15 + Math.cos(elapsed * 25) * 0.08;

                    if (f1) f1.setAttribute('height', flicker1.toFixed(2));
                    if (f2) f2.setAttribute('height', flicker2.toFixed(2));
                    if (f3) f3.setAttribute('height', flicker3.toFixed(2));

                    // Dancing warm point light cast
                    if (fireLight) {
                        const lightIntensity = 1.6 + Math.sin(elapsed * 15) * 0.35 + Math.cos(elapsed * 23) * 0.25;
                        fireLight.setAttribute('light', `type: point; color: #F97316; intensity: ${lightIntensity.toFixed(2)}; distance: 6; castShadow: true`);
                    }

                    // Drifting embers rising upward and resetting
                    const emberCycle1 = (elapsed * 0.8) % 1.2;
                    const emberCycle2 = ((elapsed + 0.6) * 0.7) % 1.2;
                    if (ember1) {
                        ember1.setAttribute('position', `${-0.1 + Math.sin(elapsed * 5) * 0.05} ${0.7 + emberCycle1} 0`);
                        ember1.setAttribute('material', `emissive: #FDE047; emissiveIntensity: 3; opacity: ${(1 - emberCycle1 / 1.2).toFixed(2)}`);
                    }
                    if (ember2) {
                        ember2.setAttribute('position', `${0.15 + Math.cos(elapsed * 4) * 0.05} ${0.7 + emberCycle2} 0`);
                        ember2.setAttribute('material', `emissive: #F97316; emissiveIntensity: 3; opacity: ${(1 - emberCycle2 / 1.2).toFixed(2)}`);
                    }
                }

                animFrameId = requestAnimationFrame(loop);
            }

            animFrameId = requestAnimationFrame(loop);
        }

        // Office scenario: Pop & dissolve stress bubble into sparkles
        function popStressTask(element) {
            // Play gentle pop tone using Web Audio
            if (vrAudioCtx && !isAudioMuted) {
                try {
                    const popOsc = vrAudioCtx.createOscillator();
                    const popGain = vrAudioCtx.createGain();
                    popOsc.type = 'sine';
                    popOsc.frequency.setValueAtTime(580, vrAudioCtx.currentTime);
                    popOsc.frequency.exponentialRampToValueAtTime(880, vrAudioCtx.currentTime + 0.15);
                    popGain.gain.setValueAtTime(0.3, vrAudioCtx.currentTime);
                    popGain.gain.exponentialRampToValueAtTime(0.01, vrAudioCtx.currentTime + 0.18);
                    popOsc.connect(popGain);
                    popGain.connect(vrMasterGain);
                    popOsc.start();
                    popOsc.stop(vrAudioCtx.currentTime + 0.2);
                } catch (e) {}
            }

            // Quick shrink and disappear
            element.setAttribute('visible', 'false');
            
            // In-scene toast feedback
            const adviceBox = document.getElementById('player-instructions');
            if (adviceBox) {
                adviceBox.innerText = '✨ Stress Node Dissolved! You have reclaimed cognitive mental space. Breathe in clarity.';
            }
        }

        // Parenting scenario choice callback with instant audio-visual guidance
        function chooseParentOption(optionIndex) {
            const adviceBox = document.getElementById('player-instructions');
            if (optionIndex === 1) {
                if (vrAudioCtx && !isAudioMuted) {
                    try {
                        const chime = vrAudioCtx.createOscillator();
                        const chimeGain = vrAudioCtx.createGain();
                        chime.type = 'sine';
                        chime.frequency.setValueAtTime(523.25, vrAudioCtx.currentTime); // C5
                        chime.frequency.setValueAtTime(659.25, vrAudioCtx.currentTime + 0.1); // E5
                        chimeGain.gain.setValueAtTime(0.3, vrAudioCtx.currentTime);
                        chimeGain.gain.exponentialRampToValueAtTime(0.01, vrAudioCtx.currentTime + 0.4);
                        chime.connect(chimeGain);
                        chimeGain.connect(vrMasterGain);
                        chime.start();
                        chime.stop(vrAudioCtx.currentTime + 0.45);
                    } catch (e) {}
                }
                if (adviceBox) {
                    adviceBox.innerText = '🟢 Excellent! Validating your child’s emotions before enforcing boundaries prevents fight-or-flight escalation and builds lifelong trust.';
                }
            } else {
                if (adviceBox) {
                    adviceBox.innerText = '🟡 Reflection: Threatening immediate isolation when a child is overwhelmed can amplify abandonment panic. Try acknowledging feelings first.';
                }
            }
        }

        function finishSimulation() {
            clearInterval(timerInterval);
            if (animFrameId) cancelAnimationFrame(animFrameId);
            stopVRAudio();

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
                    const passportMsg = data.activity_count ? `\n🌟 Wellbeing Passport Updated: ${data.activity_count} activities completed!` : '';
                    alert(`🎉 Session Completed!\nDuration: ${Math.floor(duration/60)} mins ${duration%60} secs\nMood Boosted by +${simulatedMoodImprovement} points.${passportMsg}`);
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
    <?php if (!isset($_GET['embed'])): ?>
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
<?php endif; ?>

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
