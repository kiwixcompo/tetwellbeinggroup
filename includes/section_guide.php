<?php
/**
 * Tet Wellbeing Group - Section-by-Section Interactive Guide & Tour Component (includes/section_guide.php)
 * Highlights sections with SVG backdrop-blur spotlight cutouts, glowing halo frames, pop-out elevation,
 * deep step-by-step "How It Works" instructions, and comprehensive coverage across all platform modules.
 */
?>
<!-- SECTION GUIDE / WALKTHROUGH OVERLAY COMPONENT -->
<div id="section-guide-overlay" class="fixed inset-0 z-[9999] hidden pointer-events-none transition-opacity duration-300 opacity-0 select-none">
    
    <!-- SVG Masked Dark & Blurred Backdrop Layer -->
    <!-- The SVG uses an evenodd path to cut out a rounded hole directly over the active element -->
    <svg id="guide-spotlight-svg" class="fixed inset-0 w-full h-full pointer-events-none z-[9999]" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <mask id="guide-cutout-mask">
                <!-- White = opaque / blurred mask -->
                <rect width="100%" height="100%" fill="white" />
                <!-- Black = transparent cutout hole over spotlighted element -->
                <rect id="guide-cutout-hole" x="0" y="0" width="0" height="0" rx="20" ry="20" fill="black" />
            </mask>
        </defs>
        <!-- Dark Tinted Backdrop (blocks everything except the cutout hole) -->
        <rect id="guide-backdrop-rect" width="100%" height="100%" fill="rgba(15, 23, 42, 0.72)" mask="url(#guide-cutout-mask)" class="pointer-events-auto cursor-pointer" onclick="closeSectionGuide()" />
    </svg>

    <!-- Backdrop Blur Filter Layer (masked so only the background outside the spotlight is blurred) -->
    <div id="guide-blur-backdrop" class="fixed inset-0 pointer-events-none z-[9999]" style="-webkit-mask: url(#guide-cutout-mask); mask: url(#guide-cutout-mask); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"></div>

    <!-- Spotlight Halo / Glowing Ring Frame (smoothly animates and tracks the spotlighted element) -->
    <div id="guide-spotlight-frame" class="fixed pointer-events-none z-[10000] rounded-3xl transition-all duration-300 opacity-0 border-2 border-brand-sage" style="box-shadow: 0 0 0 4px rgba(94, 140, 113, 0.4), 0 0 35px rgba(94, 140, 113, 0.6), 0 25px 50px rgba(0, 0, 0, 0.5);">
        <!-- Active Spotlight Beacon Pin in corner -->
        <div class="absolute -top-3.5 -left-3.5 flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-sage text-white text-[11px] font-bold font-outfit shadow-lg tracking-wider uppercase">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
            </span>
            <span>Feature Spotlight</span>
        </div>
    </div>

    <!-- Floating Interactive Tip Card -->
    <div id="section-guide-card" class="fixed z-[10002] pointer-events-auto bg-white rounded-3xl shadow-2xl border border-brand-sage/20 max-w-lg w-[calc(100vw-2rem)] transition-all duration-300 transform scale-95 opacity-0 overflow-hidden font-sans">
        
        <!-- Top Status Bar & Category -->
        <div class="bg-gradient-to-r from-brand-bg to-brand-sageLight/50 px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 flex-wrap">
                <span id="guide-cat-badge" class="px-3 py-1 rounded-full bg-brand-slate text-white text-[11px] font-bold uppercase tracking-wider font-outfit shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-compass text-[10px]"></i> Ecosystem Guide
                </span>
                <span id="guide-step-badge" class="px-2.5 py-1 rounded-full bg-white text-brand-sage border border-brand-sage/20 text-[11px] font-bold font-outfit">
                    Part 1 of 5
                </span>
            </div>
            
            <!-- Close (X) button -->
            <button onclick="closeSectionGuide()" class="text-gray-400 hover:text-brand-slate p-1.5 rounded-full hover:bg-white transition-colors" title="Close Guide (Esc)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Animated Progress Bar -->
        <div class="w-full bg-gray-100 h-1.5 overflow-hidden">
            <div id="guide-progress-bar" class="bg-gradient-to-r from-brand-sage to-emerald-500 h-full transition-all duration-300" style="width: 20%;"></div>
        </div>

        <!-- Main Body -->
        <div class="p-6 md:p-7 max-h-[70vh] overflow-y-auto">
            <!-- Title -->
            <div class="flex items-start gap-3 mb-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage text-base shadow-sm mt-0.5" id="guide-icon-box">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div>
                    <h3 id="guide-title" class="text-xl font-bold font-outfit text-brand-slate tracking-tight leading-snug">Section Title</h3>
                    <p class="text-xs text-brand-sage font-semibold uppercase tracking-wider mt-0.5" id="guide-subtitle">What this feature does</p>
                </div>
            </div>

            <!-- Description -->
            <p id="guide-desc" class="text-sm text-gray-600 leading-relaxed mb-5">Detailed overview explaining this part.</p>

            <!-- "How It Works & How to Use" Guidance Box -->
            <div class="bg-[#FAF9F6] rounded-2xl p-4 border border-brand-sage/20 mb-5">
                <div class="flex items-center gap-2 mb-2 text-brand-slate font-bold text-xs uppercase tracking-wider font-outfit">
                    <i class="fa-solid fa-circle-check text-brand-sage text-sm"></i>
                    <span>How It Works & How To Use It:</span>
                </div>
                <ul id="guide-how-it-works" class="text-xs text-gray-600 space-y-1.5 pl-1 leading-relaxed">
                    <!-- Dynamic bullet steps inserted here -->
                </ul>
            </div>

            <!-- Interactive Step Indicator Dots -->
            <div class="flex items-center justify-center gap-1.5 py-1 mb-2" id="guide-step-dots">
                <!-- Dots populated by JS -->
            </div>
        </div>

        <!-- Footer Navigation Controls -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
            <button id="guide-prev-btn" onclick="prevGuideStep()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:text-brand-slate hover:bg-gray-200/70 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Previous
            </button>
            
            <div class="flex items-center gap-2">
                <button onclick="closeSectionGuide()" class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-400 hover:text-gray-600 transition-all">
                    Skip Tour
                </button>
                <button id="guide-next-btn" onclick="nextGuideStep()" class="px-6 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold transition-all shadow-md active:scale-95 flex items-center gap-2 font-outfit">
                    <span>Next Feature</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* When the element is popped out into the spotlight */
.guide-target-popped {
    position: relative !important;
    z-index: 10001 !important;
    filter: brightness(1.05) contrast(1.02) !important;
    transition: filter 0.3s ease !important;
}
/* Subtle blur on background body while tour is active */
body.guide-active-body {
    overflow: hidden !important;
}
</style>

<script>
let currentGuideIndex = 0;
let activeGuideSteps = [];
let currentSpotlightRect = null;
let guideScrollTimeout = null;

// Comprehensive, detailed guide definitions for EVERY page across the Tet Wellbeing platform
const pageGuideDefinitions = {
    'dashboard.php': [
        {
            selector: 'header nav.hidden.md\\:flex, header nav',
            category: 'Ecosystem Architecture',
            title: '4-Pillar Global Navigation',
            subtitle: 'Core Platform Pillars',
            desc: 'Tet Wellbeing Group brings together clinical mental healthcare, proactive self-care, peer circles, and psychoeducation into four unified pillars. You can move between them seamlessly with zero loss of progress.',
            howToUse: [
                'Click "My TET" for your personal daily dashboard and telemetry.',
                'Click "MindBloom" for private self-care tools, journaling, and breathing exercises.',
                'Click "Community & Care" for support circles, video meetups, and teletherapy.',
                'Click "Learning Center" for masterclasses, guided meditations, and clinical studies.'
            ]
        },
        {
            selector: '.mb-8.relative.w-full.h-\\[250px\\], div:has(> img[alt="Calming Background"])',
            category: 'Personalized Routine',
            title: 'Daily Command Center & Intelligent Navigator',
            subtitle: 'Adaptive Time-of-Day Guidance',
            desc: 'Your central hub greets you with personalized greetings and adaptive routines tailored to the current time of day—recommending box breathing in the morning, energy resets in the afternoon, and sleep soundscapes in the evening.',
            howToUse: [
                'Displays your current wellbeing status and daily streak.',
                'Features the TET Navigator card on the right suggesting your single highest-impact next step.',
                'Refreshes recommendations each morning to maintain steady daily resilience.'
            ]
        },
        {
            selector: 'section:has(#checkin-form), section:has(.mood-btn)',
            category: 'Clinical Triage',
            title: 'Daily Emotional Summary & Check-in',
            subtitle: '8 Calibrated Emotional States & Tracking Calendar',
            desc: 'A quick, private daily reflection tracking your emotional pulse. Expressed through 8 realistic portrait avatars: Overwhelmed, Low, Anxious, Exhausted, Frustrated, Okay, Grow, or Talk.',
            howToUse: [
                'Hover over any face to view an enlarged portrait and tailored motivational wisdom.',
                'Tap your current emotion, add optional journal reflections, and click "Save Check-in" for instant logging animation.',
                'Switch between Month and Year calendar views to inspect your longitudinal emotional trends.'
            ]
        },
        {
            selector: 'section:has(a[href*="predictive_hub"]), section:has(h3:contains("AI Insights")), .lg\\:col-span-5 section:first-child',
            category: 'Predictive Telemetry',
            title: 'Digital Twin & Emotional Health Index (EHI)',
            subtitle: 'Real-Time Biometric & Mood Telemetry',
            desc: 'Your secure Digital Twin analyzes your check-ins, journal reflections, and activity trends to calculate your overall Emotional Health Index (0-100) and identify burnout risks before they happen.',
            howToUse: [
                'Monitors your stress patterns over time (e.g. noticing stress spikes on weekends or caregiving fatigue).',
                'Provides early warning alerts if your wellbeing trajectory shows a downward trend.',
                'Click "Open Digital Twin" to view deep predictive metrics and clinical insights.'
            ]
        },
        {
            selector: 'section:has(a[href*="wellbeing_passport"]), section:has(h3:contains("Wellbeing Passport"))',
            category: 'Gamified Growth',
            title: 'Wellbeing Passport & Resilience Levels',
            subtitle: 'Celebrating Personal Milestones',
            desc: 'A non-clinical, strength-based progress system that honors your resilience journey. Earn XP and level up your passport by completing self-care sessions and structured challenges.',
            howToUse: [
                'Displays your current resilience level and completed wellbeing activities.',
                'Focuses on positive self-understanding: "I understand myself better because of TET."',
                'Click "View Full Passport" to explore 7-day to 30-day structured challenges.'
            ]
        },
        {
            selector: '#ai-widget-toggle, #tet-floating-ai-launcher, button[title*="AI"]',
            category: 'Empathetic AI Companion',
            title: '24/7 AI Companion & Voice Coach',
            subtitle: 'Always-Available Listening & CBT Support',
            desc: 'Instant, judgment-free support whenever you need someone to talk to. Powered by empathetic natural language understanding, natural speech recognition, and realistic gender-matched voices.',
            howToUse: [
                'Click the floating circular widget at the bottom-right of any page to open your companion.',
                'Speak or type your thoughts to practice CBT reframing, express gratitude, or unpack stress.',
                'Learns continuously from your account so your discussions are tailored to your history.'
            ]
        },
        {
            selector: 'header button[onclick*="Emergency"], button[onclick*="openEmergencyModal"], a[href*="emergency"]',
            category: 'Safety & Crisis Protocol',
            title: 'Instant 24/7 Emergency Support',
            subtitle: 'Clinical Lifeline & Crisis Directory',
            desc: 'Safety is our absolute foundation. One click gives you immediate, direct telephone and SMS access to verified emergency crisis hotlines and human counselors.',
            howToUse: [
                'Available on every single page in the top header.',
                'Direct one-tap dialing for NHS 111, Samaritans (116 123), Shout text line (85258), and Emergency 999.',
                'Accessible without any subscription barriers or login limitations.'
            ]
        },
        {
            selector: 'header .relative.group, header div:has(> button > div.rounded-full)',
            category: 'Account & Management',
            title: 'Profile, Subscription & Settings Management',
            subtitle: 'Roles, Invoices & Corporate Access',
            desc: 'Manage your credentials, subscription tiers (Free Starter, Professional, Corporate), view VAT receipts, and switch to administrative or corporate portals.',
            howToUse: [
                'Click your avatar in the top-right corner to open the quick navigation drawer.',
                'Access Subscription & Billing to upgrade plans or download past invoices.',
                'Access the Corporate HR Portal or Workplace Safety Hub if your account has management privileges.'
            ]
        }
    ],

    'mindbloom.php': [
        {
            selector: 'aside, .lg\\:w-1\\/4, .w-full.lg\\:w-1\\/4',
            category: 'Self-Care Sanctuary',
            title: 'MindBloom Toolkit Sidebar',
            subtitle: 'Modular Clinical Tools',
            desc: 'MindBloom is your private self-care sanctuary. Use the left navigation to jump between Core micro-practices, confidential TrueVent Journaling, Clinical Check-ins, VR Resilience, and Ambient Mixer.',
            howToUse: [
                'Click any tool in the sidebar to load the corresponding interactive module.',
                'Plan badges show which tools are included in each tier (preview mode has all unlocked).'
            ]
        },
        {
            selector: 'a[href*="grounding"], div:has(h3:contains("Grounding"))',
            category: 'Somatic Regulation',
            title: '5-4-3-2-1 Sensory Grounding',
            subtitle: 'Rapid Anxiety De-escalation',
            desc: 'A clinically proven somatic protocol to break panic loops and acute anxiety by systematically engaging all five physical senses.',
            howToUse: [
                'Click to start the step-by-step interactive sequence.',
                'Follow prompts to note 5 things you see, 4 you feel, 3 you hear, 2 you smell, and 1 you taste.'
            ]
        },
        {
            selector: 'a[href*="breathing"], a[href*="action=breathing"]',
            category: 'Vagus Nerve Reset',
            title: 'Paced Breathing & Box Breathing Gym',
            subtitle: 'Heart Rate Variability (HRV) Training',
            desc: 'Synchronize your breath with our visual breathing coach. Proven to reduce cortisol, calm your sympathetic nervous system, and restore focus in under 3 minutes.',
            howToUse: [
                'Follow the breathing circle as it expands for inhale, pauses for hold, and contracts for exhale.',
                'Choose between 4-7-8 Deep Relaxation, Box Breathing (4-4-4-4), or Energizing Breath.'
            ]
        },
        {
            selector: 'a[href*="reframe"], a[href*="cbt"]',
            category: 'Cognitive Behavioral Therapy',
            title: 'CBT Thought Reframer',
            subtitle: 'Challenging Negative Distortions',
            desc: 'Transform catastrophizing, all-or-nothing thinking, and self-criticism into balanced, objective, and compassionate perspectives.',
            howToUse: [
                'Write down a thought that is causing you stress or self-doubt.',
                'Follow the guided cognitive restructuring prompts to find balanced alternative viewpoints.'
            ]
        },
        {
            selector: 'a[href*="truevent"]',
            category: 'Expressive Writing',
            title: 'TrueVent Confidential Journaling',
            subtitle: 'Encrypted Reflective Space',
            desc: 'Express your feelings without reservation. TrueVent provides therapeutic writing prompts and sentiment tracking while guaranteeing complete end-to-end privacy.',
            howToUse: [
                'Choose a daily reflection prompt or free-write.',
                'Your journal entries help update your personal Wellbeing Passport without exposing sensitive text.'
            ]
        },
        {
            selector: 'a[href*="ambient"]',
            category: 'Sound Therapy',
            title: 'Multi-Track Ambient Soundscape Mixer',
            subtitle: 'Curated Natural Acoustics',
            desc: 'Blend gentle rain, ocean waves, crackling fireplace, and binaural soundscapes to create your ideal acoustic environment for focus, deep rest, or sleep.',
            howToUse: [
                'Use individual volume sliders to customize your own audio mix.',
                'Audio continues playing in the background while you write or relax.'
            ]
        }
    ],

    'community.php': [
        {
            selector: 'section:has(button[onclick*="openBefrienderSelectionModal"]), section:has(button:contains("Find a Befriender"))',
            category: 'Empathetic Connection',
            title: 'Find a Befriender: AI Companion or Human Befriender',
            subtitle: 'Choice-Driven Companionship',
            desc: 'Never face life’s challenges in isolation. Choose between an immediate 24/7 AI Companion (Maya or Alex) or match with an accredited Human Befriender for structured 1-on-1 sessions.',
            howToUse: [
                'Click "Find a Befriender" to open the companion selection menu.',
                'Choose "AI Companion" for an instant, empathetic voice conversation.',
                'Choose "Human Befriender" to schedule a 1-on-1 meetup after participating in a Circle.'
            ]
        },
        {
            selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4, section:has(button[onclick*="openVideoConference"])',
            category: 'Live Support Circles',
            title: 'Live Moderated Support Circles & Video Rooms',
            subtitle: 'Secure Peer-to-Peer Video Chat',
            desc: 'Join moderated live support circles tailored to specific journeys: Caregivers, Workplace Stress & Burnout, Anxiety, and Loneliness.',
            howToUse: [
                'Click "Join Video Chat" on any circle card to enter an instant, encrypted browser video room.',
                'Facilitated by certified wellness champions with strict community safety guidelines.'
            ]
        },
        {
            selector: '.md\\:col-span-1:has(h3), div:has(h3:contains("My Support Circles"))',
            category: 'Circle Discovery',
            title: 'Support Circles Directory & Previews',
            subtitle: 'Preview & Join Circles',
            desc: 'Explore thematic discussion channels. Each circle features a dedicated "Preview" button to read guidelines, topic focus, and member counts before joining.',
            howToUse: [
                'Click "Preview" beside any circle to view its mission statement and norms.',
                'Click "Join" to instantly become a member while remaining smoothly on the Community page.'
            ]
        },
        {
            selector: '.md\\:col-span-2, form:has(textarea[name="content"]), div:has(h3:contains("Share"))',
            category: 'Community Forum',
            title: 'Community Discussion Feed & Anonymous Sharing',
            subtitle: 'Safe, Moderated Forum',
            desc: 'Post questions, share reflections, and exchange encouragement with peers. Offers full support for anonymous posting for sensitive discussions.',
            howToUse: [
                'Type your thought in the message composer.',
                'Toggle "Post Anonymously" if you prefer complete confidentiality.',
                'Send hearts to uplift fellow members.'
            ]
        }
    ],

    'tet_academy.php': [
        {
            selector: '.grid.grid-cols-1.md\\:grid-cols-2, section:has(button:contains("Start Lesson")), .grid:has(.bg-white)',
            category: 'Psychoeducation',
            title: 'Bite-Sized Mental Health Masterclasses',
            subtitle: '5-Minute Evidence-Based Modules',
            desc: 'Concise, highly practical lessons covering Understanding Anxiety, The Science of Sleep, Healthy Boundaries, and Emotional Regulation.',
            howToUse: [
                'Click "Start Lesson" to open the interactive lesson player.',
                'Track your learning progress bar at the top of your screen as you complete modules.'
            ]
        },
        {
            selector: 'nav.hidden.md\\:flex, header',
            category: 'Continuous Care',
            title: 'Integrated Learning Center',
            subtitle: 'Theory Meets Practical Application',
            desc: 'Everything you learn in the Academy links directly to practical tools in MindBloom and peer support in Community Circles.',
            howToUse: [
                'Switch back to MindBloom to practice the techniques learned in the lesson.'
            ]
        }
    ],

    'ai_befriender.php': [
        {
            selector: '#character-switcher, div:has(button:contains("Maya")), div:has(button:contains("Alex"))',
            category: 'Companion Personas',
            title: 'Choose Your Companion: Maya 👩 or Alex 👨',
            subtitle: 'Dual Empathetic Characters',
            desc: 'Maya provides warm, reflective, and nurturing validation. Alex provides calm, grounded, and practical encouragement. Both characters offer empathetic listening.',
            howToUse: [
                'Click either Maya or Alex to switch characters at any time during your conversation.',
                'Each character maintains their own distinct voice, personality, and approach.'
            ]
        },
        {
            selector: '#voice-select, select[id*="voice"], div:has(label:contains("Voice"))',
            category: 'Natural Speech',
            title: 'Gender-Specific Natural Voice Selector',
            subtitle: 'Filtered Realistic Voices',
            desc: 'Natural text-to-speech voices with gender filtering: only female voices appear for Maya, and only male voices appear for Alex.',
            howToUse: [
                'Choose your preferred voice from the dropdown.',
                'Your chosen voice is remembered per account so your companion always sounds familiar.'
            ]
        },
        {
            selector: '#auto-submit-toggle, div:has(#auto-submit-toggle), label:has(#auto-submit-toggle)',
            category: 'Hands-Free Dialogue',
            title: 'Auto-Submit & Voice-to-Text Loop',
            subtitle: 'Seamless Hands-Free Conversation',
            desc: 'Enables a true spoken dialogue loop. Speak your mind, the speech is transcribed and submitted automatically, the AI reads its response aloud, and microphone listening restarts automatically.',
            howToUse: [
                'Turn on "Auto Submit Speech" and "Auto Read Responses" for a fluid conversation.',
                'Click the microphone button to start or pause listening.'
            ]
        },
        {
            selector: '.bg-brand-bg\\/60:has(i.fa-brain), div:has(h4:contains("Learns"))',
            category: 'Isolated Memory',
            title: 'Per-Account AI Learning & Memory Engine',
            subtitle: 'Isolated Privacy Per User',
            desc: 'The AI companion learns from your daily check-ins, Wellbeing Passport goals, and previous conversations to provide tailored advice that remembers your background.',
            howToUse: [
                'Even if multiple users share the same system or subscription, all memories and chat histories remain 100% isolated per user.'
            ]
        }
    ],

    'wellbeing_passport.php': [
        {
            selector: '.grid.grid-cols-1.md\\:grid-cols-2:first-child, div:has(h3:contains("Emotional Patterns")), div:has(h3:contains("What Helps Me"))',
            category: 'Self-Understanding',
            title: 'Emotional Patterns & What Helps Me',
            subtitle: 'Positive Longitudinal Insights',
            desc: 'Synthesizes your check-ins and journal entries to discover which days you experience stress and which specific activities (like breathing or outdoor walks) most effectively restore calm.',
            howToUse: [
                'Read your personalized patterns to understand your personal triggers.',
                'Presented positively to celebrate your self-awareness.'
            ]
        },
        {
            selector: '#challenges, div:has(h3:contains("Challenges")), .grid:has(button[onclick*="openChallengeModal"])',
            category: 'Structured Challenges',
            title: '7-Day to 30-Day Guided Challenges',
            subtitle: 'Step-by-Step Daily Programs',
            desc: 'Structured resilience programs including the 7-Day Calm Mind, 7 Days of Better Sleep, 14 Days of Self-Confidence, and 21 Days of Reducing Overthinking.',
            howToUse: [
                'Click any challenge card to view the 7-day breakdown modal.',
                'Click "Start Challenge" to track your daily progress and earn passport badges.'
            ]
        }
    ],

    'subscription.php': [
        {
            selector: '.grid.grid-cols-1.md\\:grid-cols-3, div:has(h3:contains("Free Starter"))',
            category: 'Plan Management',
            title: 'Platform Subscription Tiers',
            subtitle: 'Free Starter, Professional & Corporate',
            desc: 'Explore the tiers designed for individual self-care, clinical support, and enterprise workplace teams.',
            howToUse: [
                'Compare features and click "Upgrade" on any plan to test the simulated checkout modal.',
                'All features are currently unlocked for testing in preview mode.'
            ]
        },
        {
            selector: 'table:has(th:contains("Invoice")), div:has(h3:contains("Invoice"))',
            category: 'Billing Auditing',
            title: 'Digital Invoices & Billing History',
            subtitle: 'Instant Receipts & Records',
            desc: 'Every plan upgrade generates a timestamped digital invoice for personal and corporate expense reporting.',
            howToUse: [
                'Review payment dates, plan amounts, and payment status.'
            ]
        }
    ],

    'corporate_portal.php': [
        {
            selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4, div:has(h3:contains("Total Staff"))',
            category: 'HR Analytics',
            title: 'Workforce Wellness KPIs',
            subtitle: 'Anonymized Team Metrics',
            desc: 'High-level organizational health indicators: Total Staff Enrolled, Active Monthly Participation, Remaining Session Credits, and Collective Wellbeing Scores.',
            howToUse: [
                'View aggregated metrics that protect individual employee confidentiality.',
                'Identify organizational trends like caregiver burnout or workplace stress.'
            ]
        },
        {
            selector: 'table:has(th:contains("Email")), form:has(input[name="email"])',
            category: 'Employee Onboarding',
            title: 'Staff Roster & Invites',
            subtitle: 'Company-Sponsored Benefits',
            desc: 'Invite team members via their work email to grant company-sponsored access to Tet Wellbeing.',
            howToUse: [
                'Enter employee email addresses to send instant activation invites.',
                'Manage active, pending, and removed staff statuses with one click.'
            ]
        }
    ],

    'admin_dashboard.php': [
        {
            selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4, div:has(h3:contains("Registered Clients"))',
            category: 'Platform Operations',
            title: 'Administrative Overview & Platform Health',
            subtitle: 'Real-Time User & Specialist Auditing',
            desc: 'Monitor registered clients, specialist approval queues, suspended accounts, and escrow holdings.',
            howToUse: [
                'Provides high-level supervision of platform activity and clinical safety states.'
            ]
        },
        {
            selector: 'div:has(h3:contains("Homepage Inquiries")), div:has(label:contains("Destination Email Address"))',
            category: 'Contact Routing',
            title: 'Homepage Inquiries & Email Configuration',
            subtitle: 'Configurable Routing & Inbox',
            desc: 'Questions submitted through the homepage "Have Questions? Reach Out" form are routed directly to the admin email configured here.',
            howToUse: [
                'Update the destination email anytime (defaults to tetwellbeinggroup@gmail.com).',
                'View submitted inquiries in the inbox table and click "Reply" to respond via email.'
            ]
        },
        {
            selector: 'div:has(h3:contains("Specialist Approval")), table:has(th:contains("Specialist"))',
            category: 'Clinical Credentialing',
            title: 'Specialist Credential Verification',
            subtitle: 'Vetting Teletherapists & Befrienders',
            desc: 'Review therapist medical license numbers, bios, and hourly rates before granting approval to practice.',
            howToUse: [
                'Click "Approve" to activate a specialist or manage credentials.'
            ]
        }
    ]
};

/**
 * Intelligent feature detector for pages without explicit static definitions
 */
function getActiveGuideSteps() {
    const pageName = window.location.pathname.split('/').pop() || 'dashboard.php';
    let steps = pageGuideDefinitions[pageName];

    // Check alias mappings
    if (!steps) {
        if (pageName.includes('ai_companion') || pageName.includes('befriender')) {
            steps = pageGuideDefinitions['ai_befriender.php'];
        } else if (pageName.includes('passport')) {
            steps = pageGuideDefinitions['wellbeing_passport.php'];
        } else if (pageName.includes('academy') || pageName.includes('streaming') || pageName.includes('research')) {
            steps = pageGuideDefinitions['tet_academy.php'];
        } else if (pageName.includes('corporate')) {
            steps = pageGuideDefinitions['corporate_portal.php'];
        } else if (pageName.includes('subscription')) {
            steps = pageGuideDefinitions['subscription.php'];
        } else if (pageName.includes('admin')) {
            steps = pageGuideDefinitions['admin_dashboard.php'];
        } else {
            steps = pageGuideDefinitions['dashboard.php'];
        }
    }

    // Filter down to elements that genuinely exist on the current page DOM
    const visibleSteps = (steps || []).filter(step => {
        try {
            const el = document.querySelector(step.selector);
            return el !== null && el.offsetWidth > 0 && el.offsetHeight > 0;
        } catch (e) {
            return false;
        }
    });

    if (visibleSteps.length > 0) {
        return visibleSteps;
    }

    // Dynamic fallback: detect notable landmark sections on this page
    const dynamicSteps = [];
    
    // Top Nav
    if (document.querySelector('header nav, header')) {
        dynamicSteps.push({
            selector: 'header nav, header',
            category: 'Navigation',
            title: 'Page Header & Primary Navigation',
            subtitle: 'Workspace Header',
            desc: 'Access your profile, switch ecosystem pillars, and trigger immediate emergency crisis support.',
            howToUse: ['Use the top links to switch modules or open your account drawer.']
        });
    }

    // Main Content Cards
    const mainCards = document.querySelectorAll('main > div, main section, main .bg-white');
    if (mainCards.length > 0) {
        dynamicSteps.push({
            selector: 'main > div:first-child, main section:first-child',
            category: 'Active Workspace',
            title: 'Primary Module Workspace',
            subtitle: 'Interactive Tools & Information',
            desc: 'This is the main interactive zone of this feature. Follow on-screen prompts, forms, or media players to engage with the tool.',
            howToUse: ['Interact with the controls, submit forms, or view real-time data visualizations here.']
        });
    }

    // Floating AI Widget
    if (document.querySelector('#ai-widget-toggle, #tet-floating-ai-launcher')) {
        dynamicSteps.push({
            selector: '#ai-widget-toggle, #tet-floating-ai-launcher',
            category: 'Empathetic Companion',
            title: '24/7 AI Companion',
            subtitle: 'Continuous Conversational Support',
            desc: 'Need advice, grounding, or a compassionate sounding board? Click this floating button at any time.',
            howToUse: ['Click to open text or voice conversation with your personalized AI Coach.']
        });
    }

    return dynamicSteps;
}

/**
 * Starts the on-demand section walkthrough
 */
function startSectionTour() {
    activeGuideSteps = getActiveGuideSteps();
    if (!activeGuideSteps || activeGuideSteps.length === 0) {
        alert('Welcome! All features on this page are unlocked and ready for exploration.');
        return;
    }

    currentGuideIndex = 0;
    
    const overlay = document.getElementById('section-guide-overlay');
    overlay.classList.remove('hidden');
    document.body.classList.add('guide-active-body');

    // Attach keyboard listener
    window.addEventListener('keydown', handleGuideKeydown);
    window.addEventListener('resize', updateSpotlightPosition);
    window.addEventListener('scroll', handleGuideScroll, true);

    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        renderGuideStep(currentGuideIndex);
    }, 50);
}

/**
 * Keyboard shortcuts for the guide
 */
function handleGuideKeydown(e) {
    if (e.key === 'Escape') {
        closeSectionGuide();
    } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
        nextGuideStep();
    } else if (e.key === 'ArrowLeft') {
        prevGuideStep();
    }
}

/**
 * Renders the active step, draws the SVG cutout, animates the halo, and docks the tooltip
 */
function renderGuideStep(index) {
    // Clear previous popped elements
    document.querySelectorAll('.guide-target-popped').forEach(el => {
        el.classList.remove('guide-target-popped');
    });

    const step = activeGuideSteps[index];
    if (!step) return;

    const targetEl = document.querySelector(step.selector);
    const card = document.getElementById('section-guide-card');
    const catBadge = document.getElementById('guide-cat-badge');
    const stepBadge = document.getElementById('guide-step-badge');
    const title = document.getElementById('guide-title');
    const subtitle = document.getElementById('guide-subtitle');
    const desc = document.getElementById('guide-desc');
    const howToWorksList = document.getElementById('guide-how-it-works');
    const progressBar = document.getElementById('guide-progress-bar');
    const prevBtn = document.getElementById('guide-prev-btn');
    const nextBtn = document.getElementById('guide-next-btn');
    const stepDots = document.getElementById('guide-step-dots');

    // Update Header Badges & Texts
    catBadge.innerHTML = `<i class="fa-solid fa-sparkles text-[10px]"></i> ${step.category || 'Feature Spotlight'}`;
    stepBadge.textContent = `Part ${index + 1} of ${activeGuideSteps.length}`;
    title.textContent = step.title;
    subtitle.textContent = step.subtitle || 'How this feature works';
    desc.textContent = step.desc;
    progressBar.style.width = `${((index + 1) / activeGuideSteps.length) * 100}%`;

    // Populate How To Use Checklist
    howToWorksList.innerHTML = '';
    const items = step.howToUse || ['Interact directly with this section to explore its capabilities.'];
    items.forEach(item => {
        const li = document.createElement('li');
        li.className = 'flex items-start gap-2';
        li.innerHTML = `<span class="text-brand-sage font-bold shrink-0 text-xs">✓</span><span>${item}</span>`;
        howToWorksList.appendChild(li);
    });

    // Populate Clickable Step Dots
    stepDots.innerHTML = '';
    activeGuideSteps.forEach((_, dotIdx) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.title = `Jump to Step ${dotIdx + 1}`;
        dot.className = `h-2 rounded-full transition-all duration-300 ${dotIdx === index ? 'w-6 bg-brand-sage' : 'w-2 bg-gray-300 hover:bg-gray-400'}`;
        dot.onclick = () => {
            currentGuideIndex = dotIdx;
            renderGuideStep(currentGuideIndex);
        };
        stepDots.appendChild(dot);
    });

    // Button states
    if (index === 0) {
        prevBtn.classList.add('invisible');
    } else {
        prevBtn.classList.remove('invisible');
    }

    if (index === activeGuideSteps.length - 1) {
        nextBtn.innerHTML = `<span>Finish Walkthrough</span> <i class="fa-solid fa-check text-[10px]"></i>`;
        nextBtn.classList.remove('bg-brand-sage');
        nextBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
    } else {
        nextBtn.innerHTML = `<span>Next Feature</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>`;
        nextBtn.classList.add('bg-brand-sage');
        nextBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
    }

    // Spotlight Positioning
    if (targetEl) {
        targetEl.classList.add('guide-target-popped');
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Update cutout and halo after scroll starts/settles
        setTimeout(() => {
            positionSpotlightAndCard(targetEl, card);
        }, 150);
        setTimeout(() => {
            positionSpotlightAndCard(targetEl, card);
        }, 350);
    } else {
        // Fallback center position
        resetSpotlightToCenter();
        card.style.top = '50%';
        card.style.left = '50%';
        card.style.transform = 'translate(-50%, -50%)';
        card.classList.remove('opacity-0', 'scale-95');
        card.classList.add('opacity-100', 'scale-100');
    }
}

/**
 * Calculates bounding rect and locks spotlight cutout, glowing halo, and tooltip card
 */
function positionSpotlightAndCard(targetEl, card) {
    if (!targetEl) return;
    const rect = targetEl.getBoundingClientRect();
    currentSpotlightRect = rect;

    const cutoutHole = document.getElementById('guide-cutout-hole');
    const spotlightFrame = document.getElementById('guide-spotlight-frame');

    // Comfortable padding around highlighted element
    const padding = Math.min(14, Math.max(8, Math.round(rect.height * 0.05)));
    const x = Math.max(4, rect.left - padding);
    const y = Math.max(4, rect.top - padding);
    const w = Math.min(window.innerWidth - x - 8, rect.width + (padding * 2));
    const h = rect.height + (padding * 2);
    const rx = Math.min(24, Math.max(12, Math.round(rect.height / 6)));

    // 1. Update SVG Cutout Hole
    cutoutHole.setAttribute('x', x);
    cutoutHole.setAttribute('y', y);
    cutoutHole.setAttribute('width', Math.max(10, w));
    cutoutHole.setAttribute('height', Math.max(10, h));
    cutoutHole.setAttribute('rx', rx);
    cutoutHole.setAttribute('ry', rx);

    // 2. Update Glowing Halo Frame
    spotlightFrame.style.left = `${x}px`;
    spotlightFrame.style.top = `${y}px`;
    spotlightFrame.style.width = `${w}px`;
    spotlightFrame.style.height = `${h}px`;
    spotlightFrame.style.borderRadius = `${rx}px`;
    spotlightFrame.style.opacity = '1';

    // 3. Smart Tooltip Docking Position
    const cardWidth = Math.min(window.innerWidth - 32, 500);
    card.style.width = `${cardWidth}px`;
    const cardHeight = card.offsetHeight || 380;

    let top = rect.bottom + 20;
    let left = Math.max(16, rect.left + (rect.width / 2) - (cardWidth / 2));

    // If overflowing bottom, dock above target
    if (top + cardHeight > window.innerHeight - 20) {
        const topAbove = rect.top - cardHeight - 20;
        if (topAbove >= 20) {
            top = topAbove;
        } else {
            // If neither top nor bottom fits comfortably, dock beside or center
            if (rect.right + cardWidth + 24 <= window.innerWidth) {
                top = Math.max(20, rect.top);
                left = rect.right + 20;
            } else if (rect.left - cardWidth - 20 >= 0) {
                top = Math.max(20, rect.top);
                left = rect.left - cardWidth - 20;
            } else {
                top = Math.max(20, (window.innerHeight - cardHeight) / 2);
            }
        }
    }

    // Keep within left/right bounds
    if (left + cardWidth > window.innerWidth - 16) {
        left = window.innerWidth - cardWidth - 16;
    }
    if (left < 16) {
        left = 16;
    }

    card.style.transform = 'none';
    card.style.top = `${Math.round(top)}px`;
    card.style.left = `${Math.round(left)}px`;
    card.classList.remove('opacity-0', 'scale-95');
    card.classList.add('opacity-100', 'scale-100');
}

/**
 * Fallback center spotlight if no target element is present
 */
function resetSpotlightToCenter() {
    const cutoutHole = document.getElementById('guide-cutout-hole');
    const spotlightFrame = document.getElementById('guide-spotlight-frame');
    cutoutHole.setAttribute('width', '0');
    cutoutHole.setAttribute('height', '0');
    spotlightFrame.style.opacity = '0';
}

/**
 * Updates spotlight smoothly during window resize or continuous scrolling
 */
function updateSpotlightPosition() {
    if (!activeGuideSteps || activeGuideSteps.length === 0) return;
    const step = activeGuideSteps[currentGuideIndex];
    if (!step) return;
    const targetEl = document.querySelector(step.selector);
    const card = document.getElementById('section-guide-card');
    if (targetEl) {
        positionSpotlightAndCard(targetEl, card);
    }
}

function handleGuideScroll() {
    if (guideScrollTimeout) cancelAnimationFrame(guideScrollTimeout);
    guideScrollTimeout = requestAnimationFrame(updateSpotlightPosition);
}

/**
 * Move to next step
 */
function nextGuideStep() {
    if (currentGuideIndex < activeGuideSteps.length - 1) {
        currentGuideIndex++;
        renderGuideStep(currentGuideIndex);
    } else {
        closeSectionGuide();
    }
}

/**
 * Move to previous step
 */
function prevGuideStep() {
    if (currentGuideIndex > 0) {
        currentGuideIndex--;
        renderGuideStep(currentGuideIndex);
    }
}

/**
 * Closes the guide and restores normal state
 */
function closeSectionGuide() {
    window.removeEventListener('keydown', handleGuideKeydown);
    window.removeEventListener('resize', updateSpotlightPosition);
    window.removeEventListener('scroll', handleGuideScroll, true);
    
    document.querySelectorAll('.guide-target-popped').forEach(el => {
        el.classList.remove('guide-target-popped');
    });
    document.body.classList.remove('guide-active-body');

    const overlay = document.getElementById('section-guide-overlay');
    const card = document.getElementById('section-guide-card');
    const spotlightFrame = document.getElementById('guide-spotlight-frame');

    card.classList.add('opacity-0', 'scale-95');
    spotlightFrame.style.opacity = '0';
    overlay.classList.add('opacity-0');

    setTimeout(() => {
        overlay.classList.add('hidden');
        resetSpotlightToCenter();
    }, 300);
}
</script>
