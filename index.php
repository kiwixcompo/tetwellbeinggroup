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
    $contact_name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $contact_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $contact_message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);
    
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
                            cardBg: '#FFFFFF'
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
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="#" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
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
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-1.5 rounded-xl bg-brand-coral px-3 py-1.5 text-xs sm:text-sm font-semibold text-white transition-all duration-300 hover:bg-brand-coralHover active:scale-95">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    </svg>
                    <span class="hidden sm:inline">Emergency</span>
                </button>

                <!-- Login Button -->
                <a href="login.php" class="rounded-xl border border-brand-sage/30 px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-brand-sage hover:bg-brand-sageLight transition-all active:scale-95">
                    Log In
                </a>

                <!-- Register Button -->
                <a href="signup.php" class="rounded-xl bg-brand-sage px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-white shadow-soft hover:bg-brand-sageHover transition-all active:scale-95">
                    Sign Up
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN BODY -->
    <main class="flex-grow">
        
        <!-- HERO SECTION -->
        <section class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-16 md:py-24 grid grid-cols-1 md:grid-cols-2 gap-12 items-center fade-in">
            <!-- Hero Typography -->
            <div class="space-y-6">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-sage/10 px-3 py-1 text-xs font-bold text-brand-sage uppercase tracking-wider">
                    <span class="h-2 w-2 rounded-full bg-brand-sage animate-ping"></span>
                    Ecosystem Gateway Open
                </span>
                <h1 class="text-4xl sm:text-5xl font-extrabold font-outfit text-brand-slate tracking-tight leading-tight">
                    A calm space for your <span class="text-brand-sage">daily mental wellbeing</span>.
                </h1>
                <p class="text-base text-gray-500 max-w-lg leading-relaxed">
                    Tet Wellbeing Group is a digital ecosystem combining emotional tracking, specialized family caregiver support networks, peer communities, and a professional teletherapy marketplace.
                </p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-2">
                    <a href="signup.php" class="flex justify-center items-center gap-2 rounded-2xl bg-brand-sage px-6 py-3.5 font-bold text-white shadow-md transition-all hover:bg-brand-sageHover active:scale-95 text-center">
                        Start Your Free Account
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                    <a href="login.php" class="flex justify-center items-center rounded-2xl border border-gray-300 bg-white px-6 py-3.5 font-semibold text-brand-slate hover:bg-gray-50 transition-all active:scale-95 text-center">
                        Access Client Portal
                    </a>
                </div>
                <!-- Small credentials note for easy access -->
                <div class="p-3 bg-brand-sageLight rounded-xl border border-brand-sage/10 text-xs text-brand-sage flex items-center gap-2 max-w-md">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span><strong>Prototype Demo Access:</strong> Log in with <code>mark@tetwellbeing.com</code> and password <code>password123</code> to test right away.</span>
                </div>
            </div>

            <!-- Hero Graphic Placeholder / Vector Design -->
            <div class="relative flex justify-center items-center">
                <!-- Outer abstract glow -->
                <div class="absolute w-72 h-72 md:w-96 md:h-96 rounded-full bg-brand-sky/20 blur-3xl -z-10 animate-pulse"></div>
                
                <!-- Main Graphic Wrapper -->
                <div class="relative w-full max-w-md aspect-square bg-white rounded-3xl p-6 shadow-2xl border border-[#EBE8E0]/70 flex flex-col justify-between overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-brand-coral"></div>
                            <div class="h-3 w-3 rounded-full bg-brand-sky"></div>
                            <div class="h-3 w-3 rounded-full bg-brand-sage"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-400">Mental Wellbeing Hub</span>
                    </div>

                    <!-- Calming Sanctuary Image (Quiet Sanctuary) -->
                    <div class="flex-grow flex items-center justify-center py-4">
                        <div class="w-full h-44 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                            <img src="images/quiet_sanctuary.png" alt="Serene Sanctuary" class="w-full h-full object-cover">
                        </div>
                    </div>

                    <!-- Interactive Status mockup inside hero -->
                    <div class="bg-brand-bg rounded-2xl p-4 border border-[#EBE8E0]/50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-brand-sage/20 text-brand-sage flex items-center justify-center font-bold text-xs">
                                ☺
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-brand-slate">Reflective Log</h4>
                                <p class="text-[10px] text-gray-400">Streak: 12 Days Calmed</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-brand-sage bg-[#E8EFEA] px-2.5 py-1 rounded-xl">Active</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ABOUT SECTION -->
        <section id="about" class="bg-white border-y border-[#EBE8E0] py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-sm font-semibold tracking-wider text-brand-sage uppercase font-outfit">Who We Are</span>
                    <h2 class="text-3xl font-bold font-outfit text-brand-slate tracking-tight mt-2">A Holistic Approach to Digital Mental Health</h2>
                    <p class="text-gray-500 mt-3">We believe that mental wellness requires a comprehensive ecosystem. By supporting individuals, family caregivers, and peer groups, we build a circle of strength.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="text-2xl font-bold font-outfit text-brand-slate">Designed for Continuous, Integrated Care</h3>
                        <p class="text-gray-500 mt-4 leading-relaxed">
                            Traditional apps provide simple journaling or basic symptom tracking. Tet Wellbeing Group goes further by linking self-reflection directly with critical caregiver hubs, peer networks, and direct access to fully vetted professional clinical services.
                        </p>
                        <ul class="mt-6 space-y-3.5">
                            <li class="flex items-start gap-3">
                                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E8EFEA] text-brand-sage mt-0.5">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <span class="text-sm text-gray-600"><strong>Daily check-ins:</strong> Track your moods and capture journaling logs dynamically.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E8EFEA] text-brand-sage mt-0.5">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <span class="text-sm text-gray-600"><strong>Secure data layers:</strong> Your data is secure and accessible across platforms.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E8EFEA] text-brand-sage mt-0.5">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <span class="text-sm text-gray-600"><strong>Caregiver solidarity:</strong> Focused support blocks designed to alleviate caregiver burnout.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Custom Visual Representing About Us (Vibrant Mockup Card layout) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-brand-bg rounded-2xl p-5 border border-gray-100 flex flex-col justify-between aspect-square">
                            <span class="text-3xl">🌿</span>
                            <div>
                                <h4 class="font-bold text-brand-slate text-base font-outfit">Sustained Growth</h4>
                                <p class="text-xs text-gray-400 mt-1">Nurturing mental resilience through daily steps.</p>
                            </div>
                        </div>
                        <div class="bg-[#E8EFEA] rounded-2xl p-5 border border-brand-sage/10 flex flex-col justify-between aspect-square mt-6">
                            <span class="text-3xl text-brand-sage">🛡</span>
                            <div>
                                <h4 class="font-bold text-brand-slate text-base font-outfit">Clinical Safety</h4>
                                <p class="text-xs text-gray-500 mt-1">Direct panic-button emergency integration.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURES / ECOSYSTEM SECTION -->
        <section id="features" class="py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-sm font-semibold tracking-wider text-brand-sage uppercase font-outfit">Ecosystem Capabilities</span>
                    <h2 class="text-3xl font-bold font-outfit text-brand-slate tracking-tight mt-2">Core Service Segments</h2>
                    <p class="text-gray-500 mt-3">Providing specialized portals tailored to individual requirements, community spaces, and care clinics.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Feature 1: Check-in -->
                    <div class="bg-white rounded-3xl p-4 border border-[#EBE8E0] shadow-card flex flex-col justify-between hover:shadow-soft hover:-translate-y-1 transition-all duration-300">
                        <div>
                            <div class="w-full h-32 rounded-2xl overflow-hidden mb-4 border border-brand-sage/5">
                                <img src="images/blooming_renewal.png" alt="Daily Check-In Sprout" class="w-full h-full object-cover">
                            </div>
                            <h3 class="font-bold font-outfit text-brand-slate text-lg">Daily Check-In</h3>
                            <p class="text-xs text-gray-400 mt-2 leading-relaxed">Reflect on your mood, track patterns, and store thoughts in your private secure journal.</p>
                        </div>
                    </div>

                    <!-- Feature 2: Caregiver Hub -->
                    <div class="bg-white rounded-3xl p-4 border border-[#EBE8E0] shadow-card flex flex-col justify-between hover:shadow-soft hover:-translate-y-1 transition-all duration-300">
                        <div>
                            <div class="w-full h-32 rounded-2xl overflow-hidden mb-4 border border-brand-sage/5">
                                <img src="images/generational_anchor.png" alt="Caregiver Hand Connection" class="w-full h-full object-cover">
                            </div>
                            <h3 class="font-bold font-outfit text-brand-slate text-lg">Caregiver Hub</h3>
                            <p class="text-xs text-gray-400 mt-2 leading-relaxed">Tools designed to combat burnout for those caring for spouses, parents, or disabled relatives.</p>
                        </div>
                    </div>

                    <!-- Feature 3: Community -->
                    <div class="bg-white rounded-3xl p-4 border border-[#EBE8E0] shadow-card flex flex-col justify-between hover:shadow-soft hover:-translate-y-1 transition-all duration-300">
                        <div>
                            <div class="w-full h-32 rounded-2xl overflow-hidden mb-4 border border-brand-sage/5">
                                <img src="images/unfiltered_circle.png" alt="Supportive Peer Circle" class="w-full h-full object-cover">
                            </div>
                            <h3 class="font-bold font-outfit text-brand-slate text-lg">Peer Circles</h3>
                            <p class="text-xs text-gray-400 mt-2 leading-relaxed">Connect anonymously with peer circles holding shared journeys, guided by moderators.</p>
                        </div>
                    </div>

                    <!-- Feature 4: Teletherapy -->
                    <div class="bg-white rounded-3xl p-4 border border-[#EBE8E0] shadow-card flex flex-col justify-between hover:shadow-soft hover:-translate-y-1 transition-all duration-300">
                        <div>
                            <div class="w-full h-32 rounded-2xl overflow-hidden mb-4 border border-brand-sage/5">
                                <img src="images/safe_bridge.png" alt="Clinical Specialist Telehealth" class="w-full h-full object-cover">
                            </div>
                            <h3 class="font-bold font-outfit text-brand-slate text-lg">Teletherapy</h3>
                            <p class="text-xs text-gray-400 mt-2 leading-relaxed">Direct appointments with certified therapists using built-in high-quality secure video calls.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTACT SECTION -->
        <section id="contact" class="bg-white border-t border-[#EBE8E0] py-20">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-xl mx-auto mb-12">
                    <span class="text-sm font-semibold tracking-wider text-brand-sage uppercase font-outfit">Get in Touch</span>
                    <h2 class="text-3xl font-bold font-outfit text-brand-slate tracking-tight mt-2">Have Questions? Reach Out</h2>
                    <p class="text-gray-500 mt-2 text-sm">Our support staff is happy to answer questions regarding corporate care, platform tools, or clinical partnership opportunities.</p>
                </div>

                <!-- Contact Form Card -->
                <div class="bg-brand-bg rounded-3xl p-6 md:p-8 border border-[#EBE8E0] shadow-soft">
                    <?php if ($contact_submitted): ?>
                        <div class="bg-[#E8EFEA] border border-brand-sage text-brand-slate p-4 rounded-2xl mb-6">
                            <h4 class="font-bold text-sm">Message Sent!</h4>
                            <p class="text-xs text-gray-600 mt-0.5">Thank you, <?php echo htmlspecialchars($contact_name); ?>. A team representative will reply to your inquiry shortly.</p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="#contact" class="space-y-4">
                        <input type="hidden" name="contact_form" value="1">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-xs font-semibold text-brand-slate mb-1">Your Name</label>
                                <input type="text" id="name" name="name" required class="w-full rounded-xl border border-gray-200 bg-white p-3 text-sm focus:border-brand-sage focus:outline-none transition-all">
                            </div>
                            <div>
                                <label for="email" class="block text-xs font-semibold text-brand-slate mb-1">Email Address</label>
                                <input type="email" id="email" name="email" required class="w-full rounded-xl border border-gray-200 bg-white p-3 text-sm focus:border-brand-sage focus:outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label for="message" class="block text-xs font-semibold text-brand-slate mb-1">Message</label>
                            <textarea id="message" name="message" rows="5" required class="w-full rounded-xl border border-gray-200 bg-white p-3 text-sm focus:border-brand-sage focus:outline-none transition-all" placeholder="How can we help you?"></textarea>
                        </div>

                        <button type="submit" class="w-full py-3 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm shadow-md transition-all active:scale-95">
                            Submit Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="border-t border-[#EBE8E0] bg-brand-bg py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-gray-500">
            <div class="flex items-center gap-2">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-7 w-auto">
                <span class="font-bold text-brand-slate font-outfit">&copy; 2026</span>
            </div>
            
            <div class="flex items-center gap-6">
                <button onclick="openTermsModal()" class="hover:text-brand-sage transition-colors">Terms of Service</button>
                <a href="#" class="hover:text-brand-sage transition-colors">Privacy Policy</a>
                <a href="#contact" class="hover:text-brand-sage transition-colors">Contact</a>
            </div>
            
            <div>
                All rights reserved. Professional services provided via licensed practitioners.
            </div>
        </div>
    </footer>

    <!-- EMERGENCY MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Crisis Text Line (US/CA)</h4>
                        <p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p>
                    </div>
                    <a href="sms:741741?body=HOME" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-xs text-gray-500">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text SHOUT</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- TERMS & CONDITIONS MODAL -->
    <div id="terms-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeTermsModal()"></div>
        <div class="relative bg-white w-full max-w-lg rounded-3xl p-6 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300 flex flex-col max-h-[85vh]">
            <button onclick="closeTermsModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            
            <h3 class="text-xl font-bold font-outfit text-brand-slate mb-4">Terms of Service & Privacy Policy</h3>
            
            <div class="flex-grow overflow-y-auto text-sm text-gray-600 space-y-4 pr-2">
                <section>
                    <h4 class="font-bold text-brand-slate">1. Agreement to Terms</h4>
                    <p class="text-xs mt-1">By accessing or using Tet Wellbeing Group platforms, you agree to comply with and be bound by these Terms of Service. These terms constitute a legally binding agreement.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate">2. Scope of Services</h4>
                    <p class="text-xs mt-1">Tet Wellbeing Group provides mood tracking journals, caregiver resources, peer community groups, and practitioner matches. We do not provide direct emergency medical diagnostics.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate">3. Privacy & Personal Records</h4>
                    <p class="text-xs mt-1">We respect user privacy. All self-logged daily check-ins are fully encrypted and will never be shared with third-party advertisers without explicit, active consent.</p>
                </section>
                <section>
                    <h4 class="font-bold text-brand-slate">4. Crisis Disclaimer</h4>
                    <p class="text-xs mt-1">IF YOU ARE EXPERIENCING A MEDICAL EMERGENCY OR CRISIS, PLEASE IMMEDIATELY CONTACT THE 988 LIFELINE OR YOUR LOCAL EMERGENCY DISPATCH (911). THIS APPLICATION IS NOT AN EMERGENCY DISPATCH INTERFACE.</p>
                </section>
            </div>

            <button onclick="closeTermsModal()" class="w-full mt-6 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-semibold text-sm transition-colors shadow-soft">
                Accept and Close
            </button>
        </div>
    </div>

    <!-- JS ACTIONS -->
    <script>
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

        // Close on Esc key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEmergencyModal();
                closeTermsModal();
            }
        });
    </script>
</body>
</html>
