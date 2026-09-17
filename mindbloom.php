<?php
session_start();
require_once 'db.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'home';
$tab = $_GET['tab'] ?? 'home';

// Handle POST actions for Micro-Interventions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    
    // 1. Save Energy Battery Check
    if ($post_action === 'save_energy_check') {
        header('Content-Type: application/json');
        $level = (int)($_POST['energy_level'] ?? 50);
        $notes = trim($_POST['notes'] ?? '');
        
        $_SESSION['user_energy_checks'][$user_id][] = [
            'id' => time(),
            'level' => $level,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'display_date' => date('M j, Y - g:ia')
        ];
        
        // Award activity point to Wellbeing Passport
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Energy check recorded!',
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }

    // 2. Save Daily Gratitude
    if ($post_action === 'save_gratitude') {
        header('Content-Type: application/json');
        $win1 = trim($_POST['win1'] ?? '');
        $win2 = trim($_POST['win2'] ?? '');
        $win3 = trim($_POST['win3'] ?? '');
        
        $_SESSION['user_gratitude_logs'][$user_id][] = [
            'id' => time(),
            'wins' => array_filter([$win1, $win2, $win3]),
            'created_at' => date('Y-m-d H:i:s'),
            'display_date' => date('M j, Y - g:ia')
        ];
        
        // Award activity point to Wellbeing Passport
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Gratitude saved!',
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }

    // 3. Save Reflection Journal
    if ($post_action === 'save_reflection') {
        header('Content-Type: application/json');
        $text = trim($_POST['text'] ?? '');
        
        $_SESSION['user_reflections'][$user_id][] = [
            'id' => time(),
            'text' => $text,
            'created_at' => date('Y-m-d H:i:s'),
            'display_date' => date('M j, Y - g:ia')
        ];
        
        // Award activity point to Wellbeing Passport
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Reflection saved to your Passport!',
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }

    // 4. Log Grounding Completion
    if ($post_action === 'complete_grounding') {
        header('Content-Type: application/json');
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        echo json_encode([
            'status' => 'success',
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MindBloom - TET Wellbeing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            sage: '#5E8C71',
                            sageLight: '#E8F0EC',
                            sageHover: '#4A725C',
                            coral: '#E76F51',
                            coralLight: '#FCECE8',
                            coralHover: '#D05A3E',
                            sky: '#8ECAE6',
                            skyLight: '#EAF5FA',
                            navy: '#2A4365',
                            sand: '#F4A261',
                            sandLight: '#FDF2E9',
                            bg: '#F9F8F6',
                            slate: '#2D3748',
                            inputBg: '#F1F5F9'
                        }
                    },
                    boxShadow: {
                        'soft': '0 10px 40px -10px rgba(0,0,0,0.05)',
                        'inner-soft': 'inset 0 2px 4px 0 rgba(0, 0, 0, 0.03)',
                        'card': '0 4px 20px -2px rgba(0,0,0,0.03)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F9F8F6; font-family: 'Outfit', sans-serif; color: #2D3748; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(235, 232, 224, 0.8); }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?php include 'nav_menu.php'; ?>

    <!-- MAIN BODY CONTENT (Phase 3 Tab/Sidebar Layout) -->
    <main class="flex-grow mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-12 fade-in">
        
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT SIDEBAR (20%) -->
            <div class="w-full lg:w-1/4 xl:w-1/5 shrink-0">
                <div class="sticky top-28 space-y-1">
<?php require_once 'gating_helper.php'; ?>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-3">Everyday Calm</h3>
                    
                    <a href="?tab=home" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-leaf w-5 text-center"></i> 
                            MindBloom Core
                        </span>
                        <?php echo getPlanBadge('free'); ?>
                    </a>

                    <a href="?tab=breathing" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'breathing' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-lungs w-5 text-center"></i> 
                            Guided Breathing
                        </span>
                        <?php echo getPlanBadge('guest'); ?>
                    </a>
                    
                    <a href="?tab=truevent" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'truevent' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-book-open w-5 text-center"></i> 
                            Safe Space (TrueVent)
                        </span>
                        <?php echo getPlanBadge('basic'); ?>
                    </a>
                    
                    <div class="pt-4 mt-2 border-t border-gray-100">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-3">Deeper Support & Immersion</h3>

                        <a href="?tab=assessments" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'assessments' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                            <span class="flex items-center gap-3">
                                <i class="fa-solid fa-clipboard-check w-5 text-center"></i> 
                                Clinical Self-Checks
                            </span>
                            <?php echo getPlanBadge('free'); ?>
                        </a>
                        
                        <a href="?tab=ambient" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'ambient' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                            <span class="flex items-center gap-3">
                                <i class="fa-solid fa-music w-5 text-center"></i> 
                                Ambient Mixer
                            </span>
                            <?php echo getPlanBadge('guest'); ?>
                        </a>

                        <a href="?tab=vr" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'vr' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                            <span class="flex items-center gap-3">
                                <i class="fa-solid fa-vr-cardboard w-5 text-center"></i> 
                                VR Resilience
                            </span>
                            <?php echo getPlanBadge('premium'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RIGHT CONTENT AREA (80%) -->
            <div class="w-full lg:w-3/4 xl:w-4/5">
                <?php if ($tab === 'home'): ?>
                    <!-- Original MindBloom Content -->
                    <div class="max-w-4xl mx-auto">
                        
        
        <?php if ($action === 'home'): ?>
        
        <!-- Welcome Hero -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 mb-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-brand-sageLight text-brand-sage border border-brand-sage/20">
                    <i class="fa-solid fa-seedling mr-1"></i> Daily Micro-Interventions
                </span>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-brand-slate tracking-tight mb-2">MindBloom</h1>
            <p class="text-gray-500 max-w-2xl mx-auto text-sm md:text-base">Gentle, 2-minute restorative activities to steady your nervous system and center your thoughts.</p>
        </div>

        <!-- NEWBIE MOOD NAVIGATOR ("How are you feeling right now?") -->
        <div class="bg-gradient-to-r from-emerald-50/70 via-white to-teal-50/70 rounded-3xl p-6 sm:p-7 border border-brand-sage/30 shadow-card mb-10">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-brand-slate flex items-center gap-2">
                        <i class="fa-regular fa-compass text-brand-sage"></i>
                        How are you feeling right now?
                    </h3>
                    <p class="text-xs text-gray-500">Pick what fits your current state to find the gentlest reset for you.</p>
                </div>
                <span class="text-[11px] font-bold text-brand-sage bg-white px-3 py-1 rounded-full border border-brand-sage/20 hidden sm:inline-block">
                    Beginner Compass
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                <a href="?action=grounding" class="p-3.5 rounded-2xl bg-white hover:bg-emerald-50 border border-gray-200/80 hover:border-brand-sage/50 transition-all text-center group shadow-sm">
                    <span class="text-2xl block mb-1">🌪️</span>
                    <span class="text-xs font-bold text-brand-slate group-hover:text-brand-sage block">Overwhelmed</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5">5-4-3-2-1 Reset</span>
                </a>
                <a href="?tab=breathing" class="p-3.5 rounded-2xl bg-white hover:bg-sky-50 border border-gray-200/80 hover:border-brand-sky/50 transition-all text-center group shadow-sm">
                    <span class="text-2xl block mb-1">🫁</span>
                    <span class="text-xs font-bold text-brand-slate group-hover:text-brand-sky block">Tense / Racing</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5">Guided Breath</span>
                </a>
                <a href="?tab=truevent" class="p-3.5 rounded-2xl bg-white hover:bg-rose-50 border border-gray-200/80 hover:border-brand-coral/50 transition-all text-center group shadow-sm">
                    <span class="text-2xl block mb-1">💭</span>
                    <span class="text-xs font-bold text-brand-slate group-hover:text-brand-coral block">Need to Vent</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5">Safe TrueVent</span>
                </a>
                <a href="?action=checkin" class="p-3.5 rounded-2xl bg-white hover:bg-amber-50 border border-gray-200/80 hover:border-amber-400/50 transition-all text-center group shadow-sm">
                    <span class="text-2xl block mb-1">🪫</span>
                    <span class="text-xs font-bold text-brand-slate group-hover:text-amber-600 block">Drained</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5">Battery Check</span>
                </a>
                <a href="?action=gratitude" class="p-3.5 rounded-2xl bg-white hover:bg-purple-50 border border-gray-200/80 hover:border-purple-400/50 transition-all text-center group shadow-sm col-span-2 sm:col-span-1">
                    <span class="text-2xl block mb-1">✨</span>
                    <span class="text-xs font-bold text-brand-slate group-hover:text-purple-600 block">Reflective</span>
                    <span class="text-[10px] text-gray-400 block mt-0.5">Daily Gratitude</span>
                </a>
            </div>
        </div>

        <!-- 4 Core Micro-Intervention Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grounding -->
            <a href="?action=grounding" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-sage/10 group-hover:text-brand-sage/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zM11 7h2v5.414l3.707 3.707-1.414 1.414L11 13.12V7z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center mb-4">
                    <i class="fa-solid fa-hand-holding-heart text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">5-4-3-2-1 Sensory Grounding</h3>
                <p class="text-sm text-gray-500 mb-4">Interactive sensory countdown to swiftly anchor racing thoughts when feeling stressed.</p>
                <span class="text-xs font-semibold text-brand-sage flex items-center">
                    Start Interactive Wizard 
                    <i class="fa-solid fa-arrow-right ml-1.5 transition-transform group-hover:translate-x-1"></i>
                </span>
            </a>

            <!-- Journaling -->
            <a href="?action=journal" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-sky/10 group-hover:text-brand-sky/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2zm0 2v16h12V4H6zm2 4h8v2H8V8zm0 4h5v2H8v-2zm0 4h5v2H8v-2z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-skyLight text-brand-slate flex items-center justify-center mb-4">
                    <i class="fa-solid fa-pen-nib text-xl text-sky-600"></i>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Gentle Reflection</h3>
                <p class="text-sm text-gray-500 mb-4">Process your emotions with speech dictation and private saving to your Passport.</p>
                <span class="text-xs font-semibold text-sky-600 flex items-center">
                    Write or Speak 
                    <i class="fa-solid fa-arrow-right ml-1.5 transition-transform group-hover:translate-x-1"></i>
                </span>
            </a>

            <!-- Energy Check -->
            <a href="?action=checkin" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-coral/10 group-hover:text-brand-coral/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center mb-4">
                    <i class="fa-solid fa-battery-half text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Energy Battery Check</h3>
                <p class="text-sm text-gray-500 mb-4">Gauge your internal battery level (0-100%) and receive customized boundary advice.</p>
                <span class="text-xs font-semibold text-brand-coral flex items-center">
                    Check Capacity 
                    <i class="fa-solid fa-arrow-right ml-1.5 transition-transform group-hover:translate-x-1"></i>
                </span>
            </a>

            <!-- Gratitude -->
            <a href="?action=gratitude" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-purple-100 group-hover:text-purple-200 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L9.12 9.12 1 12l8.12 2.88L12 23l2.88-8.12L23 12l-8.12-2.88L12 1zm0 0"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-sparkles text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Daily Gratitude (3 Wins)</h3>
                <p class="text-sm text-gray-500 mb-4">Shift perspective and build resilience by capturing 3 small everyday victories.</p>
                <span class="text-xs font-semibold text-purple-600 flex items-center">
                    Record 3 Wins 
                    <i class="fa-solid fa-arrow-right ml-1.5 transition-transform group-hover:translate-x-1"></i>
                </span>
            </a>
        </div>

        <?php elseif ($action === 'grounding'): ?>
        
        <!-- Interactive 5-4-3-2-1 Grounding Wizard -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-6 sm:p-10 shadow-soft border border-[#EBE8E0]" id="grounding-card">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                <a href="mindbloom.php" class="text-xs font-bold text-gray-400 hover:text-brand-slate inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Back to MindBloom
                </a>
                <span class="text-xs font-bold text-brand-sage bg-brand-sageLight px-3 py-1 rounded-full">
                    Step <span id="grounding-step-num">1</span> of 5
                </span>
            </div>

            <div class="text-center mb-8">
                <div class="inline-flex h-16 w-16 rounded-full bg-brand-sageLight text-brand-sage items-center justify-center mb-3 text-2xl shadow-sm">
                    <i id="grounding-icon" class="fa-regular fa-eye"></i>
                </div>
                <h2 id="grounding-title" class="text-2xl sm:text-3xl font-bold text-brand-slate">5 Things You Can SEE</h2>
                <p id="grounding-sub" class="text-gray-500 text-xs sm:text-sm mt-1 max-w-md mx-auto">Look around your surroundings. Notice shapes, textures, or shadows. Click each box as you spot them.</p>
            </div>

            <!-- Grounding Items Checklist Container -->
            <div id="grounding-items-container" class="space-y-3 mb-8">
                <!-- Dynamically populated checklist -->
            </div>

            <!-- Wizard Bottom Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <button type="button" id="grounding-prev-btn" onclick="prevGroundingStep()" class="hidden px-5 py-2.5 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs font-bold transition">
                    &larr; Previous Sense
                </button>
                <div class="ml-auto">
                    <button type="button" id="grounding-next-btn" onclick="nextGroundingStep()" class="px-7 py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition flex items-center gap-2">
                        <span>Continue to Next Sense</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Grounding Completed Card (Hidden until finished) -->
            <div id="grounding-complete-card" class="hidden text-center py-6 animate-fade-in">
                <div class="inline-flex h-20 w-20 rounded-full bg-emerald-100 text-emerald-600 items-center justify-center mb-4 text-3xl shadow-sm">
                    ✓
                </div>
                <h3 class="text-2xl font-bold text-brand-slate mb-2">You Are Grounded & Present</h3>
                <p class="text-sm text-gray-600 max-w-md mx-auto mb-6">
                    Take a slow, deep breath. Notice how your feet feel against the floor and how your shoulders have relaxed.
                </p>
                <div class="p-4 rounded-2xl bg-brand-sageLight text-brand-sage text-xs font-bold mb-6 inline-flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> +1 Activity logged to your Wellbeing Passport
                </div>
                <div>
                    <a href="mindbloom.php" class="px-8 py-3 rounded-2xl bg-brand-slate hover:bg-gray-800 text-white font-bold text-xs shadow-md transition inline-block">
                        Return to MindBloom
                    </a>
                </div>
            </div>
        </div>

        <?php elseif ($action === 'journal'): ?>
        
        <!-- Gentle Reflection Journal -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-6 sm:p-10 shadow-soft border border-[#EBE8E0]">
            <div class="mb-6 pb-4 border-b border-gray-100">
                <a href="mindbloom.php" class="text-xs font-bold text-gray-400 hover:text-brand-slate mb-3 inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Back to MindBloom
                </a>
                <h2 class="text-2xl sm:text-3xl font-bold text-brand-slate mb-1">Gentle Reflection</h2>
                <p class="text-xs text-brand-sage font-semibold">Prompt: "What is one small thing that went well today, despite any challenges?"</p>
            </div>

            <div class="relative mb-6">
                <textarea id="journal-textarea" rows="7" class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg p-5 pr-14 text-brand-slate focus:border-brand-sky focus:outline-none focus:ring-2 focus:ring-brand-sky/20 transition-all placeholder-gray-400 text-sm leading-relaxed" placeholder="Type or use your voice to express your thoughts here safely..."></textarea>
                <button type="button" id="journal-mic-btn" onclick="toggleJournalDictation()" class="absolute right-3 top-3 h-10 w-10 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-sky-600 hover:bg-sky-50 flex items-center justify-center transition-all focus:outline-none shadow-sm" title="Start Voice Dictation">
                    <i class="fa-solid fa-microphone text-sm"></i>
                </button>
            </div>
            
            <div class="flex items-center justify-between">
                <p id="journal-status" class="text-xs text-gray-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-brand-sage"></i> Stored in your private Passport
                </p>
                <button type="button" onclick="saveReflectionNote()" class="px-7 py-3 rounded-2xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-regular fa-bookmark"></i>
                    <span>Save Reflection</span>
                </button>
            </div>

            <!-- Success Notification -->
            <div id="journal-save-msg" class="hidden mt-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between">
                <span>✓ Reflection saved! +1 Activity logged to your Wellbeing Passport.</span>
                <a href="mindbloom.php" class="underline text-brand-slate">Return Home</a>
            </div>
        </div>

        <?php elseif ($action === 'checkin'): ?>

        <!-- Energy Battery Check -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-6 sm:p-10 shadow-soft border border-[#EBE8E0]">
            <div class="mb-6 pb-4 border-b border-gray-100">
                <a href="mindbloom.php" class="text-xs font-bold text-gray-400 hover:text-brand-slate mb-3 inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Back to MindBloom
                </a>
                <h2 class="text-2xl sm:text-3xl font-bold text-brand-slate mb-1">Energy Battery Check</h2>
                <p class="text-xs text-gray-500">Tune in to your mental and emotional reserves. Setting boundaries starts with knowing where your battery stands.</p>
            </div>

            <!-- Battery Level Indicator Box -->
            <div class="text-center py-6 bg-brand-inputBg rounded-3xl border border-gray-100 mb-8">
                <div class="inline-flex items-center justify-center w-24 h-12 rounded-xl border-4 border-brand-slate relative mb-3 bg-white p-1">
                    <div id="battery-fill" class="h-full rounded-lg bg-brand-sage transition-all duration-300" style="width: 60%;"></div>
                    <div class="absolute -right-2 top-3 w-1.5 h-6 bg-brand-slate rounded-r"></div>
                </div>
                <div class="text-3xl font-extrabold text-brand-slate font-outfit" id="energy-val-text">60%</div>
                <div class="text-xs font-bold uppercase tracking-wider text-brand-sage mt-1" id="energy-status-label">Moderate Capacity</div>
            </div>

            <!-- Interactive Range Slider -->
            <div class="mb-8">
                <div class="flex justify-between text-xs font-bold text-gray-400 mb-2">
                    <span>0% (Depleted)</span>
                    <span>50% (Balanced)</span>
                    <span>100% (Fully Charged)</span>
                </div>
                <input type="range" id="energy-slider" min="0" max="100" value="60" oninput="updateBatteryUI(this.value)" class="w-full accent-brand-sage cursor-pointer h-2 bg-gray-200 rounded-lg">
            </div>

            <!-- Dynamic Boundary Guidance Card -->
            <div id="boundary-advice" class="p-5 rounded-2xl bg-brand-sageLight/60 border border-brand-sage/20 text-xs text-gray-700 leading-relaxed mb-6">
                <strong class="text-brand-slate block mb-1">Recommended Boundary Today:</strong>
                <span id="boundary-text">You have steady reserves. Pace yourself, tackle core priorities, and schedule 10 minutes of quiet downtime this evening.</span>
            </div>

            <!-- Optional Note -->
            <div class="mb-6">
                <label class="block text-xs font-bold text-brand-slate mb-2">What is currently drawing on or charging your battery? (Optional)</label>
                <input type="text" id="battery-notes" placeholder="e.g. Back-to-back calls, good sleep last night..." class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-3 text-xs text-brand-slate focus:outline-none focus:border-brand-sage">
            </div>

            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-400 flex items-center gap-1">
                    <i class="fa-solid fa-lock text-brand-sage"></i> Private & Confidential
                </p>
                <button type="button" onclick="saveEnergyCheck()" class="px-7 py-3 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-xs shadow-md transition-all">
                    Save Battery Level
                </button>
            </div>

            <!-- Success Notification -->
            <div id="battery-save-msg" class="hidden mt-4 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between">
                <span>✓ Energy check recorded! +1 Activity logged to your Wellbeing Passport.</span>
                <a href="mindbloom.php" class="underline text-brand-slate">Return Home</a>
            </div>
        </div>

        <?php elseif ($action === 'gratitude'): ?>

        <!-- Daily Gratitude 3 Wins Board -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-6 sm:p-10 shadow-soft border border-[#EBE8E0]">
            <div class="mb-6 pb-4 border-b border-gray-100">
                <a href="mindbloom.php" class="text-xs font-bold text-gray-400 hover:text-brand-slate mb-3 inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Back to MindBloom
                </a>
                <h2 class="text-2xl sm:text-3xl font-bold text-brand-slate mb-1">Daily Gratitude (3 Wins)</h2>
                <p class="text-xs text-gray-500">Train your brain to spot everyday moments of relief, joy, and comfort.</p>
            </div>

            <form onsubmit="saveGratitudeForm(event)" class="space-y-4 mb-6">
                <div class="p-4 rounded-2xl bg-brand-inputBg border border-gray-100">
                    <label class="block text-xs font-bold text-purple-700 mb-1.5 flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-purple-100 flex items-center justify-center text-[10px]">1</span>
                        A small comfort that made me smile today:
                    </label>
                    <input type="text" id="win1" required placeholder="e.g. A hot cup of tea in the morning, warm sunshine..." class="w-full bg-white rounded-xl border border-gray-200 px-4 py-2.5 text-xs text-brand-slate focus:border-purple-500 focus:outline-none">
                </div>

                <div class="p-4 rounded-2xl bg-brand-inputBg border border-gray-100">
                    <label class="block text-xs font-bold text-purple-700 mb-1.5 flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-purple-100 flex items-center justify-center text-[10px]">2</span>
                        A kind interaction or friendly word:
                    </label>
                    <input type="text" id="win2" required placeholder="e.g. A colleague's thank you, a pet greeting me..." class="w-full bg-white rounded-xl border border-gray-200 px-4 py-2.5 text-xs text-brand-slate focus:border-purple-500 focus:outline-none">
                </div>

                <div class="p-4 rounded-2xl bg-brand-inputBg border border-gray-100">
                    <label class="block text-xs font-bold text-purple-700 mb-1.5 flex items-center gap-2">
                        <span class="h-5 w-5 rounded-md bg-purple-100 flex items-center justify-center text-[10px]">3</span>
                        Something I handled better than before:
                    </label>
                    <input type="text" id="win3" required placeholder="e.g. Took a deep breath instead of reacting quickly..." class="w-full bg-white rounded-xl border border-gray-200 px-4 py-2.5 text-xs text-brand-slate focus:border-purple-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-between pt-2">
                    <p class="text-xs text-gray-400 flex items-center gap-1">
                        <i class="fa-solid fa-sparkles text-purple-600"></i> Saves to your Passport
                    </p>
                    <button type="submit" class="px-7 py-3 rounded-2xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md transition-all">
                        Save 3 Wins
                    </button>
                </div>
            </form>

            <!-- Success Notification -->
            <div id="gratitude-save-msg" class="hidden p-4 rounded-2xl bg-purple-50 border border-purple-200 text-purple-900 text-xs font-bold flex items-center justify-between">
                <span>✓ 3 Wins recorded! +1 Activity logged to your Wellbeing Passport.</span>
                <a href="mindbloom.php" class="underline text-brand-slate">Return Home</a>
            </div>
        </div>

        <?php else: ?>
            <div class="text-center py-16">
                <h2 class="text-2xl font-bold text-brand-slate mb-4">Activity Not Found</h2>
                <a href="mindbloom.php" class="text-brand-sage underline font-bold text-sm">Return to MindBloom</a>
            </div>
        <?php endif; ?>

    
                    </div>
                
                <?php elseif ($tab === 'breathing'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="breathing_exercises.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>

                <?php elseif ($tab === 'truevent'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="truevent.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'assessments'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="assessments.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'vr'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="vr_resilience.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'ambient'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="ambient_mixer.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-20">
                        <h2 class="text-2xl font-bold text-brand-slate mb-4">Module Not Found</h2>
                        <a href="?tab=home" class="text-brand-sage underline">Return to MindBloom</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // -------------------------------------------------------------
        // 1. 5-4-3-2-1 GROUNDING WIZARD
        // -------------------------------------------------------------
        const groundingSteps = [
            {
                count: 5,
                sense: 'SEE',
                title: '5 Things You Can SEE',
                icon: 'fa-regular fa-eye',
                sub: 'Look around. Notice 5 objects, textures, shadows, or colors in your room.',
                placeholders: ['A pen or mug', 'A shadow or light patch on the wall', 'A leaf or plant', 'A pattern on clothing', 'A spot on the ceiling or floor']
            },
            {
                count: 4,
                sense: 'FEEL',
                title: '4 Things You Can FEEL',
                icon: 'fa-solid fa-hand',
                sub: 'Bring awareness to physical sensations. Touch or feel 4 textures right now.',
                placeholders: ['Feet firmly resting on the ground', 'Fabric of your clothes against skin', 'The cool temperature of a desk or surface', 'The gentle rise and fall of your chest']
            },
            {
                count: 3,
                sense: 'HEAR',
                title: '3 Things You Can HEAR',
                icon: 'fa-solid fa-ear-listen',
                sub: 'Tune in to your auditory surroundings. Listen past your immediate thoughts.',
                placeholders: ['The soft hum of a fan or fridge', 'A car or bird outside', 'Your own steady breathing']
            },
            {
                count: 2,
                sense: 'SMELL',
                title: '2 Things You Can SMELL',
                icon: 'fa-solid fa-wind',
                sub: 'Inhale gently through your nose. Notice scents in the room or on your wrist.',
                placeholders: ['Coffee, tea, or a fresh breeze', 'Soap, lotion, or clean laundry']
            },
            {
                count: 1,
                sense: 'TASTE',
                title: '1 Thing You Can TASTE',
                icon: 'fa-solid fa-mug-hot',
                sub: 'Notice the lingering taste of a drink, toothpaste, mint, or take a sip of water.',
                placeholders: ['Toothpaste, tea, water, or a mint']
            }
        ];

        let currentGroundingIdx = 0;

        function renderGroundingStep() {
            const step = groundingSteps[currentGroundingIdx];
            const titleEl = document.getElementById('grounding-title');
            if (!titleEl) return;

            document.getElementById('grounding-step-num').textContent = currentGroundingIdx + 1;
            titleEl.textContent = step.title;
            document.getElementById('grounding-sub').textContent = step.sub;
            document.getElementById('grounding-icon').className = step.icon;

            const prevBtn = document.getElementById('grounding-prev-btn');
            if (currentGroundingIdx > 0) {
                prevBtn.classList.remove('hidden');
            } else {
                prevBtn.classList.add('hidden');
            }

            const container = document.getElementById('grounding-items-container');
            let html = '';
            for (let i = 0; i < step.count; i++) {
                html += `
                    <label class="flex items-center p-3.5 rounded-2xl border border-gray-200 bg-white hover:bg-brand-sageLight/50 cursor-pointer transition shadow-sm">
                        <input type="checkbox" class="h-4 w-4 text-brand-sage focus:ring-brand-sage rounded mr-3" onchange="checkGroundingProgress()">
                        <span class="text-xs sm:text-sm text-brand-slate font-medium">${step.placeholders[i] || 'Noticed item ' + (i+1)}</span>
                    </label>
                `;
            }
            container.innerHTML = html;

            const nextBtn = document.getElementById('grounding-next-btn');
            if (currentGroundingIdx === groundingSteps.length - 1) {
                nextBtn.innerHTML = `<span>Complete Grounding</span> <i class="fa-solid fa-check"></i>`;
            } else {
                nextBtn.innerHTML = `<span>Continue to Next Sense</span> <i class="fa-solid fa-arrow-right text-xs"></i>`;
            }
        }

        function checkGroundingProgress() {
            // Optional checkbox auto-feedback
        }

        function nextGroundingStep() {
            if (currentGroundingIdx < groundingSteps.length - 1) {
                currentGroundingIdx++;
                renderGroundingStep();
            } else {
                // Complete Grounding
                const fd = new FormData();
                fd.append('action', 'complete_grounding');
                fetch('mindbloom.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        document.getElementById('grounding-items-container').classList.add('hidden');
                        document.getElementById('grounding-prev-btn').classList.add('hidden');
                        document.getElementById('grounding-next-btn').classList.add('hidden');
                        document.getElementById('grounding-title').classList.add('hidden');
                        document.getElementById('grounding-sub').classList.add('hidden');
                        document.getElementById('grounding-icon').parentElement.classList.add('hidden');
                        document.getElementById('grounding-complete-card').classList.remove('hidden');
                    }).catch(e => console.error(e));
            }
        }

        function prevGroundingStep() {
            if (currentGroundingIdx > 0) {
                currentGroundingIdx--;
                renderGroundingStep();
            }
        }

        if (document.getElementById('grounding-items-container')) {
            renderGroundingStep();
        }

        // -------------------------------------------------------------
        // 2. ENERGY BATTERY CHECK
        // -------------------------------------------------------------
        function updateBatteryUI(val) {
            val = parseInt(val);
            const fill = document.getElementById('battery-fill');
            const text = document.getElementById('energy-val-text');
            const label = document.getElementById('energy-status-label');
            const advice = document.getElementById('boundary-text');
            if (!fill) return;

            fill.style.width = val + '%';
            text.textContent = val + '%';

            if (val <= 25) {
                fill.className = 'h-full rounded-lg bg-rose-500 transition-all duration-300';
                label.textContent = 'Critically Low / Power-Save Mode';
                label.className = 'text-xs font-bold uppercase tracking-wider text-rose-500 mt-1';
                advice.textContent = 'Your battery is drained. Say no to non-essential obligations today, delay complex choices, and treat rest as non-negotiable medicine.';
            } else if (val <= 50) {
                fill.className = 'h-full rounded-lg bg-amber-500 transition-all duration-300';
                label.textContent = 'Low Reserves / Fragile Balance';
                label.className = 'text-xs font-bold uppercase tracking-wider text-amber-600 mt-1';
                advice.textContent = 'You have limited bandwidth. Tackle one priority at a time, take 5-minute pauses between meetings, and avoid volunteering for extra burdens.';
            } else if (val <= 75) {
                fill.className = 'h-full rounded-lg bg-brand-sage transition-all duration-300';
                label.textContent = 'Steady Capacity';
                label.className = 'text-xs font-bold uppercase tracking-wider text-brand-sage mt-1';
                advice.textContent = 'You have steady reserves. Pace yourself, tackle core priorities, and schedule 10 minutes of quiet downtime this evening.';
            } else {
                fill.className = 'h-full rounded-lg bg-emerald-600 transition-all duration-300';
                label.textContent = 'High Vitality & Flourishing';
                label.className = 'text-xs font-bold uppercase tracking-wider text-emerald-600 mt-1';
                advice.textContent = 'Your emotional battery is fully charged! Channel this vitality into meaningful creative work, connecting with loved ones, or supporting someone else.';
            }
        }

        function saveEnergyCheck() {
            const slider = document.getElementById('energy-slider');
            const notes = document.getElementById('battery-notes').value.trim();
            if (!slider) return;

            const fd = new FormData();
            fd.append('action', 'save_energy_check');
            fd.append('energy_level', slider.value);
            fd.append('notes', notes);

            fetch('mindbloom.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    const msg = document.getElementById('battery-save-msg');
                    if (msg) msg.classList.remove('hidden');
                }).catch(e => console.error(e));
        }

        // -------------------------------------------------------------
        // 3. DAILY GRATITUDE (3 WINS)
        // -------------------------------------------------------------
        function saveGratitudeForm(e) {
            e.preventDefault();
            const w1 = document.getElementById('win1').value.trim();
            const w2 = document.getElementById('win2').value.trim();
            const w3 = document.getElementById('win3').value.trim();

            const fd = new FormData();
            fd.append('action', 'save_gratitude');
            fd.append('win1', w1);
            fd.append('win2', w2);
            fd.append('win3', w3);

            fetch('mindbloom.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    const msg = document.getElementById('gratitude-save-msg');
                    if (msg) msg.classList.remove('hidden');
                }).catch(e => console.error(e));
        }

        // -------------------------------------------------------------
        // 4. GENTLE REFLECTION & VOICE DICTATION
        // -------------------------------------------------------------
        let journalRecognition = null;
        let isJournalRecording = false;

        function toggleJournalDictation() {
            const micBtn = document.getElementById('journal-mic-btn');
            const textarea = document.getElementById('journal-textarea');
            const statusEl = document.getElementById('journal-status');
            if (!micBtn || !textarea) return;

            if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
                alert('Speech recognition is not supported in this browser. You can type directly.');
                return;
            }

            if (!journalRecognition) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                journalRecognition = new SpeechRecognition();
                journalRecognition.continuous = true;
                journalRecognition.interimResults = true;
                journalRecognition.lang = 'en-US';

                journalRecognition.onstart = function() {
                    isJournalRecording = true;
                    micBtn.classList.add('bg-rose-100', 'text-rose-600', 'border-rose-300');
                    if (statusEl) statusEl.innerHTML = '<span class="text-rose-600 font-bold">● Listening... speak gently.</span>';
                };

                journalRecognition.onresult = function(event) {
                    let finalTranscript = '';
                    for (let i = event.resultIndex; i < event.results.length; ++i) {
                        if (event.results[i].isFinal) {
                            finalTranscript += event.results[i][0].transcript;
                        }
                    }
                    if (finalTranscript) {
                        textarea.value += (textarea.value ? ' ' : '') + finalTranscript;
                    }
                };

                journalRecognition.onend = function() {
                    isJournalRecording = false;
                    micBtn.classList.remove('bg-rose-100', 'text-rose-600', 'border-rose-300');
                    if (statusEl) statusEl.innerHTML = '<i class="fa-solid fa-shield-halved text-brand-sage"></i> Stored in your private Passport';
                };
            }

            if (isJournalRecording) {
                journalRecognition.stop();
            } else {
                journalRecognition.start();
            }
        }

        function saveReflectionNote() {
            const textarea = document.getElementById('journal-textarea');
            if (!textarea || !textarea.value.trim()) {
                alert('Please type or speak a reflection note before saving.');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'save_reflection');
            fd.append('text', textarea.value.trim());

            fetch('mindbloom.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    const msg = document.getElementById('journal-save-msg');
                    if (msg) msg.classList.remove('hidden');
                }).catch(e => console.error(e));
        }
    </script>
</body>
</html>
