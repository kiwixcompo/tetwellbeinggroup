<?php
session_start();
require_once 'db.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$action = $_GET['action'] ?? 'home';
$tab = $_GET['tab'] ?? 'home';

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
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-4">MindBloom Self-Care</h3>
                    
                    <a href="?tab=home" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-leaf w-5 text-center"></i> 
                            MindBloom Core
                        </span>
                        <?php echo getPlanBadge('free'); ?>
                    </a>
                    
                    <a href="?tab=truevent" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'truevent' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-book-open w-5 text-center"></i> 
                            Journaling (TrueVent)
                        </span>
                        <?php echo getPlanBadge('basic'); ?>
                    </a>
                    
                    <a href="?tab=assessments" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'assessments' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-clipboard-check w-5 text-center"></i> 
                            Clinical Check-ins
                        </span>
                        <?php echo getPlanBadge('free'); ?>
                    </a>
                    
                    <div class="pt-4 mt-2 border-t border-gray-100">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-2">Immersive Tools</h3>
                        
                        <a href="?tab=vr" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'vr' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                            <span class="flex items-center gap-3">
                                <i class="fa-solid fa-vr-cardboard w-5 text-center"></i> 
                                VR Resilience
                            </span>
                            <?php echo getPlanBadge('premium'); ?>
                        </a>
                        
                        <a href="?tab=ambient" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'ambient' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                            <span class="flex items-center gap-3">
                                <i class="fa-solid fa-music w-5 text-center"></i> 
                                Ambient Mixer
                            </span>
                            <?php echo getPlanBadge('guest'); ?>
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
        
        <div class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-brand-slate tracking-tight mb-3">MindBloom</h1>
            <p class="text-gray-500 max-w-2xl mx-auto">Micro-activities and gentle journaling to build daily resilience and center your thoughts.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grounding -->
            <a href="?action=grounding" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-sage/10 group-hover:text-brand-sage/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zM11 7h2v5.414l3.707 3.707-1.414 1.414L11 13.12V7z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Grounding Exercise</h3>
                <p class="text-sm text-gray-500 mb-4">A 3-minute sensory activity to quickly center your mind when overwhelmed.</p>
                <span class="text-xs font-semibold text-brand-sage flex items-center">Start Activity <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></span>
            </a>

            <!-- Journaling -->
            <a href="?action=journal" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-sky/10 group-hover:text-brand-sky/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2zm0 2v16h12V4H6zm2 4h8v2H8V8zm0 4h5v2H8v-2zm0 4h5v2H8v-2z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-skyLight text-brand-slate flex items-center justify-center mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Guided Journaling</h3>
                <p class="text-sm text-gray-500 mb-4">Process your emotions safely with gentle prompts that guide reflection.</p>
                <span class="text-xs font-semibold text-brand-sky flex items-center">Start Writing <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></span>
            </a>

            <!-- Emotional Check -->
            <a href="?action=checkin" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-brand-coral/10 group-hover:text-brand-coral/20 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Energy Check</h3>
                <p class="text-sm text-gray-500 mb-4">A quick assessment of your emotional battery to help you manage limits.</p>
                <span class="text-xs font-semibold text-brand-coral flex items-center">Check Energy <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></span>
            </a>

            <!-- Gratitude -->
            <a href="?action=gratitude" class="group bg-white rounded-3xl p-6 shadow-card hover:shadow-soft transition-all duration-300 border border-[#EBE8E0]/70 hover:-translate-y-1 block relative overflow-hidden">
                <div class="absolute -right-4 -top-4 text-purple-100 group-hover:text-purple-200 transition-colors">
                    <svg class="h-32 w-32" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L9.12 9.12 1 12l8.12 2.88L12 23l2.88-8.12L23 12l-8.12-2.88L12 1zm0 0"/></svg>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center mb-4">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" /></svg>
                </div>
                <h3 class="text-xl font-bold text-brand-slate mb-2">Daily Gratitude</h3>
                <p class="text-sm text-gray-500 mb-4">Shift your perspective by identifying three small wins today.</p>
                <span class="text-xs font-semibold text-purple-600 flex items-center">Start Gratitude <svg class="ml-1 h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></span>
            </a>
        </div>

        <?php elseif ($action === 'grounding'): ?>
        
        <!-- Grounding Exercise -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-8 md:p-12 shadow-soft border border-[#EBE8E0]">
            <div class="text-center mb-8">
                <div class="inline-flex h-16 w-16 rounded-full bg-brand-sageLight text-brand-sage items-center justify-center mb-4">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                </div>
                <h2 class="text-3xl font-bold text-brand-slate">5-4-3-2-1 Grounding</h2>
                <p class="text-gray-500 mt-2">Bring your focus back to the present moment.</p>
            </div>

            <div class="space-y-6">
                <div class="p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6]">
                    <h3 class="font-bold text-brand-slate text-lg mb-1 flex items-center gap-2">
                        <span class="bg-brand-sageLight text-brand-sage h-6 w-6 rounded-md flex items-center justify-center text-xs">5</span>
                        Things you can SEE
                    </h3>
                    <p class="text-sm text-gray-500">Look around you and notice five things you can see. (e.g. a pen, a spot on the ceiling, anything in your surroundings).</p>
                </div>
                <div class="p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6]">
                    <h3 class="font-bold text-brand-slate text-lg mb-1 flex items-center gap-2">
                        <span class="bg-brand-sageLight text-brand-sage h-6 w-6 rounded-md flex items-center justify-center text-xs">4</span>
                        Things you can FEEL
                    </h3>
                    <p class="text-sm text-gray-500">Notice four things you can feel. (e.g. your feet on the floor, the texture of your clothes).</p>
                </div>
                <div class="p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6]">
                    <h3 class="font-bold text-brand-slate text-lg mb-1 flex items-center gap-2">
                        <span class="bg-brand-sageLight text-brand-sage h-6 w-6 rounded-md flex items-center justify-center text-xs">3</span>
                        Things you can HEAR
                    </h3>
                    <p class="text-sm text-gray-500">Listen for three sounds. (e.g. the hum of the fridge, a bird outside).</p>
                </div>
                <div class="p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6]">
                    <h3 class="font-bold text-brand-slate text-lg mb-1 flex items-center gap-2">
                        <span class="bg-brand-sageLight text-brand-sage h-6 w-6 rounded-md flex items-center justify-center text-xs">2</span>
                        Things you can SMELL
                    </h3>
                    <p class="text-sm text-gray-500">Notice two things you can smell. (e.g. coffee, fresh air).</p>
                </div>
                <div class="p-4 rounded-2xl border border-gray-100 bg-[#FAF9F6]">
                    <h3 class="font-bold text-brand-slate text-lg mb-1 flex items-center gap-2">
                        <span class="bg-brand-sageLight text-brand-sage h-6 w-6 rounded-md flex items-center justify-center text-xs">1</span>
                        Thing you can TASTE
                    </h3>
                    <p class="text-sm text-gray-500">Notice one thing you can taste. (e.g. toothpaste, a mint).</p>
                </div>
            </div>

            <div class="mt-8 text-center">
                <a href="mindbloom.php" class="px-6 py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-colors inline-block">I feel more grounded</a>
            </div>
        </div>

        <?php elseif ($action === 'journal'): ?>
        
        <!-- Journaling -->
        <div class="max-w-2xl mx-auto bg-white rounded-3xl p-8 md:p-12 shadow-soft border border-[#EBE8E0]">
            <div class="mb-8">
                <a href="mindbloom.php" class="text-xs text-gray-400 hover:text-brand-slate mb-4 inline-block">&larr; Back to MindBloom</a>
                <h2 class="text-3xl font-bold text-brand-slate mb-2">Gentle Reflection</h2>
                <p class="text-brand-sage font-medium">Prompt: "What is one small thing that went well today, despite any challenges?"</p>
            </div>

            <div class="relative mb-6">
                <textarea id="journal-textarea" rows="6" class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg p-4 pr-14 text-brand-slate focus:border-brand-sky focus:outline-none focus:ring-2 focus:ring-brand-sky/20 transition-all placeholder-gray-400" placeholder="Type or speak your thoughts safely here..."></textarea>
                <button type="button" id="journal-mic-btn" onclick="toggleJournalDictation()" class="absolute right-3 top-3 h-10 w-10 rounded-xl bg-gray-100 text-gray-500 hover:text-brand-sky hover:bg-brand-sky/10 flex items-center justify-center transition-colors focus:outline-none" title="Start Dictation">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                </button>
            </div>
            
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-400 flex items-center gap-1"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> Private & Secure</p>
                <button onclick="alert('Journal saved to your Wellbeing Passport.'); window.location.href='mindbloom.php';" class="px-6 py-2.5 rounded-2xl bg-brand-sky hover:bg-blue-400 text-white font-bold text-sm shadow-md transition-colors">Save Entry</button>
            </div>
        </div>

        <?php else: ?>
            <div class="text-center">
                <h2 class="text-2xl font-bold text-brand-slate mb-4">Activity Not Found</h2>
                <a href="mindbloom.php" class="text-brand-sage underline">Return to MindBloom</a>
            </div>
        <?php endif; ?>

    
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
        // Adjust iframe heights dynamically if needed, though h-[85vh] usually suffices.
    </script>
</body>
</html>
