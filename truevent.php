<?php
session_start();
require_once 'db.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrueVent - TET Wellbeing</title>
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
                            coral: '#E76F51',
                            sky: '#8ECAE6',
                            slate: '#2D3748',
                            darkBg: '#1A202C',
                            darkCard: '#2D3748',
                            darkInput: '#4A5568'
                        }
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #1A202C; font-family: 'Outfit', sans-serif; color: #E2E8F0; }
        .glass-nav { background: rgba(26, 32, 44, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .pulse-recording { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; box-shadow: 0 0 0 0 rgba(231, 111, 81, 0.7); animation: pulse-ring 2s infinite; }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(231, 111, 81, 0); }
            100% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0); }
        }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- DESKTOP NAVIGATION -->
    <?php if (!isset($_GET['embed'])): ?>
<nav class="glass-nav fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center gap-2 cursor-pointer" onclick="window.location.href='dashboard.php'">
                    <span class="font-bold text-white text-xl tracking-tight hidden sm:block">TET Wellbeing</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard.php" class="text-sm font-semibold text-gray-400 hover:text-white transition-colors">&larr; Leave Safe Space</a>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

    <!-- MAIN CONTENT -->
    <main class="flex-grow <?php echo isset($_GET['embed']) ? 'pt-6' : 'pt-28'; ?> pb-20 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto w-full">
        <?php if (($_SESSION['user_subscription_plan'] ?? 'free') === 'free'): ?>
        <div class="fixed inset-0 z-50 bg-brand-slate/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl p-8 max-w-lg w-full text-center shadow-2xl">
                <div class="h-16 w-16 mx-auto bg-brand-sageLight text-brand-sage rounded-full flex items-center justify-center mb-6">
                    <i class="fa-solid fa-lock text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-brand-slate mb-4">Basic Feature</h2>
                <p class="text-gray-600 mb-8">This feature requires a Basic or Premium plan. Upgrade your plan to access this tool.</p>
                <a href="subscription.php" class="inline-block px-8 py-3 bg-brand-sage text-white rounded-2xl font-bold shadow-md hover:bg-brand-sageHover transition-all">Upgrade to Basic</a>
                <a href="dashboard.php" class="block mt-4 text-sm font-bold text-gray-400 hover:text-brand-slate transition-colors">Return to Dashboard</a>
            </div>
        </div>
        <div class="hidden">
        <?php endif; ?>

        
        <div class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-3">TrueVent</h1>
            <p class="text-gray-400 max-w-xl mx-auto">This is a zero-judgment zone. What you type or say here is completely private. Release your frustration and let it go.</p>
        </div>

        <div class="bg-brand-darkCard rounded-3xl p-6 md:p-8 shadow-2xl border border-gray-700 relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-brand-coral opacity-5 rounded-full blur-3xl"></div>
            
            <div class="flex justify-between items-center mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Anonymous Venting Space</span>
                <button id="mic-btn" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-700 hover:bg-gray-600 text-white text-xs font-bold transition-all">
                    <svg class="h-4 w-4 text-brand-coral" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>
                    <span id="mic-text">Start Voice Vent</span>
                </button>
            </div>

            <textarea id="vent-box" rows="8" class="w-full rounded-2xl border border-gray-600 bg-brand-darkInput p-5 text-white placeholder-gray-400 focus:border-brand-coral focus:outline-none focus:ring-1 focus:ring-brand-coral transition-all text-lg mb-6 resize-none" placeholder="I am feeling..."></textarea>
            
            <div class="flex items-center justify-between">
                <p id="status-text" class="text-xs text-gray-500 flex items-center gap-1"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> Stored securely, never shared.</p>
                <button onclick="releaseVent()" class="px-8 py-3 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-sm shadow-md transition-all">Release It</button>
            </div>
        </div>

    
        <?php if (($_SESSION['user_subscription_plan'] ?? 'free') === 'free'): ?>
        </div>
        <?php endif; ?>
</main>

    <!-- SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-500">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative bg-brand-darkCard w-full max-w-sm rounded-3xl p-8 shadow-2xl border border-gray-700 transform scale-95 transition-all duration-500 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-sageLight mb-6">
                <svg class="h-8 w-8 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h3 class="text-xl font-bold font-outfit text-white mb-2">Letting Go</h3>
            <p class="text-sm text-gray-400 mb-8">Your thoughts have been released. Notice how your body feels now.</p>
            <button onclick="window.location.href='dashboard.php'" class="w-full py-3 rounded-xl bg-gray-700 hover:bg-gray-600 text-white font-bold text-sm transition-colors">Return to Dashboard</button>
        </div>
    </div>

    <!-- VOICE SCRIPT -->
    <script>
        const micBtn = document.getElementById('mic-btn');
        const micText = document.getElementById('mic-text');
        const ventBox = document.getElementById('vent-box');
        const statusText = document.getElementById('status-text');
        const successModal = document.getElementById('success-modal');
        let recognition = null;
        let isRecording = false;

        // Initialize Speech Recognition
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = function() {
                isRecording = true;
                micBtn.classList.add('pulse-recording', 'bg-brand-coral');
                micBtn.classList.remove('bg-gray-700');
                micText.innerText = 'Listening...';
                statusText.innerText = 'Listening... Speak your mind.';
            };

            recognition.onresult = function(event) {
                let interimTranscript = '';
                let finalTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                
                if (finalTranscript) {
                    ventBox.value += (ventBox.value ? ' ' : '') + finalTranscript;
                }
            };

            recognition.onerror = function(event) {
                console.error('Speech recognition error', event.error);
                stopRecording();
            };

            recognition.onend = function() {
                if (isRecording) {
                    // Auto-restart if it stopped unexpectedly while meant to be recording
                    recognition.start();
                } else {
                    stopRecording();
                }
            };

            micBtn.addEventListener('click', () => {
                if (isRecording) {
                    isRecording = false;
                    recognition.stop();
                } else {
                    ventBox.value = '';
                    recognition.start();
                }
            });
        } else {
            micBtn.style.display = 'none'; // Hide if unsupported
        }

        function stopRecording() {
            isRecording = false;
            micBtn.classList.remove('pulse-recording', 'bg-brand-coral');
            micBtn.classList.add('bg-gray-700');
            micText.innerText = 'Start Voice Vent';
            statusText.innerHTML = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> Stored securely, never shared.';
        }

        function releaseVent() {
            if (isRecording) {
                isRecording = false;
                recognition.stop();
            }
            
            // Animate release
            ventBox.style.transition = 'all 1s ease-in';
            ventBox.style.opacity = '0';
            ventBox.style.transform = 'translateY(-20px) scale(0.95)';
            ventBox.style.filter = 'blur(10px)';
            
            setTimeout(() => {
                successModal.classList.remove('opacity-0', 'pointer-events-none');
                successModal.querySelector('.relative').classList.remove('scale-95');
                successModal.querySelector('.relative').classList.add('scale-100');
            }, 1000);
        }
    </script>
    
    <?php include 'includes/global_ai_widget.php'; ?>

</body>
</html>
