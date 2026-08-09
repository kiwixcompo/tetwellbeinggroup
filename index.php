<?php
/**
 * Tet Wellbeing Group - Public Gateway Landing Page (index.php)
 * Serving as the introduction and portal to the actual application.
 */
require_once 'db.php';

// Check if user is already logged in, redirect them directly to the app
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$contact_submitted = false;
$contact_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $contact_name = filter_input(INPUT_POST, 'name', FILTER_DEFAULT);
    $contact_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $contact_message = filter_input(INPUT_POST, 'message', FILTER_DEFAULT);
    
    if ($contact_name && $contact_email && $contact_message) {
        $contact_submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tet Wellbeing Group - Digital Mental Health Ecosystem</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.08)',
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
        .fade-in {
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        
        .fade-in-on-scroll.show {
            opacity: 1;
            transform: translateY(0);
        }

        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px -5px rgba(38, 70, 83, 0.12);
        }
        .hover-btn {
            transition: all 0.2s ease;
        }
        .hover-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(94, 140, 113, 0.2);
        }
        .hover-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-brand-sage/10 bg-brand-bg/80 backdrop-blur-lg">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="javascript:void(0)" onclick="alert('This feature is coming soon!')" class="flex items-center transition-transform hover:scale-[1.02] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Nav Links (Desktop) -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-brand-slate/80">
                <a href="#about" class="hover:text-brand-sage transition-colors">About Us</a>
                <a href="#features" class="hover:text-brand-sage transition-colors">Ecosystem</a>
                <a href="#contact" class="hover:text-brand-sage transition-colors">Contact</a>
                <button onclick="openTermsModal()" class="hover:text-brand-sage transition-colors">Terms of Service</button>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <!-- Emergency Support Button -->
                <button type="button" onclick="openEmergencyModal()" class="hover-btn flex items-center gap-1.5 rounded-xl bg-brand-coral px-3 py-1.5 text-xs sm:text-sm font-semibold text-white transition-all duration-300 hover:bg-brand-coralHover active:scale-95">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    </svg>
                    <span class="hidden sm:inline">Emergency</span>
                </button>

                <!-- Login Button -->
                <a href="login.php" class="hover-btn rounded-xl border border-brand-sage/30 px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-sage hover:bg-brand-sageLight transition-all active:scale-95">
                    Log In
                </a>

                <!-- Register Button -->
                <a href="signup.php" class="hover-btn rounded-xl bg-brand-sage px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-white shadow-soft hover:bg-brand-sageHover transition-all active:scale-95">
                    Sign Up
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN BODY -->
    <main class="flex-grow">
        
        <!-- HERO SECTION -->
        <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 md:py-32 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center fade-in overflow-hidden">
            <!-- Hero Typography -->
            <div class="space-y-8 relative z-10">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-sage/10 px-3 py-1 text-xs font-bold text-brand-sage uppercase tracking-wider">
                    <span class="h-2 w-2 rounded-full bg-brand-sage animate-ping"></span>
                    Ecosystem Gateway Open
                </span>
                <h1 class="text-4xl sm:text-6xl font-extrabold font-outfit text-brand-slate tracking-tight leading-[1.1]">
                    A calm space for your <br/><span class="text-brand-sage">daily mental wellbeing</span>.
                </h1>
                <p class="text-lg text-gray-500 max-w-xl leading-relaxed">
                    Tet Wellbeing Group is a digital ecosystem combining emotional tracking, specialized family caregiver support networks, peer communities, and a professional teletherapy marketplace.
                </p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-4">
                    <a href="signup.php" class="hover-btn flex justify-center items-center gap-2 rounded-2xl bg-brand-sage px-8 py-4 font-bold text-white shadow-md transition-all hover:bg-brand-sageHover active:scale-95 text-center text-lg">
                        Start Your Free Account
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                    <a href="login.php" class="hover-btn flex justify-center items-center rounded-2xl border-2 border-brand-sage/20 bg-white px-8 py-4 font-semibold text-brand-slate hover:bg-brand-sageLight transition-all active:scale-95 text-center text-lg">
                        Access Client Portal
                    </a>
                </div>
                <!-- Small credentials note for easy access -->
                <div class="p-4 bg-brand-sageLight rounded-2xl border border-brand-sage/20 text-sm text-brand-sage flex items-center gap-3 max-w-md mt-6 shadow-sm">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span><strong>Prototype Demo Access:</strong> Log in with <code class="bg-white px-1 py-0.5 rounded text-brand-slate">mark@tetwellbeing.com</code> and password <code class="bg-white px-1 py-0.5 rounded text-brand-slate">password123</code>.</span>
                </div>
            </div>

            <!-- Hero Graphic Container -->
            <div class="relative flex justify-center items-center lg:justify-end z-0">
                <!-- Abstract blobs -->
                <div class="absolute -top-10 -left-10 w-64 h-64 rounded-full bg-brand-sage/20 blur-3xl -z-10 mix-blend-multiply"></div>
                <div class="absolute -bottom-10 -right-10 w-72 h-72 rounded-full bg-brand-sky/20 blur-3xl -z-10 mix-blend-multiply"></div>
                
                <div class="relative w-full max-w-lg aspect-[4/3] sm:aspect-square lg:aspect-[4/5] rounded-[2rem] overflow-hidden shadow-2xl border-4 border-white transform transition-transform hover:scale-[1.02] duration-500">
                    <img src="images/landing_hero.png" alt="Mental Wellbeing Hero" class="w-full h-full object-cover">
                    <!-- Overlay gradient for depth -->
                    <div class="absolute inset-0 bg-gradient-to-t from-brand-slate/40 to-transparent"></div>
                </div>
            </div>
        </section>

        <!-- ANONYMOUS WELLBEING CHECK (GUEST ACCESS) -->
        <section class="py-16 bg-brand-sageLight/50 border-y border-brand-sage/20 relative overflow-hidden">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 text-center relative z-10">
                <h2 class="text-3xl font-bold font-outfit text-brand-slate mb-4">Quick Wellbeing Check</h2>
                <p class="text-gray-600 mb-8 max-w-2xl mx-auto">Take a moment to check in with yourself. No sign-up required.</p>
                
                <div class="bg-white rounded-[2rem] shadow-lg p-8 border border-white max-w-3xl mx-auto" id="guest-check-form-container">
                    <form id="guest-check-form" onsubmit="handleGuestCheck(event)">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <!-- Stress Level -->
                            <div class="text-left">
                                <label class="block text-sm font-bold text-brand-slate mb-3">Stress Level Today</label>
                                <select required class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-sm focus:border-brand-sage focus:ring-brand-sage">
                                    <option value="">Select...</option>
                                    <option value="low">Low (Calm)</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="high">High (Overwhelmed)</option>
                                </select>
                            </div>
                            <!-- Mood -->
                            <div class="text-left">
                                <label class="block text-sm font-bold text-brand-slate mb-3">Current Mood</label>
                                <select required class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-sm focus:border-brand-sage focus:ring-brand-sage">
                                    <option value="">Select...</option>
                                    <option value="good">Positive / Good</option>
                                    <option value="neutral">Neutral / Okay</option>
                                    <option value="bad">Negative / Low</option>
                                </select>
                            </div>
                            <!-- Sleep -->
                            <div class="text-left">
                                <label class="block text-sm font-bold text-brand-slate mb-3">Sleep Quality</label>
                                <select required class="w-full rounded-xl border-gray-200 bg-gray-50 p-3 text-sm focus:border-brand-sage focus:ring-brand-sage">
                                    <option value="">Select...</option>
                                    <option value="good">Well Rested</option>
                                    <option value="average">Average</option>
                                    <option value="poor">Poor / Interrupted</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="hover-btn rounded-xl bg-brand-sage px-8 py-3 text-sm font-bold text-white shadow-md hover:bg-brand-sageHover transition-all">
                            View Summary
                        </button>
                    </form>

                    <!-- Summary Results (Hidden initially) -->
                    <div id="guest-check-results" class="hidden text-center py-4">
                        <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-sageLight text-brand-sage mb-4">
                            <i class="fa-solid fa-heart-pulse text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold font-outfit text-brand-slate mb-2">Check-in Complete</h3>
                        <p class="text-gray-600 mb-6">Thank you for taking a moment for yourself. Tracking your state daily can significantly improve emotional resilience.</p>
                        
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 mb-6">
                            <p class="text-sm font-semibold text-brand-slate mb-2"><i class="fa-solid fa-lock text-gray-400 mr-2"></i>Unlock Your Personalized Action Plan</p>
                            <p class="text-xs text-gray-500 mb-4">Create a free account to view your history, receive AI-driven recommendations, and access your tailored wellbeing plan.</p>
                            <a href="signup.php" class="inline-block hover-btn rounded-xl bg-brand-slate px-6 py-2.5 text-sm font-bold text-white shadow-md hover:bg-brand-slate/90 transition-all">
                                Create Free Account
                            </a>
                        </div>
                        <button type="button" onclick="resetGuestCheck()" class="text-xs font-bold text-gray-400 hover:text-brand-sage transition-colors">
                            Retake Check-in
                        </button>
                    </div>
                </div>
            </div>
            
            <script>
                function handleGuestCheck(e) {
                    e.preventDefault();
                    document.getElementById('guest-check-form').classList.add('hidden');
                    document.getElementById('guest-check-results').classList.remove('hidden');
                }
                function resetGuestCheck() {
                    document.getElementById('guest-check-form').reset();
                    document.getElementById('guest-check-form').classList.remove('hidden');
                    document.getElementById('guest-check-results').classList.add('hidden');
                }
            </script>
        </section>

        <!-- ABOUT SECTION -->
        <section id="about" class="bg-white border-y border-[#EBE8E0] py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    
                    <div class="order-2 lg:order-1 relative fade-in-on-scroll">
                        <div class="absolute -inset-4 bg-brand-sageLight rounded-[3rem] transform -rotate-3 -z-10"></div>
                        <div class="w-full aspect-[4/3] rounded-[2rem] overflow-hidden shadow-xl border-4 border-white">
                            <img src="images/landing_wellness.png" alt="Continuous Integrated Care" class="w-full h-full object-cover">
                        </div>
                    </div>

                    <div class="order-1 lg:order-2 fade-in-on-scroll">
                        <span class="text-sm font-bold tracking-wider text-brand-sage uppercase font-outfit">Who We Are</span>
                        <h2 class="text-4xl font-bold font-outfit text-brand-slate tracking-tight mt-3 mb-6">Designed for Continuous, Integrated Care</h2>
                        <p class="text-gray-500 text-lg leading-relaxed mb-8">
                            Traditional apps provide simple journaling or basic symptom tracking. Tet Wellbeing Group goes further by linking self-reflection directly with critical caregiver hubs, peer networks, and direct access to fully vetted professional clinical services.
                        </p>
                        <ul class="space-y-6">
                            <li class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage shadow-sm">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-brand-slate font-outfit">Daily check-ins</h4>
                                    <p class="text-gray-600 mt-1">Track your moods and capture journaling logs dynamically in a secure environment.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-sky/20 text-brand-slate shadow-sm">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-brand-slate font-outfit">Secure data layers</h4>
                                    <p class="text-gray-600 mt-1">Your data is fully protected and thoughtfully accessible across integrated platforms.</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-coralLight text-brand-coral shadow-sm">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-xl font-bold text-brand-slate font-outfit">Caregiver solidarity</h4>
                                    <p class="text-gray-600 mt-1">Focused support networks designed specifically to alleviate caregiver burnout.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURES / ECOSYSTEM SECTION -->
        <section id="features" class="py-24 bg-brand-bg">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16 fade-in-on-scroll">
                    <span class="text-sm font-bold tracking-wider text-brand-sage uppercase font-outfit">Ecosystem Capabilities</span>
                    <h2 class="text-4xl font-bold font-outfit text-brand-slate tracking-tight mt-3">Core Service Segments</h2>
                    <p class="text-gray-500 text-lg mt-4">Providing specialized portals tailored to individual requirements, community spaces, and care clinics.</p>
                </div>

                <!-- Bento Grid Layout -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    
                    <!-- Teletherapy (Large Card) -->
                    <div class="lg:col-span-2 bg-white rounded-[2rem] p-6 border border-[#EBE8E0] hover-card fade-in-on-scroll flex flex-col md:flex-row gap-6 items-center">
                        <div class="w-full md:w-1/2 aspect-video rounded-xl overflow-hidden relative">
                            <img src="images/landing_teletherapy.png" alt="Clinical Specialist Telehealth" class="w-full h-full object-cover">
                        </div>
                        <div class="w-full md:w-1/2 flex flex-col justify-center">
                            <div class="inline-block px-3 py-1 bg-brand-sky/20 text-brand-slate text-xs font-bold rounded-lg mb-3 w-max">Professional</div>
                            <h3 class="font-bold font-outfit text-brand-slate text-2xl mb-2">Teletherapy Marketplace</h3>
                            <p class="text-gray-500 leading-relaxed">Direct appointments with certified therapists using built-in high-quality secure video calls. Match with the right expert for your specific needs.</p>
                        </div>
                    </div>

                    <!-- Peer Circles -->
                    <div class="bg-white rounded-[2rem] p-6 border border-[#EBE8E0] hover-card fade-in-on-scroll flex flex-col">
                        <div class="w-full aspect-video rounded-xl overflow-hidden relative mb-5">
                            <img src="images/landing_community.png" alt="Supportive Peer Circle" class="w-full h-full object-cover">
                        </div>
                        <div class="inline-block px-3 py-1 bg-brand-sageLight text-brand-sage text-xs font-bold rounded-lg mb-3 w-max">Community</div>
                        <h3 class="font-bold font-outfit text-brand-slate text-xl mb-2">Peer Circles</h3>
                        <p class="text-gray-500 text-sm leading-relaxed">Connect anonymously with peer circles holding shared journeys, guided by trained moderators.</p>
                    </div>

                    <!-- Caregiver Hub -->
                    <div class="bg-brand-slate rounded-[2rem] p-8 border border-brand-slate hover-card fade-in-on-scroll flex flex-col text-white shadow-xl relative overflow-hidden">
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-brand-sky/20 rounded-full blur-2xl"></div>
                        <div class="relative z-10">
                            <div class="inline-block px-3 py-1 bg-brand-coral/20 text-brand-coralLight text-xs font-bold rounded-lg mb-4 w-max">Support</div>
                            <h3 class="font-bold font-outfit text-2xl mb-3">Caregiver Hub</h3>
                            <p class="text-gray-300 text-sm leading-relaxed mb-6">Tools designed to combat burnout for those caring for spouses, parents, or disabled relatives. Resources, shared calendars, and emergency respites.</p>
                        </div>
                        <div class="mt-auto relative z-10 w-full h-32 rounded-xl overflow-hidden border border-white/10">
                            <img src="images/generational_anchor.png" alt="Caregiver Connect" class="w-full h-full object-cover opacity-80">
                        </div>
                    </div>

                    <!-- Daily Check-In -->
                    <div class="lg:col-span-2 bg-white rounded-[2rem] p-6 border border-[#EBE8E0] hover-card fade-in-on-scroll flex flex-col md:flex-row-reverse gap-6 items-center">
                        <div class="w-full md:w-1/2 aspect-video rounded-xl overflow-hidden relative border border-gray-100">
                            <img src="images/blooming_renewal.png" alt="Daily Check-In" class="w-full h-full object-cover">
                        </div>
                        <div class="w-full md:w-1/2 flex flex-col justify-center">
                            <div class="inline-block px-3 py-1 bg-[#FDF8F5] text-brand-coral text-xs font-bold rounded-lg mb-3 w-max">Personal</div>
                            <h3 class="font-bold font-outfit text-brand-slate text-2xl mb-2">Reflective Log & Check-in</h3>
                            <p class="text-gray-500 leading-relaxed">Reflect on your mood, track patterns, and store thoughts in your private secure journal. Watch your mental resilience bloom day by day.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- CONTACT SECTION -->
        <section id="contact" class="bg-white py-24 border-t border-[#EBE8E0]">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16 fade-in-on-scroll">
                    <span class="text-sm font-bold tracking-wider text-brand-sage uppercase font-outfit">Get in Touch</span>
                    <h2 class="text-4xl font-bold font-outfit text-brand-slate tracking-tight mt-3">Have Questions? Reach Out</h2>
                    <p class="text-gray-500 mt-4 text-lg">Our support staff is happy to answer questions regarding corporate care, platform tools, or clinical partnership opportunities.</p>
                </div>

                <!-- Contact Form Card -->
                <div class="bg-brand-bg rounded-[2rem] p-8 md:p-12 border border-brand-sage/10 shadow-card fade-in-on-scroll max-w-3xl mx-auto relative overflow-hidden">
                    <!-- Decor -->
                    <div class="absolute -right-16 -top-16 w-64 h-64 bg-brand-sage/10 rounded-full blur-3xl pointer-events-none"></div>

                    <?php if ($contact_submitted): ?>
                        <div class="bg-brand-sageLight border border-brand-sage text-brand-slate p-5 rounded-2xl mb-8 flex items-start gap-4">
                            <div class="text-brand-sage mt-0.5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg font-outfit">Message Sent!</h4>
                                <p class="text-sm text-gray-600 mt-1">Thank you, <?php echo htmlspecialchars($contact_name); ?>. A team representative will reply to your inquiry shortly.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="#contact" class="space-y-6 relative z-10">
                        <input type="hidden" name="contact_form" value="1">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-bold text-brand-slate mb-2">Your Name</label>
                                <input type="text" id="name" name="name" required class="w-full rounded-xl border-2 border-gray-200 bg-white p-4 text-sm focus:border-brand-sage focus:ring-4 focus:ring-brand-sage/10 outline-none transition-all">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-bold text-brand-slate mb-2">Email Address</label>
                                <input type="email" id="email" name="email" required class="w-full rounded-xl border-2 border-gray-200 bg-white p-4 text-sm focus:border-brand-sage focus:ring-4 focus:ring-brand-sage/10 outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-bold text-brand-slate mb-2">Message</label>
                            <textarea id="message" name="message" rows="5" required class="w-full rounded-xl border-2 border-gray-200 bg-white p-4 text-sm focus:border-brand-sage focus:ring-4 focus:ring-brand-sage/10 outline-none transition-all resize-none" placeholder="How can we help you?"></textarea>
                        </div>

                        <button type="submit" class="hover-btn w-full py-4 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-lg shadow-md transition-all">
                            Submit Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="border-t border-[#EBE8E0] bg-brand-bg py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6 text-sm text-gray-500">
            <div class="flex items-center gap-3">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-8 w-auto">
                <span class="font-bold text-brand-slate font-outfit text-base">&copy; 2026 Tet Wellbeing</span>
            </div>
            
            <div class="flex items-center gap-8 font-semibold">
                <button onclick="openTermsModal()" class="hover:text-brand-sage transition-colors">Terms of Service</button>
                <a href="javascript:void(0)" onclick="alert('This feature is coming soon!')" class="hover:text-brand-sage transition-colors">Privacy Policy</a>
                <a href="#contact" class="hover:text-brand-sage transition-colors">Contact</a>
            </div>
            
            <div class="text-xs">
                All rights reserved. Professional services provided via licensed practitioners.
            </div>
        </div>
    </footer>

    <!-- EMERGENCY MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-[2rem] p-8 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-5 right-5 text-gray-400 hover:text-brand-slate transition-colors p-1.5 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-4 mb-5 text-brand-coral">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-sm text-brand-coral font-bold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-8 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-4 mb-8">
                <div class="flex items-center justify-between p-4 rounded-2xl bg-brand-inputBg border border-gray-100 hover-card">
                    <div>
                        <h4 class="text-base font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="hover-btn px-5 py-2 rounded-xl bg-brand-coral text-white text-sm font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-4 rounded-2xl bg-brand-inputBg border border-gray-100 hover-card">
                    <div>
                        <h4 class="text-base font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="hover-btn px-5 py-2 rounded-xl bg-brand-coral text-white text-sm font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-4 rounded-2xl bg-brand-inputBg border border-gray-100 hover-card">
                    <div>
                        <h4 class="text-base font-bold text-brand-slate">Crisis Text Line (US/CA)</h4>
                        <p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p>
                    </div>
                    <a href="sms:741741?body=HOME" class="hover-btn px-5 py-2 rounded-xl bg-brand-slate text-white text-sm font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- TERMS & CONDITIONS MODAL -->
    <div id="terms-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeTermsModal()"></div>
        <div class="relative bg-white w-full max-w-2xl rounded-[2rem] p-8 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300 flex flex-col max-h-[85vh]">
            <button onclick="closeTermsModal()" class="absolute top-5 right-5 text-gray-400 hover:text-brand-slate transition-colors p-1.5 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            
            <h3 class="text-2xl font-bold font-outfit text-brand-slate mb-6">Terms of Service & Privacy Policy</h3>
            
            <div class="flex-grow overflow-y-auto text-sm text-gray-600 space-y-6 pr-4 custom-scrollbar">
                <section>
                    <h4 class="font-bold text-brand-slate text-base">1. Agreement to Terms</h4>
                    <p class="mt-2 leading-relaxed">By accessing or using Tet Wellbeing Group platforms, you agree to comply with and be bound by these Terms of Service. These terms constitute a legally binding agreement between you and Tet Wellbeing Group.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate text-base">2. Scope of Services</h4>
                    <p class="mt-2 leading-relaxed">Tet Wellbeing Group provides mood tracking journals, caregiver resources, peer community groups, and practitioner matches. We do not provide direct emergency medical diagnostics.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate text-base">3. Privacy & Personal Records</h4>
                    <p class="mt-2 leading-relaxed">We respect user privacy. All self-logged daily check-ins are fully encrypted and will never be shared with third-party advertisers without explicit, active consent.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate text-base">4. Crisis Disclaimer</h4>
                    <p class="mt-2 leading-relaxed font-semibold text-brand-coral">IF YOU ARE EXPERIENCING A MEDICAL EMERGENCY OR CRISIS, PLEASE IMMEDIATELY CONTACT THE 988 LIFELINE OR YOUR LOCAL EMERGENCY DISPATCH (911). THIS APPLICATION IS NOT AN EMERGENCY DISPATCH INTERFACE.</p>
                </section>
            </div>

            <button onclick="closeTermsModal()" class="hover-btn w-full mt-8 py-4 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-lg transition-colors shadow-soft">
                Accept and Close
            </button>
        </div>
    </div>

    <!-- JS ACTIONS & ANIMATIONS -->
    <script>
        // Modals
        const emergencyModal = document.getElementById('emergency-modal');
        const termsModal = document.getElementById('terms-modal');

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

        function openTermsModal() {
            termsModal.classList.remove('opacity-0', 'pointer-events-none');
            termsModal.querySelector('.relative').classList.remove('scale-95');
            termsModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeTermsModal() {
            termsModal.classList.add('opacity-0', 'pointer-events-none');
            termsModal.querySelector('.relative').classList.remove('scale-100');
            termsModal.querySelector('.relative').classList.add('scale-95');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEmergencyModal();
                closeTermsModal();
            }
        });

        // Intersection Observer for scroll animations
        document.addEventListener('DOMContentLoaded', () => {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('show');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in-on-scroll').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
