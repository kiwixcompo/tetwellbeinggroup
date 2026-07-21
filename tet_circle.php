<?php
session_start();
require_once 'db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TET Circle - Tet Wellbeing Group</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        heading: ['Outfit', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="text-brand-slate flex flex-col min-h-screen">
    <?php require_once 'nav_menu.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-8 max-w-7xl">
        <!-- Hero Section -->
        <section class="bg-brand-coralLight rounded-3xl p-8 md:p-12 mb-12 text-center relative overflow-hidden border border-brand-coral/20 shadow-sm">
            <div class="absolute inset-0 z-0">
                <img src="images/circle_hero.png" alt="Circle Hero" class="w-full h-full object-cover opacity-25 mix-blend-multiply">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-coralLight via-transparent to-transparent"></div>
            </div>
            <div class="relative z-10 max-w-3xl mx-auto">
                <h1 class="text-4xl md:text-5xl font-bold text-brand-slate mb-4 font-heading">TET Circle</h1>
                <p class="text-lg text-gray-700 max-w-2xl mx-auto">Connect, share, and heal together. You are not alone.</p>
            </div>
        </section>

        <!-- Two Main Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <!-- Peer Support Groups -->
            <div class="lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold text-brand-slate">Peer Support Groups</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Group Card 1 -->
                    <div class="bg-brand-cardBg p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-brand-sky/20 rounded-full flex items-center justify-center text-brand-sky mb-4 text-xl">
                            <i class="fa-solid fa-hands-holding-child"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Caregivers Support</h3>
                        <p class="text-gray-600 mb-4 text-sm">A safe space for those caring for loved ones to share challenges and find comfort.</p>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fa-solid fa-users mr-2"></i> 124 Members
                        </div>
                        <button onclick="showToast('Join request sent! We will notify you once approved.')" class="w-full py-2 bg-brand-sage text-white rounded-lg font-medium hover:bg-brand-sageHover transition-colors">Join Group</button>
                    </div>

                    <!-- Group Card 2 -->
                    <div class="bg-brand-cardBg p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-brand-coralLight rounded-full flex items-center justify-center text-brand-coral mb-4 text-xl">
                            <i class="fa-solid fa-fire-extinguisher"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Managing Burnout</h3>
                        <p class="text-gray-600 mb-4 text-sm">Discuss strategies to recover from professional and emotional exhaustion.</p>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fa-solid fa-users mr-2"></i> 89 Members
                        </div>
                        <button onclick="showToast('Join request sent! We will notify you once approved.')" class="w-full py-2 bg-brand-sage text-white rounded-lg font-medium hover:bg-brand-sageHover transition-colors">Join Group</button>
                    </div>

                    <!-- Group Card 3 -->
                    <div class="bg-brand-cardBg p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-brand-sageLight rounded-full flex items-center justify-center text-brand-sage mb-4 text-xl">
                            <i class="fa-solid fa-baby"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">New Parents</h3>
                        <p class="text-gray-600 mb-4 text-sm">Navigate the overwhelming but rewarding journey of parenthood together.</p>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fa-solid fa-users mr-2"></i> 215 Members
                        </div>
                        <button onclick="showToast('Join request sent! We will notify you once approved.')" class="w-full py-2 bg-brand-sage text-white rounded-lg font-medium hover:bg-brand-sageHover transition-colors">Join Group</button>
                    </div>

                    <!-- Group Card 4 -->
                    <div class="bg-brand-cardBg p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-500 mb-4 text-xl">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Student Stress</h3>
                        <p class="text-gray-600 mb-4 text-sm">For students facing academic pressure, exams anxiety, and finding balance.</p>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fa-solid fa-users mr-2"></i> 156 Members
                        </div>
                        <button onclick="showToast('Join request sent! We will notify you once approved.')" class="w-full py-2 bg-brand-sage text-white rounded-lg font-medium hover:bg-brand-sageHover transition-colors">Join Group</button>
                    </div>
                </div>
            </div>

            <!-- Community Board -->
            <div>
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold text-brand-slate">Community Board</h2>
                </div>
                <div class="bg-brand-cardBg rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col h-[calc(100%-4rem)]">
                    <p class="text-gray-500 text-sm mb-6">Anonymous forum to share your thoughts and find community support.</p>
                    
                    <div class="flex-grow space-y-4 overflow-y-auto mb-6 pr-2">
                        <!-- Post 1 -->
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs mr-3">Anon</div>
                                <span class="text-xs text-gray-400">2 hours ago</span>
                            </div>
                            <h4 class="font-semibold text-brand-slate text-sm mb-1">Struggling to find motivation</h4>
                            <p class="text-xs text-gray-600 mb-2">Lately I just can't seem to get out of bed in the morning. Has anyone else felt this way?</p>
                            <div class="flex space-x-4 text-xs text-gray-400">
                                <span><i class="fa-regular fa-comment mr-1"></i> 14 Replies</span>
                                <span><i class="fa-regular fa-heart mr-1"></i> 32</span>
                            </div>
                        </div>

                        <!-- Post 2 -->
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 rounded-full bg-brand-sky/20 flex items-center justify-center text-brand-sky text-xs mr-3">Anon</div>
                                <span class="text-xs text-gray-400">5 hours ago</span>
                            </div>
                            <h4 class="font-semibold text-brand-slate text-sm mb-1">Small win today!</h4>
                            <p class="text-xs text-gray-600 mb-2">Finally went for a 10 minute walk after being inside all week. It feels good.</p>
                            <div class="flex space-x-4 text-xs text-gray-400">
                                <span><i class="fa-regular fa-comment mr-1"></i> 8 Replies</span>
                                <span><i class="fa-regular fa-heart mr-1"></i> 56</span>
                            </div>
                        </div>

                        <!-- Post 3 -->
                        <div class="pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 rounded-full bg-brand-coralLight flex items-center justify-center text-brand-coral text-xs mr-3">Anon</div>
                                <span class="text-xs text-gray-400">1 day ago</span>
                            </div>
                            <h4 class="font-semibold text-brand-slate text-sm mb-1">How to handle panic attacks?</h4>
                            <p class="text-xs text-gray-600 mb-2">My breathing exercises aren't working as well as they used to. Any advice?</p>
                            <div class="flex space-x-4 text-xs text-gray-400">
                                <span><i class="fa-regular fa-comment mr-1"></i> 22 Replies</span>
                                <span><i class="fa-regular fa-heart mr-1"></i> 18</span>
                            </div>
                        </div>
                    </div>

                    <button onclick="showToast('Share thought modal coming soon!')" class="w-full py-3 border-2 border-brand-sage text-brand-sage rounded-xl font-semibold hover:bg-brand-sageLight transition-colors mt-auto">
                        <i class="fa-solid fa-pen mr-2"></i> Share Your Thought
                    </button>
                </div>
            </div>
        </div>

        <!-- Befriender CTA Block -->
        <section class="bg-brand-slate text-white rounded-3xl p-8 md:p-10 flex flex-col md:flex-row items-center justify-between shadow-lg relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-coral rounded-full opacity-20 blur-3xl"></div>
            <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-brand-sky rounded-full opacity-20 blur-3xl"></div>
            
            <div class="z-10 mb-6 md:mb-0 md:pr-8">
                <h2 class="text-3xl font-bold mb-2">Need a 1-on-1 chat?</h2>
                <p class="text-white/80 max-w-xl text-lg">Sometimes a personal conversation is what you need most. Match with a befriender today and talk in a safe, non-judgmental space.</p>
            </div>
            <div class="z-10 w-full md:w-auto">
                <a href="befriender_network.php" class="inline-block w-full md:w-auto text-center px-8 py-4 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1">
                    Find a Befriender
                </a>
            </div>
        </section>
    </main>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2"></div>

    <?php 
    // Standard emergency modal placeholder
    if(file_exists('includes/emergency_modal.php')) {
        require_once 'includes/emergency_modal.php';
    } else {
        echo '<!-- Emergency modal placeholder -->';
    }
    
    // Global AI Widget
    if(file_exists('includes/global_ai_widget.php')) {
        require_once 'includes/global_ai_widget.php';
    }
    ?>

    <script>
        function showToast(message) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            toast.className = 'bg-brand-slate text-white px-6 py-3 rounded-lg shadow-lg flex items-center justify-between transform transition-all duration-300 translate-y-10 opacity-0';
            
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="fa-solid fa-circle-check text-brand-sageLight mr-3"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.remove()" class="ml-4 text-white/70 hover:text-white">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.remove('translate-y-10', 'opacity-0');
            }, 10);
            
            // Auto remove
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
