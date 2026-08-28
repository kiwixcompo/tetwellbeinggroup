<?php
/**
 * Tet Wellbeing Group - Breathing Exercises (breathing_exercises.php)
 * A visually guided breathing tool offering Box Breathing, 4-7-8, and Energizing patterns.
 */
require_once __DIR__ . '/db.php';

require_once "gating_helper.php";
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guided Breathing | Tet Wellbeing</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',
                            sage: '#5E8C71',
                            sageLight: '#E8EFEA',
                            slate: '#264653',
                            coral: '#E76F51',
                            coralLight: '#FCEBE6',
                            sky: '#8ECAE6',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
        
        .breath-circle {
            transition: transform linear, background-color 0.5s ease;
            transform: scale(0.5); /* Default resting state */
        }
        
        /* Smooth scale animation handled by JS modifying the inline transition duration */
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="min-h-screen text-brand-slate pb-20 flex flex-col">
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <!-- NAV BAR -->
    

    <div class="max-w-7xl mx-auto w-full px-6 mt-8">
        </div>

    <!-- MAIN CONTENT -->
    <main class="flex-grow flex flex-col items-center justify-center max-w-4xl mx-auto px-6 w-full mt-4">
        
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 mb-2">
                <?php echo getPlanBadge('guest'); ?>
            </div>
            <h1 class="text-4xl font-extrabold font-outfit text-brand-slate mb-3">Breathe</h1>
            <p class="text-gray-500 max-w-lg mx-auto">Follow the circle to regulate your nervous system. Inhale as it expands, exhale as it shrinks.</p>
        </div>

        <!-- Mode Selector -->
        <div class="flex flex-wrap justify-center gap-3 mb-16">
            <button class="mode-btn active px-6 py-2.5 rounded-full text-sm font-bold border-2 border-brand-sage bg-brand-sage text-white transition shadow-md" data-mode="box">
                Box Breathing (Focus)
            </button>
            <button class="mode-btn px-6 py-2.5 rounded-full text-sm font-bold border-2 border-gray-200 bg-white text-gray-500 hover:border-brand-sage transition shadow-sm" data-mode="relax">
                4-7-8 (Relaxation)
            </button>
            <button class="mode-btn px-6 py-2.5 rounded-full text-sm font-bold border-2 border-gray-200 bg-white text-gray-500 hover:border-brand-sky transition shadow-sm" data-mode="energize">
                6-2 (Energize)
            </button>
        </div>

        <!-- Animation Area -->
        <div class="relative w-64 h-64 sm:w-80 sm:h-80 flex items-center justify-center mx-auto mb-16">
            <!-- Guide Ring -->
            <div class="absolute inset-0 border-2 border-dashed border-gray-300 rounded-full opacity-50"></div>
            
            <!-- Breathing Circle -->
            <div id="circle" class="breath-circle absolute w-full h-full rounded-full bg-brand-sage shadow-lg flex items-center justify-center">
                <span id="instruction-text" class="font-outfit font-bold text-2xl text-white tracking-wider pointer-events-none drop-shadow-md">READY</span>
            </div>
        </div>

        <!-- Controls -->
        <div class="text-center">
            <button id="start-btn" class="bg-brand-slate hover:bg-gray-800 text-white font-bold py-4 px-12 rounded-full text-lg shadow-xl transition-transform active:scale-95">
                Begin Session
            </button>
        </div>

    </main>

    <script>
        // Breathing Patterns (in milliseconds)
        const patterns = {
            'box': [
                { text: 'INHALE', duration: 4000, scale: 1 },
                { text: 'HOLD', duration: 4000, scale: 1 },
                { text: 'EXHALE', duration: 4000, scale: 0.5 },
                { text: 'HOLD', duration: 4000, scale: 0.5 }
            ],
            'relax': [
                { text: 'INHALE', duration: 4000, scale: 1 },
                { text: 'HOLD', duration: 7000, scale: 1 },
                { text: 'EXHALE', duration: 8000, scale: 0.5 }
            ],
            'energize': [
                { text: 'INHALE', duration: 6000, scale: 1 },
                { text: 'EXHALE', duration: 2000, scale: 0.5 }
            ]
        };

        const colors = {
            'box': '#5E8C71',      // sage
            'relax': '#E76F51',    // coral
            'energize': '#8ECAE6'  // sky
        };

        let currentMode = 'box';
        let isPlaying = false;
        let stepIndex = 0;
        let timeoutId = null;

        const circle = document.getElementById('circle');
        const instructionText = document.getElementById('instruction-text');
        const startBtn = document.getElementById('start-btn');
        const modeBtns = document.querySelectorAll('.mode-btn');

        // Mode Switching
        modeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (isPlaying) stopBreathing();
                
                modeBtns.forEach(b => {
                    b.classList.remove('bg-brand-sage', 'bg-brand-coral', 'bg-brand-sky', 'text-white', 'border-brand-sage', 'border-brand-coral', 'border-brand-sky');
                    b.classList.add('bg-white', 'text-gray-500', 'border-gray-200');
                });

                currentMode = btn.dataset.mode;
                let activeColorClass = '';
                if(currentMode === 'box') activeColorClass = 'brand-sage';
                if(currentMode === 'relax') activeColorClass = 'brand-coral';
                if(currentMode === 'energize') activeColorClass = 'brand-sky';

                btn.classList.remove('bg-white', 'text-gray-500', 'border-gray-200');
                btn.classList.add(`bg-${activeColorClass}`, 'text-white', `border-${activeColorClass}`);
                
                circle.style.backgroundColor = colors[currentMode];
                instructionText.textContent = 'READY';
                circle.style.transitionDuration = '0.5s';
                circle.style.transform = 'scale(0.5)';
            });
        });

        // Breathing Logic
        function runStep() {
            const pattern = patterns[currentMode];
            const step = pattern[stepIndex];

            instructionText.textContent = step.text;
            
            // Set transition duration to match the step duration precisely
            circle.style.transitionDuration = `${step.duration}ms`;
            circle.style.transform = `scale(${step.scale})`;

            // Schedule next step
            timeoutId = setTimeout(() => {
                stepIndex = (stepIndex + 1) % pattern.length;
                if (isPlaying) runStep();
            }, step.duration);
        }

        function startBreathing() {
            isPlaying = true;
            stepIndex = 0;
            startBtn.textContent = 'Stop Session';
            startBtn.classList.replace('bg-brand-slate', 'bg-brand-coral');
            startBtn.classList.replace('hover:bg-gray-800', 'hover:bg-red-700');
            runStep();
        }

        function stopBreathing() {
            isPlaying = false;
            clearTimeout(timeoutId);
            startBtn.textContent = 'Begin Session';
            startBtn.classList.replace('bg-brand-coral', 'bg-brand-slate');
            startBtn.classList.replace('hover:bg-red-700', 'hover:bg-gray-800');
            
            instructionText.textContent = 'READY';
            circle.style.transitionDuration = '1s';
            circle.style.transform = 'scale(0.5)';
        }

        startBtn.addEventListener('click', () => {
            if (isPlaying) {
                stopBreathing();
            } else {
                startBreathing();
            }
        });
    </script>
</body>
</html>
