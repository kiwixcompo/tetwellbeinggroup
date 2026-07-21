<?php
session_start();
require_once 'db.php';

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
</head>
<body class="text-brand-slate antialiased min-h-screen flex flex-col">

    <!-- Navigation Menu -->
    <?php require_once 'nav_menu.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
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
    </main>

    <!-- Lesson Modal -->
    <div id="lessonModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-brand-cardBg rounded-2xl p-8 max-w-md w-full mx-4 shadow-xl border border-brand-sageLight">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-heading font-bold text-brand-slate">Lesson Player</h3>
                <button onclick="closeLesson()" class="text-brand-slate opacity-60 hover:opacity-100 transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="bg-brand-bg rounded-xl p-8 text-center border border-brand-sageLight mb-6">
                <i data-lucide="play-circle" class="w-16 h-16 text-brand-sage mx-auto mb-4 opacity-50"></i>
                <p id="lessonTitle" class="text-lg font-medium text-brand-slate mb-2"></p>
                <p class="text-sm text-brand-coral font-medium">Coming Soon</p>
            </div>
            <button onclick="closeLesson()" class="w-full bg-brand-slate text-white py-2 rounded-lg font-medium hover:bg-opacity-90 transition">
                Close
            </button>
        </div>
    </div>

    <!-- Emergency Modal Placeholder -->
    <div id="emergencyModal" class="fixed inset-0 bg-black bg-opacity-50 z-[100] hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl border-t-4 border-brand-coral">
            <div class="flex items-center justify-center w-16 h-16 bg-brand-coralLight rounded-full mx-auto mb-4">
                <i data-lucide="phone" class="w-8 h-8 text-brand-coral"></i>
            </div>
            <h2 class="text-2xl font-heading font-bold text-center text-brand-slate mb-2">Emergency Contacts</h2>
            <p class="text-center text-brand-slate opacity-80 mb-6">If you or someone else is in immediate danger, please reach out to these services immediately.</p>
            
            <div class="space-y-4 mb-8">
                <a href="tel:999" class="flex items-center p-4 bg-brand-bg rounded-xl hover:bg-brand-coralLight transition-colors group">
                    <div class="flex-grow">
                        <h3 class="font-bold text-brand-slate group-hover:text-brand-coral transition-colors">Emergency Services</h3>
                        <p class="text-sm text-brand-slate opacity-70">Police, Fire, Ambulance (UK)</p>
                    </div>
                    <span class="font-bold text-lg text-brand-coral">999</span>
                </a>
                <a href="tel:111" class="flex items-center p-4 bg-brand-bg rounded-xl hover:bg-brand-sageLight transition-colors group">
                    <div class="flex-grow">
                        <h3 class="font-bold text-brand-slate group-hover:text-brand-sage transition-colors">NHS Non-Emergency</h3>
                        <p class="text-sm text-brand-slate opacity-70">Medical advice 24/7</p>
                    </div>
                    <span class="font-bold text-lg text-brand-sage">111</span>
                </a>
                <a href="tel:116123" class="flex items-center p-4 bg-brand-bg rounded-xl hover:bg-brand-sky hover:bg-opacity-20 transition-colors group">
                    <div class="flex-grow">
                        <h3 class="font-bold text-brand-slate group-hover:text-brand-sky transition-colors">Samaritans</h3>
                        <p class="text-sm text-brand-slate opacity-70">24/7 Mental health support</p>
                    </div>
                    <span class="font-bold text-lg text-brand-sky">116 123</span>
                </a>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full bg-brand-slate text-white py-3 rounded-xl font-medium hover:bg-opacity-90 transition-colors">
                Close
            </button>
        </div>
    </div>

    <!-- AI Companion Widget -->
    <?php require_once 'includes/global_ai_widget.php'; ?>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Lesson Modal Logic
        function openLesson(title) {
            document.getElementById('lessonTitle').textContent = title;
            document.getElementById('lessonModal').classList.remove('hidden');
        }

        function closeLesson() {
            document.getElementById('lessonModal').classList.add('hidden');
        }

        // Close modal on clicking outside
        document.getElementById('lessonModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLesson();
            }
        });

        // Emergency Modal Functions
        function openEmergencyModal() {
            document.getElementById('emergencyModal').classList.remove('hidden');
        }
        
        function closeEmergencyModal() {
            document.getElementById('emergencyModal').classList.add('hidden');
        }
        
        // Close emergency modal on background click
        document.getElementById('emergencyModal').addEventListener('click', function(e) {
            if(e.target === this) closeEmergencyModal();
        });
    </script>
</body>
</html>
