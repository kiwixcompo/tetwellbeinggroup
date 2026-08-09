<?php
session_start();
require_once 'db.php';
$tab = $_GET['tab'] ?? 'home';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TET Academy - Tet Wellbeing Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',
                            sage: '#5E8C71',
                            slate: '#264653',
                            sky: '#8ECAE6',
                            coral: '#E76F51',
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        heading: ['"Outfit"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="text-brand-slate antialiased min-h-screen flex flex-col">

    <?php include 'nav_menu.php'; ?>

    <!-- MAIN BODY CONTENT (Phase 4 Learning Center Layout) -->
    <main class="flex-grow mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-12 fade-in">
        
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT SIDEBAR (20%) -->
            <div class="w-full lg:w-1/4 xl:w-1/5 shrink-0">
                <div class="sticky top-28 space-y-1">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-4">Learning Center</h3>
                    
                    <a href="?tab=home" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-graduation-cap w-5 text-center"></i> 
                        TET Academy
                    </a>
                    
                    <a href="?tab=streaming" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'streaming' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-play-circle w-5 text-center"></i> 
                        Streaming Hub
                    </a>
                    
                    <a href="?tab=research" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'research' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-microscope w-5 text-center"></i> 
                        Research Centre
                    </a>
                </div>
            </div>

            <!-- RIGHT CONTENT AREA (80%) -->
            <div class="w-full lg:w-3/4 xl:w-4/5">
                <?php if ($tab === 'home'): ?>
                    <div class="max-w-4xl mx-auto">
                        
        <!-- Hero Section -->
        <div class="bg-brand-sageLight rounded-3xl p-8 sm:p-12 mb-10 text-center relative overflow-hidden border border-brand-sage/20 shadow-sm">
            <div class="absolute inset-0 z-0">
                <img src="images/academy_hero.png" alt="Academy Hero" class="w-full h-full object-cover opacity-30 mix-blend-multiply">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-sageLight via-transparent to-transparent"></div>
            </div>
            <div class="relative z-10 max-w-3xl mx-auto">
                <h1 class="text-4xl sm:text-5xl font-heading font-bold text-brand-slate mb-4">TET Academy</h1>
                <p class="text-lg text-brand-slate opacity-80 max-w-2xl mx-auto">
                    Bite-sized emotional intelligence and wellbeing lessons for your daily life.
                </p>
            </div>
        </div>

        <!-- Progress Bar Section -->
        <div class="bg-brand-cardBg rounded-2xl p-6 mb-8 shadow-sm border border-brand-sageLight">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-heading font-semibold text-brand-slate">Your Learning Journey</h2>
                <span class="text-brand-sage font-bold text-lg">35%</span>
            </div>
            <div class="w-full bg-brand-sageLight rounded-full h-4 overflow-hidden">
                <div class="bg-brand-sage h-4 rounded-full transition-all duration-1000" style="width: 35%"></div>
            </div>
            <p class="text-sm text-brand-slate opacity-70 mt-3 text-right">Keep going! You've completed 7 lessons.</p>
        </div>

        <!-- Lesson Modules Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Card 1 -->
            <div class="bg-brand-cardBg rounded-2xl overflow-hidden shadow-sm border border-brand-sageLight hover:shadow-md transition flex flex-col">
                <div class="h-40 bg-brand-sky flex items-center justify-center text-white">
                    <i data-lucide="cloud-rain" class="w-16 h-16 opacity-80"></i>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-heading font-semibold text-brand-slate">Understanding Anxiety</h3>
                    </div>
                    <p class="text-xs text-brand-slate opacity-60 mb-3 flex items-center">
                        <i data-lucide="clock" class="w-3 h-3 mr-1"></i> 5 mins
                    </p>
                    <p class="text-sm text-brand-slate opacity-80 mb-5 flex-grow">
                        Learn to identify the root causes of anxiety and practical tools to ground yourself in the moment.
                    </p>
                    <button onclick="openLesson('Understanding Anxiety')" class="w-full bg-brand-sage text-white py-2 rounded-lg font-medium hover:bg-brand-sageHover transition">
                        Start Lesson
                    </button>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-brand-cardBg rounded-2xl overflow-hidden shadow-sm border border-brand-sageLight hover:shadow-md transition flex flex-col">
                <div class="h-40 bg-brand-slate flex items-center justify-center text-white">
                    <i data-lucide="moon" class="w-16 h-16 opacity-80"></i>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-heading font-semibold text-brand-slate">The Science of Sleep</h3>
                    </div>
                    <p class="text-xs text-brand-slate opacity-60 mb-3 flex items-center">
                        <i data-lucide="clock" class="w-3 h-3 mr-1"></i> 7 mins
                    </p>
                    <p class="text-sm text-brand-slate opacity-80 mb-5 flex-grow">
                        Discover how sleep impacts emotional regulation and build a healthy nighttime routine.
                    </p>
                    <button onclick="openLesson('The Science of Sleep')" class="w-full bg-brand-sage text-white py-2 rounded-lg font-medium hover:bg-brand-sageHover transition">
                        Start Lesson
                    </button>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-brand-cardBg rounded-2xl overflow-hidden shadow-sm border border-brand-sageLight hover:shadow-md transition flex flex-col">
                <div class="h-40 bg-brand-coral flex items-center justify-center text-white">
                    <i data-lucide="shield" class="w-16 h-16 opacity-80"></i>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-heading font-semibold text-brand-slate">Boundaries 101</h3>
                    </div>
                    <p class="text-xs text-brand-slate opacity-60 mb-3 flex items-center">
                        <i data-lucide="clock" class="w-3 h-3 mr-1"></i> 6 mins
                    </p>
                    <p class="text-sm text-brand-slate opacity-80 mb-5 flex-grow">
                        Master the art of saying no with kindness and protecting your energy without guilt.
                    </p>
                    <button onclick="openLesson('Boundaries 101')" class="w-full bg-brand-sage text-white py-2 rounded-lg font-medium hover:bg-brand-sageHover transition">
                        Start Lesson
                    </button>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="bg-brand-cardBg rounded-2xl overflow-hidden shadow-sm border border-brand-sageLight hover:shadow-md transition flex flex-col">
                <div class="h-40 bg-brand-sage flex items-center justify-center text-white">
                    <i data-lucide="heart-pulse" class="w-16 h-16 opacity-80"></i>
                </div>
                <div class="p-5 flex flex-col flex-grow">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-lg font-heading font-semibold text-brand-slate">Emotional Regulation</h3>
                    </div>
                    <p class="text-xs text-brand-slate opacity-60 mb-3 flex items-center">
                        <i data-lucide="clock" class="w-3 h-3 mr-1"></i> 8 mins
                    </p>
                    <p class="text-sm text-brand-slate opacity-80 mb-5 flex-grow">
                        Techniques to pause, process, and respond to intense emotions instead of reacting.
                    </p>
                    <button onclick="openLesson('Emotional Regulation')" class="w-full bg-brand-sage text-white py-2 rounded-lg font-medium hover:bg-brand-sageHover transition">
                        Start Lesson
                    </button>
                </div>
            </div>

        </div>
    
                    </div>
                
                <?php elseif ($tab === 'streaming'): ?>
                    <div class="bg-[#121212] rounded-[2rem] border border-gray-800 shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="streaming_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'research'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="research_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-20">
                        <h2 class="text-2xl font-bold text-brand-slate mb-4">Module Not Found</h2>
                        <a href="?tab=home" class="text-brand-sage underline">Return to Learning Center</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
