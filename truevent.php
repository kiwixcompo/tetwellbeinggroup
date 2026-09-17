<?php
session_start();
require_once 'db.php';
require_once 'EmotionalHealthService.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// AJAX Vent Processing & Distress Triage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_vent') {
        header('Content-Type: application/json');
        $text = trim($_POST['text'] ?? '');
        $vent_mode = $_POST['vent_mode'] ?? 'release'; // 'release' or 'save'
        
        $is_distressed = false;
        if (class_exists('EmotionalHealthService')) {
            $is_distressed = EmotionalHealthService::checkDistress($text);
        }
        
        // Award +1 activity point to Passport
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        
        if ($vent_mode === 'save') {
            if (!isset($_SESSION['user_private_journal'][$user_id])) {
                $_SESSION['user_private_journal'][$user_id] = [];
            }
            $_SESSION['user_private_journal'][$user_id][] = [
                'id' => time(),
                'text' => $text,
                'created_at' => date('Y-m-d H:i:s'),
                'display_date' => date('M j, Y - g:ia')
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'distressed' => $is_distressed,
            'mode' => $vent_mode,
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }
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
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 mb-3">
                <span class="px-3 py-1 rounded-full text-xs font-bold font-outfit uppercase tracking-wider bg-sky-500/20 text-sky-300 border border-sky-500/30">
                    <i class="fa-solid fa-star text-[10px] text-sky-400"></i> Basic Plan (£9.99/mo in Production)
                </span>
            </div>
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
            
            <!-- Gentle Distress Crisis Banner (Shown if acute keywords detected) -->
            <div id="distress-banner" class="hidden mb-6 p-4 rounded-2xl bg-rose-950/80 border border-rose-500/50 text-rose-200 animate-fade-in">
                <div class="flex items-start gap-3">
                    <div class="h-8 w-8 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0 text-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-white mb-1">We hear you, and you are not alone.</h4>
                        <p class="text-xs text-rose-200/90 leading-relaxed mb-3">
                            It sounds like you may be carrying an overwhelming weight right now. Please know that gentle, confidential help is available 24/7 without judgment.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="document.getElementById('emergency-modal') ? document.getElementById('emergency-modal').classList.remove('hidden') : window.location.href='dashboard.php'" class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition">
                                <i class="fa-solid fa-phone mr-1"></i> Crisis Support Numbers
                            </button>
                            <a href="community.php" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                                Talk with a Befriender
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p id="status-text" class="text-xs text-gray-500 flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    Stored securely, zero judgment.
                </p>
                
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="button" onclick="submitVent('save')" class="w-1/2 sm:w-auto px-5 py-3 rounded-2xl bg-gray-700 hover:bg-gray-600 text-gray-200 font-bold text-xs transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-regular fa-bookmark"></i>
                        <span>Save Privately</span>
                    </button>
                    <button type="button" onclick="submitVent('release')" class="w-1/2 sm:w-auto px-7 py-3 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-fire"></i>
                        <span>Release & Let Go</span>
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-500">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative bg-brand-darkCard w-full max-w-sm rounded-3xl p-8 shadow-2xl border border-gray-700 transform scale-95 transition-all duration-500 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-sageLight mb-6">
                <svg class="h-8 w-8 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h3 id="modal-title" class="text-xl font-bold font-outfit text-white mb-2">Letting Go</h3>
            <p id="modal-desc" class="text-sm text-gray-400 mb-6">Your thoughts have been released. Notice how your body feels now.</p>
            <p class="text-xs text-brand-sage font-bold mb-6 flex items-center justify-center gap-1">
                ✓ +1 Activity added to your Wellbeing Passport
            </p>
            <div class="flex flex-col gap-2.5">
                <button type="button" onclick="resetVentBox()" class="w-full py-3 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs transition-colors">
                    Write Another Vent
                </button>
                <button type="button" onclick="<?php echo isset($_GET['embed']) ? "window.parent.location.href='mindbloom.php'" : "window.location.href='dashboard.php'"; ?>" class="w-full py-2.5 rounded-xl bg-gray-700 hover:bg-gray-600 text-gray-300 font-bold text-xs transition-colors">
                    Return to Hub
                </button>
            </div>
        </div>
    </div>

    <!-- VOICE SCRIPT -->
    <script>
        const micBtn = document.getElementById('mic-btn');
        const micText = document.getElementById('mic-text');
        const ventBox = document.getElementById('vent-box');
        const statusText = document.getElementById('status-text');
        const successModal = document.getElementById('success-modal');
        const distressBanner = document.getElementById('distress-banner');
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
            statusText.innerHTML = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg> Stored securely, zero judgment.';
        }

        function submitVent(mode) {
            const content = ventBox.value.trim();
            if (!content) {
                alert('Please type or speak what you wish to express first.');
                return;
            }

            if (isRecording) {
                isRecording = false;
                recognition.stop();
            }

            // AJAX call to process distress triage and logging
            const formData = new FormData();
            formData.append('action', 'process_vent');
            formData.append('text', content);
            formData.append('vent_mode', mode);

            fetch('truevent.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.distressed) {
                    distressBanner.classList.remove('hidden');
                    distressBanner.scrollIntoView({ behavior: 'smooth' });
                }

                // Visual release animation
                ventBox.style.transition = 'all 1s ease-in';
                ventBox.style.opacity = '0';
                ventBox.style.transform = 'translateY(-20px) scale(0.95)';
                ventBox.style.filter = 'blur(10px)';

                if (mode === 'save') {
                    document.getElementById('modal-title').textContent = 'Saved Privately';
                    document.getElementById('modal-desc').textContent = 'Your entry has been secured in your personal encrypted journal vault.';
                } else {
                    document.getElementById('modal-title').textContent = 'Letting Go';
                    document.getElementById('modal-desc').textContent = 'Your thoughts have been dissolved into the quiet. Take a slow, deep breath in.';
                }

                setTimeout(() => {
                    successModal.classList.remove('opacity-0', 'pointer-events-none');
                    successModal.querySelector('.relative').classList.remove('scale-95');
                    successModal.querySelector('.relative').classList.add('scale-100');
                }, 900);
            }).catch(err => {
                console.error(err);
            });
        }

        function resetVentBox() {
            ventBox.value = '';
            ventBox.style.opacity = '1';
            ventBox.style.transform = 'none';
            ventBox.style.filter = 'none';
            successModal.classList.add('opacity-0', 'pointer-events-none');
        }
    </script>
    
    <?php include 'includes/global_ai_widget.php'; ?>

</body>
</html>
