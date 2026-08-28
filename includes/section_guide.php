<?php
/**
 * Tet Wellbeing Group - Section-by-Section Interactive Guide & Tour Component
 * Highlights sections with spotlight focus, overview tips, step tracker, and next/prev controls.
 */
?>
<!-- SECTION GUIDE / WALKTHROUGH OVERLAY COMPONENT -->
<div id="section-guide-overlay" class="fixed inset-0 z-[100] hidden pointer-events-none transition-opacity duration-300 opacity-0">
    <!-- Darkened backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px] pointer-events-auto" onclick="closeSectionGuide()"></div>
    
    <!-- Floating Tip Card -->
    <div id="section-guide-card" class="fixed z-[102] pointer-events-auto bg-white rounded-3xl p-6 shadow-2xl border border-brand-sage/20 max-w-md w-[calc(100vw-2rem)] transition-all duration-300 transform scale-95 opacity-0">
        <!-- Header -->
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-sage text-white text-xs font-bold font-outfit shadow-sm" id="guide-step-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </span>
                <span id="guide-step-badge" class="px-2.5 py-0.5 rounded-full bg-brand-sageLight text-brand-sage text-xs font-bold uppercase tracking-wider font-outfit">
                    Part 1 of 5
                </span>
            </div>
            <button onclick="closeSectionGuide()" class="text-gray-400 hover:text-brand-slate p-1.5 rounded-full hover:bg-gray-100 transition-colors" title="Close Guide">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mb-4">
            <div id="guide-progress-bar" class="bg-brand-sage h-full transition-all duration-300 rounded-full" style="width: 20%;"></div>
        </div>

        <!-- Content -->
        <h3 id="guide-title" class="text-lg font-bold font-outfit text-brand-slate mb-2">Section Title</h3>
        <p id="guide-desc" class="text-sm text-gray-600 leading-relaxed mb-6">Brief overview explaining this part.</p>

        <!-- Footer / Navigation Controls -->
        <div class="flex items-center justify-between gap-2 pt-3 border-t border-gray-100">
            <button id="guide-prev-btn" onclick="prevGuideStep()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:text-brand-slate hover:bg-gray-100 transition-all">
                &larr; Previous
            </button>
            <div class="flex items-center gap-2">
                <button onclick="closeSectionGuide()" class="px-3 py-2 rounded-xl text-xs font-semibold text-gray-400 hover:text-gray-600 transition-all">
                    Skip
                </button>
                <button id="guide-next-btn" onclick="nextGuideStep()" class="px-5 py-2 rounded-xl bg-brand-sage text-white text-xs font-bold hover:bg-brand-sageHover transition-all shadow-md active:scale-95 flex items-center gap-1.5">
                    <span>Next</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Highlight spotlight styling */
.guide-highlight-active {
    position: relative !important;
    z-index: 101 !important;
    outline: 4px solid #5E8C71 !important;
    outline-offset: 4px !important;
    box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.55), 0 20px 40px -15px rgba(0, 0, 0, 0.3) !important;
    border-radius: 1.5rem !important;
    transition: outline 0.2s ease, box-shadow 0.3s ease !important;
}
</style>

<script>
let currentGuideIndex = 0;
let activeGuideSteps = [];

// Guide configurations for different pages
const pageGuideDefinitions = {
    'dashboard.php': [
        {
            selector: 'nav.hidden.md\\:flex',
            title: '4-Pillar Global Navigation',
            desc: 'Seamlessly switch across the four core ecosystem areas: My TET, MindBloom, Community & Care, and Learning Center.'
        },
        {
            selector: '.mb-8.relative.w-full.h-\\[250px\\]',
            title: 'Personal Command Center & Navigator',
            desc: 'View your daily progress, active streak, and recommended next steps tailored to your wellbeing profile.'
        },
        {
            selector: 'section:has(#checkin-form)',
            title: 'Daily Mood & Emotional Check-in',
            desc: 'Quickly log how you are feeling each day. Your check-ins power early wellness indicators and personalized tools.'
        },
        {
            selector: '#telemetry-analytics, section:has(.h-48), .lg\\:col-span-5 section:first-child',
            title: 'Emotional Health Index & Telemetry',
            desc: 'Real-time telemetry and clinical indicators tracking resilience, stress patterns, and biometric trends over time.'
        },
        {
            selector: '#ai-widget-toggle',
            title: '24/7 AI Companion Widget',
            desc: 'Click this floating button anytime to chat with your empathetic AI Coach, practice CBT, or get instant support.'
        }
    ],
    'mindbloom.php': [
        {
            selector: 'nav.hidden.md\\:flex',
            title: 'MindBloom Clinical Hub',
            desc: 'Your dedicated self-care and mental health toolkit for proactive resilience and habit building.'
        },
        {
            selector: '.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3',
            title: 'Clinical Tool Suite',
            desc: 'Access the Digital Twin, VR Resilience Centre, Ambient Soundscapes, Breathing Exercises, and CBT Reframer.'
        }
    ],
    'community.php': [
        {
            selector: 'nav.hidden.md\\:flex',
            title: 'Community & Care Hub',
            desc: 'Connect with certified teletherapists, join peer circles, or match with an empathetic befriender.'
        },
        {
            selector: 'section:has(button[onclick*="showToast"]), section:has(a[href*="befriender"])',
            title: 'Befriender & Peer Network',
            desc: 'Request an AI Companion or Human Befriender matched to your shared circles and cultural preferences.'
        }
    ],
    'tet_academy.php': [
        {
            selector: 'nav.hidden.md\\:flex',
            title: 'Learning Center (TET Academy)',
            desc: 'Access psychoeducation masterclasses, mental health streaming media, and published scientific research.'
        }
    ]
};

function getActiveGuideSteps() {
    const pageName = window.location.pathname.split('/').pop() || 'dashboard.php';
    let steps = pageGuideDefinitions[pageName] || pageGuideDefinitions['dashboard.php'];
    
    // Filter steps to only those where selector exists on page
    return steps.filter(step => {
        try {
            return document.querySelector(step.selector) !== null;
        } catch(e) {
            return false;
        }
    });
}

function startSectionTour() {
    activeGuideSteps = getActiveGuideSteps();
    if (!activeGuideSteps || activeGuideSteps.length === 0) {
        // Fallback generic steps
        activeGuideSteps = [
            {
                selector: 'header',
                title: 'Global Navigation',
                desc: 'Explore the 4 pillars of wellbeing and access your profile and settings.'
            },
            {
                selector: 'main',
                title: 'Main Platform Hub',
                desc: 'Access all mental health modules, assessments, streaming audio, and personalized tools.'
            }
        ];
    }
    currentGuideIndex = 0;
    
    const overlay = document.getElementById('section-guide-overlay');
    overlay.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        renderGuideStep(currentGuideIndex);
    }, 50);
}

function renderGuideStep(index) {
    // Clear previous highlight
    document.querySelectorAll('.guide-highlight-active').forEach(el => {
        el.classList.remove('guide-highlight-active');
    });

    const step = activeGuideSteps[index];
    if (!step) return;

    const targetEl = document.querySelector(step.selector);
    const card = document.getElementById('section-guide-card');
    const badge = document.getElementById('guide-step-badge');
    const title = document.getElementById('guide-title');
    const desc = document.getElementById('guide-desc');
    const progressBar = document.getElementById('guide-progress-bar');
    const prevBtn = document.getElementById('guide-prev-btn');
    const nextBtn = document.getElementById('guide-next-btn');

    // Update Card Content
    badge.textContent = `Part ${index + 1} of ${activeGuideSteps.length}`;
    title.textContent = step.title;
    desc.textContent = step.desc;
    progressBar.style.width = `${((index + 1) / activeGuideSteps.length) * 100}%`;

    // Button states
    if (index === 0) {
        prevBtn.classList.add('invisible');
    } else {
        prevBtn.classList.remove('invisible');
    }

    if (index === activeGuideSteps.length - 1) {
        nextBtn.innerHTML = `<span>Finish Tour</span> <i class="fa-solid fa-check text-[10px]"></i>`;
    } else {
        nextBtn.innerHTML = `<span>Next Part</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>`;
    }

    if (targetEl) {
        targetEl.classList.add('guide-highlight-active');
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Position card near target element
        setTimeout(() => {
            const rect = targetEl.getBoundingClientRect();
            const cardHeight = card.offsetHeight || 240;
            const cardWidth = Math.min(window.innerWidth - 32, 420);

            let top = rect.bottom + 20;
            let left = Math.max(16, rect.left + (rect.width / 2) - (cardWidth / 2));

            // Prevent overflowing bottom of viewport
            if (top + cardHeight > window.innerHeight - 20) {
                top = Math.max(20, rect.top - cardHeight - 20);
            }
            // Prevent overflowing right
            if (left + cardWidth > window.innerWidth - 16) {
                left = window.innerWidth - cardWidth - 16;
            }

            card.style.top = `${top}px`;
            card.style.left = `${left}px`;
            card.classList.remove('opacity-0', 'scale-95');
            card.classList.add('opacity-100', 'scale-100');
        }, 150);
    } else {
        // Fallback center position
        card.style.top = '50%';
        card.style.left = '50%';
        card.style.transform = 'translate(-50%, -50%)';
        card.classList.remove('opacity-0', 'scale-95');
        card.classList.add('opacity-100', 'scale-100');
    }
}

function nextGuideStep() {
    if (currentGuideIndex < activeGuideSteps.length - 1) {
        currentGuideIndex++;
        renderGuideStep(currentGuideIndex);
    } else {
        closeSectionGuide();
    }
}

function prevGuideStep() {
    if (currentGuideIndex > 0) {
        currentGuideIndex--;
        renderGuideStep(currentGuideIndex);
    }
}

function closeSectionGuide() {
    document.querySelectorAll('.guide-highlight-active').forEach(el => {
        el.classList.remove('guide-highlight-active');
    });
    const overlay = document.getElementById('section-guide-overlay');
    const card = document.getElementById('section-guide-card');
    card.classList.add('opacity-0', 'scale-95');
    overlay.classList.add('opacity-0');
    setTimeout(() => {
        overlay.classList.add('hidden');
    }, 300);
}
</script>
