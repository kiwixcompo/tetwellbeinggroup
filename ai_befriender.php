<?php
/**
 * Tet Wellbeing Group - AI Befriender (ai_befriender.php)
 * A dedicated peer conversation partner with continuous learning per user account.
 * Offers 2 selectable peer characters (Maya & Alex), realistic natural voice TTS,
 * clean formatted markdown AI display, and popup-free Puter AI safety integration.
 */
require_once 'db.php';
require_once 'gating_helper.php';
require_once 'AiLearningService.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Friend';
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_learning_context = AiLearningService::buildSystemPromptContext($user_id);

// AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'get_ai_response') {
        header('Content-Type: application/json');
        $msg = trim($_POST['message'] ?? '');
        $char = trim($_POST['character'] ?? 'maya');
        $reply = AiLearningService::generateContextualResponse($user_id, $msg, $char, 'befriender');
        echo json_encode(['status' => 'success', 'reply' => $reply]);
        exit;
    }
    if ($_POST['action'] === 'learn_interaction') {
        header('Content-Type: application/json');
        $msg = trim($_POST['message'] ?? '');
        AiLearningService::learnFromInteraction($user_id, $msg);
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Befriender – Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Puter.js CDN for AI -->
    <script src="https://js.puter.com/v2/"></script>
    <!-- Marked.js for Clean Markdown Formatting -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- TET AI & User Learning Bridge -->
    <script src="js/tet_ai_client_helper.js"></script>
    
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
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6',
                            inputBg: '#FAF9F6',
                            cardBg: '#FFFFFF',
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        'glow': '0 0 25px rgba(231, 111, 81, 0.2)'
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
        }
        .chat-bubble-ai {
            border-bottom-left-radius: 4px;
        }
        .chat-bubble-user {
            border-bottom-right-radius: 4px;
        }
        /* Rich rendered AI content styling */
        .ai-prose p { margin-bottom: 0.5rem; line-height: 1.6; }
        .ai-prose p:last-child { margin-bottom: 0; }
        .ai-prose ul { list-style-type: disc; padding-left: 1.25rem; margin: 0.5rem 0; }
        .ai-prose ol { list-style-type: decimal; padding-left: 1.25rem; margin: 0.5rem 0; }
        .ai-prose li { margin-bottom: 0.25rem; }
        .ai-prose strong { font-weight: 700; color: #264653; }
        .ai-prose em { font-style: italic; color: #475569; }
        .ai-prose blockquote { border-left: 3px solid #E76F51; padding-left: 0.75rem; margin: 0.5rem 0; font-style: italic; color: #64748B; }
        
        .pulse-recording {
            animation: record-pulse 1.4s infinite;
        }
        @keyframes record-pulse {
            0% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0.7); transform: scale(1); }
            70% { box-shadow: 0 0 0 10px rgba(231, 111, 81, 0); transform: scale(1.06); }
            100% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0); transform: scale(1); }
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <main class="flex-grow mx-auto w-full max-w-5xl px-4 py-6 pb-20">
        
        <!-- Header Banner & Character Switcher -->
        <div class="mb-6 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-2xl">
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full bg-brand-coralLight text-brand-coral font-bold text-xs uppercase tracking-wider font-outfit">AI Befriender Network</span>
                    <?php echo getPlanBadge('basic'); ?>
                </div>
                <h1 class="text-3xl font-extrabold font-outfit text-brand-slate tracking-tight">
                    Chat with <span id="header-char-name" class="text-brand-coral">Maya</span>
                </h1>
                <p id="header-char-desc" class="text-gray-500 text-xs md:text-sm leading-relaxed">
                    A warm, empathetic peer companion who chats naturally with you. Maya shares thoughtful opinions, comforting recommendations, and relatable stories.
                </p>

                <!-- Character Switcher Buttons -->
                <div class="pt-1 flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Choose Character:</span>
                    <button type="button" onclick="selectBefrienderCharacter('maya')" id="btn-char-maya" class="px-3.5 py-1.5 rounded-2xl bg-brand-coral text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 active:scale-95">
                        <span>👩 Maya (Warm & Caring)</span>
                    </button>
                    <button type="button" onclick="selectBefrienderCharacter('alex')" id="btn-char-alex" class="px-3.5 py-1.5 rounded-2xl bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold text-xs transition-all flex items-center gap-2 active:scale-95">
                        <span>👨 Alex (Grounded & Friendly)</span>
                    </button>
                </div>
            </div>
            
            <!-- Active Character Avatar Badge -->
            <div class="relative shrink-0 flex items-center gap-3.5 bg-[#FAF9F6] p-4 rounded-3xl border border-gray-200 shadow-sm">
                <div class="relative">
                    <div id="char-avatar-box" class="h-14 w-14 rounded-2xl bg-gradient-to-tr from-rose-400 to-brand-coral text-white flex items-center justify-center text-2xl font-bold shadow-md">
                        👩
                    </div>
                    <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full bg-emerald-500 border-2 border-white animate-pulse"></span>
                </div>
                <div>
                    <h4 id="char-badge-name" class="font-bold text-sm text-brand-slate font-outfit">Maya (AI Peer Friend)</h4>
                    <span class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Online & Listening
                    </span>
                </div>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card overflow-hidden flex flex-col h-[640px]">
            
            <!-- Chat Toolbar (Voice Selection & Auto-Submit Controls) -->
            <div class="px-6 py-3.5 border-b border-[#EBE8E0] bg-brand-coralLight/20 flex flex-wrap items-center justify-between gap-4">
                
                <!-- Voice Selector -->
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-volume-high text-brand-coral text-xs"></i>
                    <label for="befriender-voice-select" class="text-xs font-bold text-brand-slate font-outfit">Voice:</label>
                    <select id="befriender-voice-select" onchange="onVoiceSelected(this.value)" class="text-xs font-medium rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-brand-slate focus:border-brand-coral focus:outline-none max-w-[200px] sm:max-w-[260px] truncate">
                        <option value="">Loading realistic voices...</option>
                    </select>
                    <button type="button" onclick="testCurrentVoice()" class="p-1.5 rounded-lg text-gray-500 hover:text-brand-coral hover:bg-white text-xs transition-colors" title="Test Voice Output">
                        <i class="fa-solid fa-play"></i>
                    </button>
                </div>

                <!-- Auto-Send & Auto-Read Toggles -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Auto-Send Toggle -->
                    <label class="flex items-center gap-1.5 cursor-pointer select-none bg-white px-2.5 py-1.5 rounded-xl border border-gray-200 text-xs shadow-xs" title="Auto-submit speech transcription after speaking">
                        <input type="checkbox" id="auto-submit-toggle" onchange="toggleAutoSubmit(this.checked)" class="rounded text-brand-coral focus:ring-brand-coral h-3.5 w-3.5">
                        <span class="font-bold text-[11px] text-brand-slate flex items-center gap-1">
                            <i class="fa-solid fa-bolt text-amber-500"></i> Auto-Submit Speech
                        </span>
                    </label>

                    <!-- Auto-Read Response Toggle -->
                    <label class="flex items-center gap-1.5 cursor-pointer select-none bg-white px-2.5 py-1.5 rounded-xl border border-gray-200 text-xs shadow-xs" title="Automatically read aloud the AI's response using Text-to-Speech">
                        <input type="checkbox" id="auto-read-toggle" onchange="toggleAutoRead(this.checked)" class="rounded text-brand-coral focus:ring-brand-coral h-3.5 w-3.5">
                        <span class="font-bold text-[11px] text-brand-slate flex items-center gap-1">
                            <i class="fa-solid fa-volume-high text-brand-coral"></i> Auto-Read Response
                        </span>
                    </label>

                    <button onclick="clearBefrienderChat()" class="px-2.5 py-1.5 rounded-xl border border-gray-200 text-gray-500 hover:text-brand-coral hover:bg-white text-xs font-semibold transition-all">
                        <i class="fa-solid fa-trash-can mr-1"></i> Clear
                    </button>
                    <a href="community.php" class="px-2.5 py-1.5 rounded-xl bg-brand-bg hover:bg-gray-100 text-gray-600 text-xs font-semibold transition-all">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Community
                    </a>
                </div>
            </div>

            <!-- Conversation Log -->
            <div id="befriender-messages" class="flex-grow p-6 overflow-y-auto space-y-4 bg-gradient-to-b from-[#FAF9F6] to-white">
                <!-- Welcome Message dynamically injected -->
            </div>

            <!-- Suggested Conversation Starters -->
            <div class="px-6 py-2 bg-[#FAF9F6] border-t border-gray-100 flex items-center gap-2 overflow-x-auto scrollbar-hide text-xs">
                <span class="text-gray-400 font-bold text-[10px] uppercase tracking-wider shrink-0">Try asking:</span>
                <button onclick="sendStarterPrompt(this.textContent)" class="shrink-0 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:border-brand-coral hover:text-brand-coral transition-all">
                    "I've been feeling a bit overwhelmed lately."
                </button>
                <button onclick="sendStarterPrompt(this.textContent)" class="shrink-0 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:border-brand-coral hover:text-brand-coral transition-all">
                    "What's your personal favorite way to wind down in the evening?"
                </button>
                <button onclick="sendStarterPrompt(this.textContent)" class="shrink-0 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:border-brand-coral hover:text-brand-coral transition-all">
                    "I'm stuck in an overthinking loop today."
                </button>
                <button onclick="sendStarterPrompt(this.textContent)" class="shrink-0 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:border-brand-coral hover:text-brand-coral transition-all">
                    "Recommend a calming book or relaxing activity."
                </button>
            </div>

            <!-- Input Area -->
            <div class="p-4 bg-white border-t border-[#EBE8E0]">
                <form id="befriender-input-form" onsubmit="handleBefrienderSubmit(event)" class="flex items-center gap-3">
                    
                    <!-- Microphone Speech Button -->
                    <button 
                        type="button" 
                        id="mic-btn"
                        onclick="toggleSpeechRecognition()"
                        class="h-12 w-12 rounded-2xl border border-gray-200 bg-[#FAF9F6] text-gray-500 hover:text-brand-coral hover:border-brand-coral transition-all flex items-center justify-center text-lg shrink-0 active:scale-95 shadow-sm"
                        title="Voice Input (Tap to speak)"
                    >
                        <i class="fa-solid fa-microphone"></i>
                    </button>

                    <div class="relative flex-grow">
                        <textarea 
                            id="befriender-chat-input" 
                            rows="1" 
                            placeholder="Type a message or tap the microphone to talk..."
                            class="w-full pl-4 pr-12 py-3 rounded-2xl border border-gray-200 bg-[#FAF9F6] text-sm text-brand-slate placeholder-gray-400 focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20 focus:outline-none transition-all resize-none max-h-32"
                            onkeydown="if(event.key==='Enter' && !event.shiftKey){ event.preventDefault(); handleBefrienderSubmit(event); }"
                        ></textarea>
                    </div>
                    
                    <button 
                        type="submit" 
                        id="befriender-send-btn"
                        class="h-12 w-12 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white flex items-center justify-center text-lg shadow-md transition-all shrink-0 active:scale-95 disabled:opacity-50"
                        title="Send Message"
                    >
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

        </div>

    </main>

    <script>
        const userName = <?php echo json_encode($user_name); ?>;
        const userLearningContext = <?php echo json_encode($user_learning_context); ?>;
        const chatMessagesEl = document.getElementById('befriender-messages');
        const chatInputEl = document.getElementById('befriender-chat-input');
        const sendBtnEl = document.getElementById('befriender-send-btn');
        const micBtnEl = document.getElementById('mic-btn');
        const voiceSelectEl = document.getElementById('befriender-voice-select');
        const autoSubmitToggleEl = document.getElementById('auto-submit-toggle');

        // Initialize Tet AI Client Helper for this specific account
        window.TetAiHelper.init({
            id: <?php echo (int)$user_id; ?>,
            name: <?php echo json_encode($user_name); ?>,
            contextPrompt: userLearningContext
        });

        // Character definitions with injected user learning context
        const characters = {
            maya: {
                id: 'maya',
                name: 'Maya',
                gender: 'female',
                avatar: '👩',
                gradient: 'from-rose-400 to-brand-coral',
                badgeText: 'Maya (AI Peer Friend)',
                desc: 'A warm, empathetic peer companion who chats naturally with you. Maya shares thoughtful opinions, comforting recommendations, and gentle reassurance.',
                greeting: `Hey ${userName}! 🌸 I’m so happy you dropped by. I’m here as a friend whenever you feel like sharing your thoughts, talking through how your day went, or finding ways to feel a little lighter. How are you feeling right now?`,
                systemPrompt: `You are Maya, a warm, compassionate, empathetic, and relatable female AI Befriender at Tet Wellbeing Group.
You are having a supportive 1-on-1 peer conversation with ${userName}.
Unlike a clinical therapist who just asks diagnostic questions, you converse as a genuine, caring peer friend.
Behaviors:
1. Speak warmly with sincere validation and relatable life reflections.
2. Share thoughtful personal opinions and practical, cozy recommendations (e.g. chamomile/peppermint teas, gentle evening strolls, soothing instrumental playlists, comforting books, micro-breaks).
3. Do not just fire questions at the user. Offer your perspective or gentle advice first, then keep the chat flowing naturally.
4. Format your responses cleanly using markdown (bold, bullet points, short paragraphs).

${userLearningContext}`
            },
            alex: {
                id: 'alex',
                name: 'Alex',
                gender: 'male',
                avatar: '👨',
                gradient: 'from-sky-500 to-brand-slate',
                badgeText: 'Alex (AI Peer Friend)',
                desc: 'A grounded, friendly peer companion who chats naturally with you. Alex brings candid perspectives, practical life analogies, and relaxing routine tips.',
                greeting: `Hey ${userName}! ☕ Great to connect with you today. Consider me a relaxed sounding board—whether you want to vent about work, unpack what's on your mind, or just talk through things without any pressure. What’s going on today?`,
                systemPrompt: `You are Alex, a grounded, friendly, relatable, and authentic male AI Befriender at Tet Wellbeing Group.
You are having a supportive 1-on-1 peer conversation with ${userName}.
Unlike a clinical therapist who interrogates with questions, you converse as a true peer companion.
Behaviors:
1. Speak with grounded warmth, relatable humor when appropriate, and thoughtful perspectives.
2. Share practical recommendations (e.g. structured wind-down routines, exercise, audiobooks, habit resets, breathing space).
3. Share your own thoughts and analogies first before gently inviting their thoughts.
4. Format your responses cleanly using markdown (bold, bullet points, short paragraphs).

${userLearningContext}`
            }
        };

        let activeCharacter = 'maya';
        let conversationHistory = [];
        let autoSubmitEnabled = window.TetAiHelper.getStoredData('voice_auto_submit', false);
        let autoReadEnabled = window.TetAiHelper.getStoredData('voice_auto_read', true);
        autoSubmitToggleEl.checked = autoSubmitEnabled;
        const autoReadToggleEl = document.getElementById('auto-read-toggle');
        if (autoReadToggleEl) autoReadToggleEl.checked = autoReadEnabled;

        // Helper to accurately detect voice gender
        function detectVoiceGender(voice) {
            const name = (voice.name || '').toLowerCase();
            
            const femaleKeywords = [
                'female', 'woman', 'girl', 'jenny', 'samantha', 'zira', 'aria', 'karen', 'moira', 
                'victoria', 'fiona', 'tessa', 'sangeeta', 'heera', 'hazel', 'susan', 'cathy', 
                'allison', 'ava', 'amira', 'stephanie', 'hannah', 'mia', 'emma', 'chloe', 'zoe', 
                'sophia', 'olivia', 'lucy', 'alice', 'katya', 'helena', 'monica', 'paulina', 
                'yasmin', 'noora', 'ayumi', 'haruka', 'yuna', 'kyoko', 'joana', 'laura', 'marta', 
                'carmen', 'elena', 'hortense', 'julie', 'marie', 'hedda', 'marlene', 'vicki'
            ];

            const maleKeywords = [
                'male', 'man', 'boy', 'guy', 'daniel', 'david', 'george', 'mark', 'alex', 'rishi', 
                'fred', 'bruce', 'tom', 'ralph', 'oliver', 'william', 'james', 'ryan', 'eric', 
                'stefan', 'thomas', 'arthur', 'cosimo', 'diego', 'jorge', 'juan', 'pablo', 'manuel', 
                'henri', 'paul', 'nicolas', 'hans', 'stefan', 'yannick', 'kazuhiro', 'naoki', 'takumi'
            ];

            for (const kw of femaleKeywords) {
                if (name.includes(kw)) return 'female';
            }
            for (const kw of maleKeywords) {
                if (name.includes(kw)) return 'male';
            }

            if (name.includes('google')) {
                if (name.includes('female')) return 'female';
                if (name.includes('male')) return 'male';
                return 'female';
            }

            return 'unspecified';
        }

        function loadRealisticVoices() {
            if (!('speechSynthesis' in window)) return;
            const voices = window.speechSynthesis.getVoices();
            if (!voices || voices.length === 0) return;

            availableVoices = voices;
            populateFilteredVoices();
        }

        function populateFilteredVoices() {
            if (!availableVoices.length || !voiceSelectEl) return;
            
            const targetGender = characters[activeCharacter].gender; // 'female' or 'male'
            voiceSelectEl.innerHTML = '';

            // Filter voices by active character gender
            let filteredVoices = availableVoices.filter(v => {
                const g = detectVoiceGender(v);
                return g === targetGender;
            });

            // If strict filter yielded empty, fallback to unspecified or language matches
            if (filteredVoices.length === 0) {
                filteredVoices = availableVoices.filter(v => v.lang.startsWith('en'));
            }

            // Sort: Natural/Premium/Google first
            filteredVoices.sort((a, b) => {
                const aNat = a.name.includes('Natural') || a.name.includes('Google') || a.name.includes('Premium');
                const bNat = b.name.includes('Natural') || b.name.includes('Google') || b.name.includes('Premium');
                if (aNat && !bNat) return -1;
                if (!aNat && bNat) return 1;
                return a.name.localeCompare(b.name);
            });

            filteredVoices.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.name;
                opt.textContent = `${v.name} (${v.lang})`;
                voiceSelectEl.appendChild(opt);
            });

            // Select saved or best matching voice for this character
            const savedVoiceName = window.TetAiHelper.getStoredData(`voice_${activeCharacter}`);
            let chosen = filteredVoices.find(v => v.name === savedVoiceName) || filteredVoices[0];

            if (chosen) {
                selectedVoice = chosen;
                voiceSelectEl.value = chosen.name;
            }
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = loadRealisticVoices;
            loadRealisticVoices();
        }

        function onVoiceSelected(voiceName) {
            selectedVoice = availableVoices.find(v => v.name === voiceName) || null;
            if (selectedVoice) {
                window.TetAiHelper.setStoredData(`voice_${activeCharacter}`, selectedVoice.name);
            }
        }

        function testCurrentVoice() {
            const testMsg = activeCharacter === 'maya' ? "Hello! I am Maya, your warm AI companion." : "Hey there, I am Alex, your friendly AI companion.";
            speakText(testMsg);
        }

        function toggleAutoSubmit(isChecked) {
            autoSubmitEnabled = isChecked;
            window.TetAiHelper.setStoredData('voice_auto_submit', isChecked);
        }

        function toggleAutoRead(isChecked) {
            autoReadEnabled = isChecked;
            window.TetAiHelper.setStoredData('voice_auto_read', isChecked);
            if (!isChecked && 'speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        }

        /* Clean Markdown Rendering */
        function formatAiResponseHtml(rawText) {
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                // Configure marked options
                marked.setOptions({
                    breaks: true,
                    gfm: true
                });
                return `<div class="ai-prose">${marked.parse(rawText)}</div>`;
            }
            // Fallback regex parser
            let formatted = rawText
                .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-brand-slate">$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/^- (.*$)/gim, '<li class="ml-4 list-disc">$1</li>')
                .replace(/\n\n/g, '</p><p class="mt-2">');
            return `<div class="ai-prose"><p>${formatted}</p></div>`;
        }

        function selectBefrienderCharacter(charKey) {
            if (!characters[charKey]) return;
            activeCharacter = charKey;
            const char = characters[charKey];

            // Update UI headers
            document.getElementById('header-char-name').textContent = char.name;
            document.getElementById('header-char-desc').textContent = char.desc;
            document.getElementById('char-badge-name').textContent = char.badgeText;
            document.getElementById('char-avatar-box').textContent = char.avatar;
            document.getElementById('char-avatar-box').className = `h-14 w-14 rounded-2xl bg-gradient-to-tr ${char.gradient} text-white flex items-center justify-center text-2xl font-bold shadow-md`;

            // Update Buttons
            const btnMaya = document.getElementById('btn-char-maya');
            const btnAlex = document.getElementById('btn-char-alex');
            if (charKey === 'maya') {
                btnMaya.className = 'px-3.5 py-1.5 rounded-2xl bg-brand-coral text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 active:scale-95';
                btnAlex.className = 'px-3.5 py-1.5 rounded-2xl bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold text-xs transition-all flex items-center gap-2 active:scale-95';
            } else {
                btnAlex.className = 'px-3.5 py-1.5 rounded-2xl bg-brand-slate text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2 active:scale-95';
                btnMaya.className = 'px-3.5 py-1.5 rounded-2xl bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold text-xs transition-all flex items-center gap-2 active:scale-95';
            }

            // Populate filtered voices for this character
            populateFilteredVoices();

            // Reset conversation history for new character
            conversationHistory = [{ role: 'system', content: char.systemPrompt }];
            renderWelcomeMessage();
        }

        function renderWelcomeMessage() {
            const char = characters[activeCharacter];
            chatMessagesEl.innerHTML = `
                <div class="flex items-start gap-3 max-w-[85%]">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-brand-coral text-white text-base shadow-sm font-bold">
                        ${char.avatar}
                    </div>
                    <div class="chat-bubble-ai bg-white rounded-2xl p-4 text-sm text-brand-slate leading-relaxed shadow-soft border border-gray-100 relative group">
                        <p class="font-bold text-brand-coral text-xs mb-1">${char.name}</p>
                        ${formatAiResponseHtml(char.greeting)}
                        <button onclick="speakText(this.dataset.text)" data-text="${escapeHtml(char.greeting)}" class="absolute top-3 right-3 text-gray-300 hover:text-brand-coral transition-colors opacity-0 group-hover:opacity-100" title="Listen">
                            <i class="fa-solid fa-volume-high text-xs"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        function scrollToBottom() {
            chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }

        function speakText(text, onCompleteCallback) {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                // Strip markdown formatting symbols before speaking for clean voice output
                const cleanSpeechText = text.replace(/[*_#`~>-]/g, ' ').replace(/\s+/g, ' ').trim();
                const utterance = new SpeechSynthesisUtterance(cleanSpeechText);
                utterance.rate = 0.98;
                utterance.pitch = activeCharacter === 'maya' ? 1.05 : 0.95;
                if (selectedVoice) {
                    utterance.voice = selectedVoice;
                }
                if (onCompleteCallback) {
                    utterance.onend = onCompleteCallback;
                    utterance.onerror = onCompleteCallback;
                }
                window.speechSynthesis.speak(utterance);
            } else if (onCompleteCallback) {
                onCompleteCallback();
            }
        }

        function appendUserMessage(text) {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-start gap-3 max-w-[85%] ml-auto justify-end';
            wrapper.innerHTML = `
                <div class="chat-bubble-user bg-brand-slate text-white rounded-2xl p-4 text-sm leading-relaxed shadow-soft">
                    <p class="font-bold text-gray-300 text-xs mb-1">You</p>
                    <p class="whitespace-pre-wrap">${escapeHtml(text)}</p>
                </div>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-brand-slate text-white text-sm shadow-sm font-bold">
                    ${userName.charAt(0).toUpperCase()}
                </div>
            `;
            chatMessagesEl.appendChild(wrapper);
            scrollToBottom();
        }

        function appendAiTyping() {
            const id = 'typing-' + Date.now();
            const char = characters[activeCharacter];
            const wrapper = document.createElement('div');
            wrapper.id = id;
            wrapper.className = 'flex items-start gap-3 max-w-[85%]';
            wrapper.innerHTML = `
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-brand-coral text-white text-base shadow-sm font-bold">
                    ${char.avatar}
                </div>
                <div class="chat-bubble-ai bg-white rounded-2xl p-4 text-sm text-brand-slate shadow-soft border border-gray-100 flex items-center gap-2">
                    <span class="text-xs text-gray-400 font-semibold">${char.name} is thinking</span>
                    <span class="flex gap-1">
                        <span class="h-1.5 w-1.5 bg-brand-coral rounded-full animate-bounce"></span>
                        <span class="h-1.5 w-1.5 bg-brand-coral rounded-full animate-bounce [animation-delay:0.2s]"></span>
                        <span class="h-1.5 w-1.5 bg-brand-coral rounded-full animate-bounce [animation-delay:0.4s]"></span>
                    </span>
                </div>
            `;
            chatMessagesEl.appendChild(wrapper);
            scrollToBottom();
            return id;
        }

        function appendAiMessage(rawText) {
            const char = characters[activeCharacter];
            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-start gap-3 max-w-[85%]';
            wrapper.innerHTML = `
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-brand-coral text-white text-base shadow-sm font-bold">
                    ${char.avatar}
                </div>
                <div class="chat-bubble-ai bg-white rounded-2xl p-4 text-sm text-brand-slate leading-relaxed shadow-soft border border-gray-100 relative group w-full">
                    <p class="font-bold text-brand-coral text-xs mb-1">${char.name}</p>
                    ${formatAiResponseHtml(rawText)}
                    <button onclick="speakText(this.dataset.text)" data-text="${escapeHtml(rawText)}" class="absolute top-3 right-3 text-gray-300 hover:text-brand-coral transition-colors opacity-0 group-hover:opacity-100" title="Listen">
                        <i class="fa-solid fa-volume-high text-xs"></i>
                    </button>
                </div>
            `;
            chatMessagesEl.appendChild(wrapper);
            scrollToBottom();
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function sendStarterPrompt(promptText) {
            const cleanText = promptText.replace(/^"|"$/g, '').trim();
            chatInputEl.value = cleanText;
            handleBefrienderSubmit(new Event('submit'));
        }

        function clearBefrienderChat() {
            if (confirm(`Start a fresh conversation with ${characters[activeCharacter].name}?`)) {
                conversationHistory = [conversationHistory[0]];
                renderWelcomeMessage();
            }
        }

        /* Continuous Speech-to-Text Recognition */
        let recognition = null;
        let isListening = false;
        let silenceTimer = null;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRec();
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                isListening = true;
                micBtnEl.classList.add('bg-brand-coral', 'text-white', 'pulse-recording');
                micBtnEl.classList.remove('bg-[#FAF9F6]', 'text-gray-500');
                chatInputEl.placeholder = "Listening... Speak now.";
            };

            recognition.onresult = (event) => {
                let finalTranscript = '';
                let interimTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                const spoken = (finalTranscript || interimTranscript).trim();
                if (spoken) {
                    chatInputEl.value = spoken;
                }

                // If Auto-Submit is enabled, trigger submission after a brief pause
                if (autoSubmitEnabled && spoken) {
                    clearTimeout(silenceTimer);
                    silenceTimer = setTimeout(() => {
                        if (isListening && chatInputEl.value.trim().length > 0) {
                            stopSpeechRecognition();
                            handleBefrienderSubmit(new Event('submit'));
                        }
                    }, 1400);
                }
            };

            recognition.onerror = (e) => {
                console.warn("Speech Rec Error:", e.error);
                stopSpeechRecognition();
            };

            recognition.onend = () => {
                stopSpeechRecognition();
            };
        }

        function toggleSpeechRecognition() {
            if (!recognition) {
                alert("Speech recognition is not supported in this browser. Please use Chrome, Edge, or Safari.");
                return;
            }
            if (isListening) {
                stopSpeechRecognition();
            } else {
                startSpeechRecognition();
            }
        }

        function startSpeechRecognition() {
            if (!recognition || isListening) return;
            try {
                recognition.start();
            } catch(e){}
        }

        function stopSpeechRecognition() {
            isListening = false;
            if (recognition) {
                try { recognition.stop(); } catch(e){}
            }
            micBtnEl.classList.remove('bg-brand-coral', 'text-white', 'pulse-recording');
            micBtnEl.classList.add('bg-[#FAF9F6]', 'text-gray-500');
            chatInputEl.placeholder = "Type a message or tap the microphone to talk...";
        }

        async function handleBefrienderSubmit(e) {
            if (e) e.preventDefault();
            const text = chatInputEl.value.trim();
            if (!text) return;

            chatInputEl.value = '';
            chatInputEl.style.height = 'auto';
            appendUserMessage(text);

            conversationHistory.push({ role: 'user', content: text });

            const typingId = appendAiTyping();
            sendBtnEl.disabled = true;

            try {
                // Call AI safely without triggering Puter auth popups
                const replyText = await window.TetAiHelper.safeChat(conversationHistory, async () => {
                    const fd = new FormData();
                    fd.append('action', 'get_ai_response');
                    fd.append('message', text);
                    fd.append('character', activeCharacter);
                    const res = await fetch('ai_befriender.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    return data.reply;
                });

                // Record interaction to learn from user
                window.TetAiHelper.recordInteraction('ai_befriender.php', text, activeCharacter);

                document.getElementById(typingId)?.remove();
                appendAiMessage(replyText);
                conversationHistory.push({ role: 'assistant', content: replyText });

                // Text-to-Speech playback with continuous conversation loop
                if (autoReadEnabled) {
                    speakText(replyText, () => {
                        // When speech output ends, if auto-submit loop is enabled, reactivate listening!
                        if (autoSubmitEnabled) {
                            setTimeout(() => {
                                startSpeechRecognition();
                            }, 500);
                        }
                    });
                } else if (autoSubmitEnabled) {
                    setTimeout(() => {
                        startSpeechRecognition();
                    }, 500);
                }

            } catch (err) {
                console.error("AI Error:", err);
                document.getElementById(typingId)?.remove();
                const friendlyFallback = `I'm completely with you on this, ${userName}. Even when things feel tangled, taking a gentle breath and tackling just one moment at a time works wonders. What feels most important to talk through?`;
                appendAiMessage(friendlyFallback);
                conversationHistory.push({ role: 'assistant', content: friendlyFallback });
            } finally {
                sendBtnEl.disabled = false;
            }
        }

        // Initialize with default character
        selectBefrienderCharacter('maya');
    </script>
</body>
</html>
