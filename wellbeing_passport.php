<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Dummy mock data for progress/challenges
$completed_activities = 8;
$stress_pattern = "You often report higher stress on Sundays.";
$helpful_pattern = "Breathing exercises appear to help you feel calmer.";
$wellbeing_goals = ["Improve sleep", "Build confidence", "Connect with people"];

$challenges = [
    ["title" => "7-Day Calm Mind Challenge", "desc" => "Learn to quiet your mind and find peace in the present moment.", "duration" => "7 Days"],
    ["title" => "7 Days of Better Sleep", "desc" => "Build habits that promote restful and rejuvenating sleep.", "duration" => "7 Days"],
    ["title" => "14 Days of Self-Confidence", "desc" => "Challenge your inner critic and embrace your self-worth.", "duration" => "14 Days"],
    ["title" => "21 Days of Reducing Overthinking", "desc" => "Break the cycle of rumination and stay focused on what matters.", "duration" => "21 Days"],
    ["title" => "30 Days of Connection", "desc" => "Deepen your relationships and build meaningful bonds with others.", "duration" => "30 Days"],
    ["title" => "Caregiver Resilience Challenge", "desc" => "Find strength and balance while caring for others.", "duration" => "Ongoing"],
    ["title" => "Student Wellbeing Challenge", "desc" => "Manage academic stress and maintain a healthy study-life balance.", "duration" => "Ongoing"],
    ["title" => "Workplace Burnout Prevention Challenge", "desc" => "Set boundaries and protect your energy at work.", "duration" => "Ongoing"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wellbeing Passport | Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .tab-btn.active {
            border-bottom: 3px solid #5E8C71;
            color: #5E8C71;
            font-weight: 600;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="text-brand-slate antialiased flex flex-col min-h-screen">

    <!-- Top Nav -->
    <?php include 'nav_menu.php'; ?>

    <!-- Page Hero -->
    <header class="pt-28 pb-16 px-6 sm:px-8 lg:px-12 bg-white relative overflow-hidden border-b border-gray-100">
        <div class="absolute inset-0 z-0">
            <img src="images/passport_hero.png" alt="Wellbeing Journey" class="w-full h-full object-cover opacity-20">
            <div class="absolute inset-0 bg-gradient-to-b from-white/60 to-white/95"></div>
        </div>
        <div class="max-w-4xl mx-auto relative z-10 text-center">
            <h1 class="text-4xl sm:text-5xl font-heading font-bold text-brand-slate mb-4 tracking-tight">My Wellbeing Passport</h1>
            <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                A private space to understand yourself better through your progress and reflections. <br>
                <span class="italic text-brand-sage font-medium mt-2 inline-block">"I understand myself better because of TET."</span>
            </p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 w-full pb-20">
        
        <!-- Tabs Navigation -->
        <div class="flex border-b border-gray-200 mb-8 overflow-x-auto hide-scrollbar">
            <button onclick="switchTab('insights')" id="btn-insights" class="tab-btn active px-6 py-4 text-gray-500 hover:text-brand-sage focus:outline-none whitespace-nowrap transition-colors duration-200 font-medium text-lg">
                My Insights Profile
            </button>
            <button onclick="switchTab('challenges')" id="btn-challenges" class="tab-btn px-6 py-4 text-gray-500 hover:text-brand-sage focus:outline-none whitespace-nowrap transition-colors duration-200 font-medium text-lg">
                Wellbeing Challenges
            </button>
        </div>

        <!-- TAB 1: Insights Profile -->
        <div id="tab-insights" class="tab-content active fade-in">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                
                <!-- Emotional Patterns -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-brand-sky/20 flex items-center justify-center text-brand-sky text-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-heading font-bold">Emotional Patterns</h2>
                    </div>
                    <p class="text-gray-600 mb-4">Based on your recent reflections, we've noticed some trends in how you've been feeling.</p>
                    <div class="bg-brand-bg rounded-xl p-4 border border-gray-100">
                        <p class="text-lg text-brand-slate font-medium"><span class="text-brand-sage mr-2">✨</span><?php echo htmlspecialchars($stress_pattern); ?></p>
                    </div>
                </div>

                <!-- What Helps Me -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage text-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-heading font-bold">What Helps Me</h2>
                    </div>
                    <p class="text-gray-600 mb-4">Activities and practices that have a positive impact on your wellbeing.</p>
                    <div class="bg-brand-bg rounded-xl p-4 border border-gray-100">
                        <p class="text-lg text-brand-slate font-medium"><span class="text-brand-sage mr-2">🌱</span><?php echo htmlspecialchars($helpful_pattern); ?></p>
                    </div>
                </div>

                <!-- My Progress -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300 flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 rounded-full bg-brand-sage text-white flex items-center justify-center text-5xl font-heading font-bold mb-4 shadow-md">
                        <?php echo $completed_activities; ?>
                    </div>
                    <h2 class="text-2xl font-heading font-bold mb-2">Activities Completed</h2>
                    <p class="text-gray-600">You've completed <?php echo $completed_activities; ?> wellbeing activities this month. Great consistency!</p>
                </div>

                <!-- My Wellbeing Goals -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-brand-coralLight flex items-center justify-center text-brand-coral text-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h2 class="text-2xl font-heading font-bold">My Wellbeing Goals</h2>
                    </div>
                    <ul class="space-y-3 mb-6">
                        <?php foreach($wellbeing_goals as $goal): ?>
                        <li class="flex items-center gap-3 p-3 bg-brand-bg rounded-lg border border-gray-100">
                            <span onclick="toggleGoal(this)" class="w-5 h-5 rounded border-2 border-brand-sage flex items-center justify-center text-transparent hover:bg-brand-sage hover:text-white cursor-pointer transition-colors duration-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></span>
                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($goal); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <form onsubmit="addGoal(event, this)" class="flex gap-2">
                        <input type="text" name="new_goal" placeholder="Set a new goal..." class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-sage focus:border-transparent" required>
                        <button type="submit" class="bg-brand-slate text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition-colors font-medium">Add</button>
                    </form>
                </div>

            </div>
        </div>

        <!-- TAB 2: Challenges -->
        <div id="tab-challenges" class="tab-content fade-in">
            <div class="mb-8 max-w-3xl">
                <h2 class="text-3xl font-heading font-bold mb-4">Structured Growth</h2>
                <p class="text-gray-600 text-lg">People enjoy structured challenges. Choose a path below to start building positive habits step by step.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach($challenges as $index => $challenge): ?>
                <div class="glass-card rounded-2xl overflow-hidden hover:-translate-y-1 transition-all duration-300 cursor-pointer group flex flex-col h-full hover:shadow-xl border border-gray-100" onclick="openChallengeModal('<?php echo htmlspecialchars($challenge['title']); ?>')">
                    <div class="h-36 relative overflow-hidden">
                        <img src="images/challenge_calm.png" alt="Challenge Cover" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <div class="absolute bottom-3 left-4">
                            <span class="inline-block px-3 py-1 bg-white/90 backdrop-blur-sm text-brand-slate text-xs font-bold rounded-full shadow-sm">
                                <?php echo htmlspecialchars($challenge['duration']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col flex-grow bg-white">
                        <h3 class="text-xl font-heading font-bold mb-2 group-hover:text-brand-sage transition-colors text-brand-slate"><?php echo htmlspecialchars($challenge['title']); ?></h3>
                        <p class="text-gray-500 text-sm flex-grow mb-4 leading-relaxed"><?php echo htmlspecialchars($challenge['desc']); ?></p>
                        <div class="flex items-center justify-between text-brand-sage font-bold text-sm mt-auto pt-4 border-t border-gray-50">
                            <span>View details</span>
                            <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center group-hover:bg-brand-sage group-hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- Emergency Modal Placeholder -->
    <div id="emergency-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <!-- Emergency modal content would be handled by nav_menu.php or a separate include usually -->
        <div class="bg-white rounded-2xl max-w-md w-full p-8 text-center shadow-2xl">
            <h3 class="text-2xl font-bold text-red-600 mb-4">Need Immediate Help?</h3>
            <p class="mb-6 text-gray-700">If you are in immediate danger or experiencing a medical emergency, please call 999 or go to your nearest A&E.</p>
            <div class="space-y-4">
                <a href="tel:111" class="block w-full py-3 bg-red-100 text-red-700 font-bold rounded-xl hover:bg-red-200 transition">NHS 111 (Non-Emergency)</a>
                <a href="tel:116123" class="block w-full py-3 bg-red-100 text-red-700 font-bold rounded-xl hover:bg-red-200 transition">Samaritans: 116 123</a>
            </div>
            <button onclick="document.getElementById('emergency-modal').classList.add('hidden')" class="mt-6 text-gray-500 hover:text-gray-800 font-medium">Close</button>
        </div>
    </div>

    <!-- Challenge Modal -->
    <div id="challenge-modal" class="fixed inset-0 bg-brand-slate/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl transform scale-95 transition-transform duration-300 p-1 relative" id="challenge-modal-content">
            <button onclick="closeChallengeModal()" class="absolute top-6 right-6 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition-colors z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            
            <div class="bg-brand-sageLight/50 p-8 pt-10 rounded-t-2xl">
                <span class="inline-block px-3 py-1 bg-brand-sage text-white text-xs font-bold rounded-full mb-3 uppercase tracking-wide">Structured Challenge</span>
                <h3 id="modal-challenge-title" class="text-3xl font-heading font-bold text-brand-slate mb-2">7-Day Calm Mind Challenge</h3>
                <p class="text-gray-600">A step-by-step journey to building a calmer, more present mind.</p>
            </div>
            
            <div class="p-8">
                <h4 class="font-heading font-bold text-xl mb-6">Your Daily Steps</h4>
                
                <div class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-brand-sage before:to-transparent">
                    
                    <!-- Day 1 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-brand-sage text-white shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">1</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-brand-sage/30 bg-brand-sageLight/20 shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Notice your thoughts</h5>
                            <p class="text-sm text-gray-600">Spend 5 minutes simply observing your thoughts without judgment.</p>
                        </div>
                    </div>
                    <!-- Day 2 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">2</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Practise grounding</h5>
                            <p class="text-sm text-gray-600">Use the 5-4-3-2-1 technique to anchor yourself in the present.</p>
                        </div>
                    </div>
                    <!-- Day 3 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">3</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Identify one source of stress</h5>
                            <p class="text-sm text-gray-600">Write down one thing stressing you out and one small action you can take.</p>
                        </div>
                    </div>
                    <!-- Day 4 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">4</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Take a meaningful break</h5>
                            <p class="text-sm text-gray-600">Disconnect from screens for 30 minutes and do something analog.</p>
                        </div>
                    </div>
                    <!-- Day 5 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">5</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Connect with someone</h5>
                            <p class="text-sm text-gray-600">Reach out to a friend or family member for a brief chat.</p>
                        </div>
                    </div>
                    <!-- Day 6 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">6</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Practise self-compassion</h5>
                            <p class="text-sm text-gray-600">Write down three things you appreciate about yourself.</p>
                        </div>
                    </div>
                    <!-- Day 7 -->
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-gray-200 text-gray-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 font-bold text-sm z-10">7</div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                            <h5 class="font-bold text-brand-slate mb-1">Reflect on your progress</h5>
                            <p class="text-sm text-gray-600">Look back at the week. What felt good? What did you learn?</p>
                        </div>
                    </div>

                </div>

                <div class="mt-10 pt-6 border-t border-gray-100 flex justify-between items-center bg-white rounded-b-3xl">
                    <div class="text-sm text-gray-500">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        You can only start one challenge at a time.
                    </div>
                    <button onclick="startChallenge(this)" class="bg-brand-sage hover:bg-brand-sageHover text-white px-8 py-3 rounded-xl font-bold text-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 flex items-center gap-2">
                        Start Challenge
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer placeholder -->
    <footer class="mt-auto py-8 text-center text-gray-500 text-sm">
        <p>&copy; <?php echo date('Y'); ?> Tet Wellbeing Group. All rights reserved.</p>
    </footer>

    <script>
        // Tab switching logic
        function switchTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            // Deactivate all buttons
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active');
            });
            
            // Show target tab
            document.getElementById('tab-' + tabId).classList.add('active');
            // Activate target button
            document.getElementById('btn-' + tabId).classList.add('active');
        }

        // Modal logic
        const modal = document.getElementById('challenge-modal');
        const modalContent = document.getElementById('challenge-modal-content');
        
        function openChallengeModal(title) {
            document.getElementById('modal-challenge-title').innerText = title;
            modal.classList.remove('hidden');
            // small delay for transition
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
            }, 10);
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }

        function closeChallengeModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300); // match duration-300
            document.body.style.overflow = '';
        }

        // Close modal on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeChallengeModal();
            }
        });

        function toggleGoal(el) {
            el.classList.toggle('text-transparent');
            el.classList.toggle('bg-brand-sage');
            el.classList.toggle('border-brand-sage');
            el.classList.toggle('text-white');
            
            // Visual checkmark bounce effect
            const svg = el.querySelector('svg');
            svg.classList.remove('scale-0');
            svg.classList.add('scale-100', 'transition-transform');

            const textSpan = el.nextElementSibling;
            if (el.classList.contains('bg-brand-sage')) {
                textSpan.classList.add('line-through', 'text-gray-400');
                textSpan.classList.remove('text-gray-700', 'font-medium');
            } else {
                textSpan.classList.remove('line-through', 'text-gray-400');
                textSpan.classList.add('text-gray-700', 'font-medium');
            }
        }

        function addGoal(e, form) {
            e.preventDefault();
            const input = form.querySelector('input[name="new_goal"]');
            const goalText = input.value.trim();
            if (goalText) {
                const ul = form.previousElementSibling;
                const li = document.createElement('li');
                li.className = 'flex items-center gap-3 p-3 bg-brand-bg rounded-lg border border-gray-100 fade-in';
                li.innerHTML = `
                    <span onclick="toggleGoal(this)" class="w-5 h-5 rounded border-2 border-brand-sage flex items-center justify-center text-transparent hover:bg-brand-sage hover:text-white cursor-pointer transition-colors duration-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></span>
                    <span class="font-medium text-gray-700">${goalText.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>
                `;
                ul.appendChild(li);
                input.value = '';
            }
        }

        function startChallenge(btn) {
            const originalContent = btn.innerHTML;
            btn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Starting...
            `;
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            
            setTimeout(() => {
                btn.innerHTML = `
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    Challenge Started!
                `;
                btn.classList.remove('bg-brand-sage', 'hover:bg-brand-sageHover', 'opacity-80', 'cursor-not-allowed');
                btn.classList.add('bg-green-600');
                
                setTimeout(() => {
                    closeChallengeModal();
                    // Reset button for next time modal is opened
                    setTimeout(() => {
                        btn.innerHTML = originalContent;
                        btn.classList.add('bg-brand-sage', 'hover:bg-brand-sageHover');
                        btn.classList.remove('bg-green-600');
                    }, 500);
                }, 1500);
            }, 800);
        }
    </script>
</body>
</html>
