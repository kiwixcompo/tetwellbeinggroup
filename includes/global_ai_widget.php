<?php
/**
 * Tet Wellbeing Group - Global Floating AI Widget (global_ai_widget.php)
 * Continuous learning per user account and popup-free Puter AI safety bridge.
 */
require_once __DIR__ . '/../AiLearningService.php';

$current_widget_uid = $_SESSION['user_id'] ?? 0;
$current_widget_uname = $_SESSION['user_name'] ?? 'Friend';
$current_widget_context = AiLearningService::buildSystemPromptContext($current_widget_uid);
?>

<!-- Floating Action Button -->
<button id="ai-widget-toggle" onclick="toggleAiWidget()" class="fixed bottom-6 right-6 h-16 w-16 bg-brand-sage hover:bg-brand-sageHover text-white rounded-full shadow-2xl flex items-center justify-center transition-all hover:scale-105 z-50 group" title="TET AI Wellbeing Companion">
    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
    <div class="absolute -top-2 -right-2 h-4 w-4 bg-brand-coral rounded-full border-2 border-brand-bg animate-pulse"></div>
</button>

<!-- Widget Container -->
<div id="ai-widget-container" class="fixed bottom-24 right-6 w-[380px] max-w-[calc(100vw-3rem)] h-[600px] max-h-[70vh] bg-white rounded-3xl shadow-2xl border border-gray-200 z-50 flex flex-col hidden overflow-hidden transition-all duration-300 transform translate-y-4 opacity-0 pointer-events-none">
    
    <!-- Widget Header -->
    <div class="bg-brand-sage text-white p-4 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 bg-white/20 rounded-full flex items-center justify-center">
                <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 12h4l3-9 5 18 3-9h5"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold font-outfit text-sm">TET AI Companion</h3>
                    <span class="px-2 py-0.2 rounded-full bg-white/20 text-[9px] font-extrabold uppercase font-outfit tracking-wider">Basic Tier</span>
                </div>
                <p class="text-[10px] text-brand-sageLight">Always here for you</p>
            </div>
        </div>
        <button onclick="toggleAiWidget()" class="text-brand-sageLight hover:text-white p-1 rounded-full transition-colors">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <!-- Toolbar -->
    <div class="bg-[#F0F5F2] border-b border-gray-100 p-2 flex items-center justify-between shrink-0 text-xs gap-2">
        <div class="flex items-center gap-1.5">
            <button onclick="startNewChat()" class="text-brand-sage font-medium hover:text-brand-sageHover px-2 py-1 rounded hover:bg-brand-sage/10 transition-colors">
                <svg class="h-3 w-3 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>New
            </button>
            <button onclick="toggleChatHistory()" class="text-brand-slate font-medium hover:text-brand-slate/80 px-2 py-1 rounded hover:bg-gray-200 transition-colors">
                <svg class="h-3 w-3 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>History
            </button>
        </div>
        <div class="flex items-center gap-1.5">
            <select id="ai-voice-select" class="text-[10px] bg-white border border-gray-200 rounded px-1 py-1 max-w-[90px] text-gray-600 focus:outline-none">
                <option value="">Voice...</option>
            </select>
            <label class="flex items-center gap-1 cursor-pointer select-none text-[10px] font-bold text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200" title="Auto-submit speech transcription">
                <input type="checkbox" id="ai-widget-auto-submit" class="rounded text-brand-sage h-3 w-3">
                <span>Auto-Send</span>
            </label>
            <label class="flex items-center gap-1 cursor-pointer select-none text-[10px] font-bold text-gray-500 bg-white px-1.5 py-0.5 rounded border border-gray-200" title="Automatically read response aloud">
                <input type="checkbox" id="ai-widget-auto-read" checked class="rounded text-brand-sage h-3 w-3">
                <span>Read</span>
            </label>
        </div>
    </div>

    <!-- Widget Body (Chat) -->
    <div id="ai-widget-chat" class="flex-1 p-4 overflow-y-auto space-y-4 bg-[#FAF9F6]">
        <!-- Welcome Message -->
        <div class="flex items-start gap-2 max-w-[85%]">
            <div class="h-8 w-8 rounded-full bg-brand-sageLight flex items-center justify-center shrink-0">
                <svg class="h-4 w-4 text-brand-sage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M2 12h4l3-9 5 18 3-9h5"/>
                </svg>
            </div>
            <div class="p-3 rounded-2xl rounded-tl-sm bg-white border border-gray-100 shadow-sm text-xs text-brand-slate leading-relaxed">
                Hello! I'm your AI Wellbeing Companion. How are you feeling today?
            </div>
        </div>
    </div>

    <!-- History View (hidden by default) -->
    <div id="ai-widget-history-view" class="flex-1 p-4 overflow-y-auto bg-[#FAF9F6] hidden space-y-2">
        <!-- History items injected here -->
    </div>

    <!-- Widget Footer (Input) -->
    <div id="ai-widget-footer" class="p-3 bg-white border-t border-gray-100 shrink-0">
        <form onsubmit="handleAiWidgetSubmit(event)" class="flex items-center gap-2">
            <button type="button" id="ai-mic-btn" onclick="startVoiceInput()" class="h-11 w-11 shrink-0 rounded-2xl bg-gray-100 text-gray-500 hover:text-brand-sage hover:bg-brand-sage/10 flex items-center justify-center transition-colors focus:outline-none" title="Voice-to-Text Input">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
            </button>
            <div class="relative flex-grow">
                <input type="text" id="ai-widget-input" placeholder="Type or speak a message..." class="w-full pl-4 pr-12 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage text-xs text-brand-slate">
                <button type="submit" id="ai-widget-send-btn" class="absolute right-1.5 top-1.5 h-8 w-8 rounded-xl bg-brand-sage text-white flex items-center justify-center shadow-sm hover:bg-brand-sageHover transition-colors disabled:opacity-50">
                    <svg class="h-4 w-4 translate-x-px translate-y-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://js.puter.com/v2/"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="js/tet_ai_client_helper.js"></script>
<script>
    let aiWidgetOpen = false;
    const widgetContainer = document.getElementById('ai-widget-container');
    const chatBox = document.getElementById('ai-widget-chat');
    const sendBtn = document.getElementById('ai-widget-send-btn');
    const inputField = document.getElementById('ai-widget-input');
    const micBtn = document.getElementById('ai-mic-btn');

    // Initialize Tet AI Client Helper for this specific account
    window.TetAiHelper.init({
        id: <?php echo (int)$current_widget_uid; ?>,
        name: <?php echo json_encode($current_widget_uname); ?>,
        contextPrompt: <?php echo json_encode($current_widget_context); ?>
    });

    const defaultHistory = [
        { role: 'system', content: 'You are a compassionate, empathetic Cognitive Behavioral Therapy (CBT) assistant. Keep responses under 3 sentences. Be very supportive. Never provide medical diagnoses.\n\n' + (window.TetAiHelper.currentUser.contextPrompt || '') }
    ];

    let chatHistory = window.TetAiHelper.getStoredData('ai_history', defaultHistory);
    if (!chatHistory || !chatHistory.length) {
        chatHistory = [...defaultHistory];
    }

    function saveHistory() {
        window.TetAiHelper.setStoredData('ai_history', chatHistory);
    }

    // Function to render history to DOM on load
    function renderHistory() {
        // Clear except welcome message
        const welcomeMsg = chatBox.firstElementChild;
        chatBox.innerHTML = '';
        if (welcomeMsg) chatBox.appendChild(welcomeMsg);

        for (let i = 1; i < chatHistory.length; i++) {
            const msg = chatHistory[i];
            if (msg.role === 'user' || msg.role === 'assistant') {
                appendMessage(msg.content, msg.role === 'user');
            }
        }
    }
    
    // Call render once
    renderHistory();

    // Voice Selection Logic
    const voiceSelect = document.getElementById('ai-voice-select');
    let voices = [];
    
    function populateVoices() {
        if (!('speechSynthesis' in window)) return;
        voices = window.speechSynthesis.getVoices();
        if (voices.length > 0) {
            voiceSelect.innerHTML = '<option value="">Default Voice</option>';
            voices.forEach((voice, index) => {
                const option = document.createElement('option');
                option.textContent = voice.name;
                option.value = index;
                voiceSelect.appendChild(option);
            });
        }
    }
    
    if ('speechSynthesis' in window) {
        populateVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = populateVoices;
        }
    }

    // Session Management Logic
    let allSessions = [];
    try {
        const storedSessions = localStorage.getItem('tet_ai_sessions');
        if (storedSessions) {
            allSessions = JSON.parse(storedSessions);
        }
    } catch(e) { }

    function startNewChat() {
        if (chatHistory.length > 1) { 
            allSessions.unshift({
                id: Date.now(),
                date: new Date().toLocaleString(),
                snippet: chatHistory[1].content.substring(0, 40) + '...',
                history: [...chatHistory]
            });
            localStorage.setItem('tet_ai_sessions', JSON.stringify(allSessions));
        }
        chatHistory = [...defaultHistory];
        saveHistory();
        renderHistory();
        showChatView();
    }

    function toggleChatHistory() {
        const chatView = document.getElementById('ai-widget-chat');
        const historyView = document.getElementById('ai-widget-history-view');
        const footer = document.getElementById('ai-widget-footer');
        
        if (historyView.classList.contains('hidden')) {
            chatView.classList.add('hidden');
            footer.classList.add('hidden');
            historyView.classList.remove('hidden');
            
            historyView.innerHTML = '<h4 class="font-bold text-sm text-brand-slate mb-3">Previous Conversations</h4>';
            if (allSessions.length === 0) {
                historyView.innerHTML += '<p class="text-xs text-gray-500">No previous conversations.</p>';
            } else {
                allSessions.forEach(session => {
                    historyView.innerHTML += `
                        <div onclick="loadSession(${session.id})" class="p-3 bg-white border border-gray-200 rounded-xl cursor-pointer hover:border-brand-sage hover:shadow-sm transition-all">
                            <p class="text-xs text-gray-400 mb-1">${session.date}</p>
                            <p class="text-sm text-brand-slate font-medium truncate">${session.snippet}</p>
                        </div>
                    `;
                });
            }
        } else {
            showChatView();
        }
    }
    
    function showChatView() {
        document.getElementById('ai-widget-chat').classList.remove('hidden');
        document.getElementById('ai-widget-footer').classList.remove('hidden');
        document.getElementById('ai-widget-history-view').classList.add('hidden');
    }

    function loadSession(id) {
        const session = allSessions.find(s => s.id === id);
        if (session) {
            chatHistory = [...session.history];
            saveHistory();
            renderHistory();
            showChatView();
        }
    }

    function saveHistory() {
        localStorage.setItem('tet_ai_history', JSON.stringify(chatHistory));
    }

    function toggleAiWidget() {
        aiWidgetOpen = !aiWidgetOpen;
        if (aiWidgetOpen) {
            widgetContainer.classList.remove('hidden');
            // Slight delay for animation
            setTimeout(() => {
                widgetContainer.classList.remove('translate-y-4', 'opacity-0', 'pointer-events-none');
                inputField.focus();
            }, 10);
        } else {
            widgetContainer.classList.add('translate-y-4', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                widgetContainer.classList.add('hidden');
            }, 300);
        }
    }

    function appendMessage(text, isUser = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `flex items-start gap-2 max-w-[85%] ${isUser ? 'ml-auto flex-row-reverse' : ''}`;
        
        let avatar = '';
        if (isUser) {
            avatar = `<div class="h-8 w-8 rounded-full bg-brand-sky flex items-center justify-center shrink-0 shadow-sm text-xs font-bold text-white uppercase">U</div>`;
        } else {
            avatar = `<div class="h-8 w-8 rounded-full bg-brand-sageLight flex items-center justify-center shrink-0">
                <svg class="h-4 w-4 text-brand-sage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M2 12h4l3-9 5 18 3-9h5"/>
                </svg>
            </div>`;
        }

        const bubbleClass = isUser 
            ? 'bg-brand-sage text-white rounded-tr-sm shadow-sm' 
            : 'bg-white border border-gray-100 shadow-sm text-brand-slate rounded-tl-sm';

        let contentHtml = '';
        if (isUser) {
            contentHtml = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
        } else {
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                marked.setOptions({ breaks: true, gfm: true });
                contentHtml = marked.parse(text);
            } else {
                contentHtml = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/^- (.*$)/gim, '<li>$1</li>').replace(/\n/g, '<br>');
            }
        }

        msgDiv.innerHTML = `
            ${avatar}
            <div class="p-3 rounded-2xl ${bubbleClass} text-xs leading-relaxed max-w-full overflow-hidden">
                ${contentHtml}
            </div>
        `;
        
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function handleAiWidgetSubmit(e) {
        if(e) e.preventDefault();
        const text = inputField.value.trim();
        if (!text) return;

        // Add user message
        appendMessage(text, true);
        inputField.value = '';
        sendBtn.disabled = true;

        // Add to history
        chatHistory.push({ role: 'user', content: text });
        saveHistory();

        // Show typing indicator
        const typingId = 'typing-' + Date.now();
        const typingDiv = document.createElement('div');
        typingDiv.id = typingId;
        typingDiv.className = "flex items-start gap-2 max-w-[85%]";
        typingDiv.innerHTML = `
            <div class="h-8 w-8 rounded-full bg-brand-sageLight flex items-center justify-center shrink-0">
                <svg class="h-4 w-4 text-brand-sage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M2 12h4l3-9 5 18 3-9h5"/>
                </svg>
            </div>
            <div class="p-3 rounded-2xl rounded-tl-sm bg-white border border-gray-100 shadow-sm text-xs text-gray-400 flex gap-1">
                <div class="h-1.5 w-1.5 bg-gray-300 rounded-full animate-bounce"></div>
                <div class="h-1.5 w-1.5 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="h-1.5 w-1.5 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
        `;
        chatBox.appendChild(typingDiv);
        chatBox.scrollTop = chatBox.scrollHeight;

        try {
            // Call AI safely without triggering Puter auth popups
            let promptText = chatHistory.map(msg => msg.role.toUpperCase() + ": " + msg.content).join("\n\n") + "\n\nASSISTANT: ";
            
            const aiText = await window.TetAiHelper.safeChat(promptText, async () => {
                const uName = window.TetAiHelper.currentUser.name || 'Friend';
                return `Hello ${uName}, I hear you. Even in busy moments, giving yourself a pause and focusing on what is within your control right now brings a lot of relief. How are you feeling right now?`;
            });
            
            // Remove typing indicator
            document.getElementById(typingId)?.remove();
            
            // Append AI response
            appendMessage(aiText, false);
            chatHistory.push({ role: 'assistant', content: aiText });
            
            if (chatHistory.length > 21) {
                chatHistory.splice(1, 2);
            }
            saveHistory();

            // Attempt to speak (Text-to-Speech)
            const autoReadCheckbox = document.getElementById('ai-widget-auto-read');
            const shouldAutoRead = !autoReadCheckbox || autoReadCheckbox.checked;
            const autoSubmitCheckbox = document.getElementById('ai-widget-auto-submit');

            if (shouldAutoRead && 'speechSynthesis' in window) {
                const cleanSpeechText = aiText.replace(/[*_#`~>-]/g, ' ').replace(/\s+/g, ' ').trim();
                const utterance = new SpeechSynthesisUtterance(cleanSpeechText);
                utterance.rate = 0.95;
                utterance.pitch = 1.0;
                if (voiceSelect && voiceSelect.value !== "" && voices[voiceSelect.value]) {
                    utterance.voice = voices[voiceSelect.value];
                }
                
                // If auto-submit is enabled, restart speech recognition when TTS completes
                if (autoSubmitCheckbox && autoSubmitCheckbox.checked) {
                    utterance.onend = () => {
                        setTimeout(() => {
                            startVoiceInput();
                        }, 500);
                    };
                }

                window.speechSynthesis.speak(utterance);
            } else if (autoSubmitCheckbox && autoSubmitCheckbox.checked) {
                setTimeout(() => {
                    startVoiceInput();
                }, 500);
            }

        } catch (err) {
            document.getElementById(typingId)?.remove();
            appendMessage("I'm sorry, I'm having trouble connecting right now. Please try again in a moment.", false);
        } finally {
            sendBtn.disabled = false;
            inputField.focus();
        }
    }

    // Speech to Text (Microphone)
    let recognition;
    let widgetSilenceTimer = null;
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        recognition = new SpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = true;

        recognition.onstart = function() {
            micBtn.classList.add('bg-brand-coral', 'text-white');
            micBtn.classList.remove('bg-gray-100', 'text-gray-500');
            inputField.placeholder = "Listening... Speak now.";
        };

        recognition.onresult = function(event) {
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
                inputField.value = spoken;
            }

            const autoCheckbox = document.getElementById('ai-widget-auto-submit');
            if (autoCheckbox && autoCheckbox.checked && spoken) {
                clearTimeout(widgetSilenceTimer);
                widgetSilenceTimer = setTimeout(() => {
                    if (inputField.value.trim().length > 0) {
                        try { recognition.stop(); } catch(e){}
                        handleAiWidgetSubmit();
                    }
                }, 1400);
            }
        };

        recognition.onend = function() {
            micBtn.classList.remove('bg-brand-coral', 'text-white');
            micBtn.classList.add('bg-gray-100', 'text-gray-500');
            inputField.placeholder = "Type or speak a message...";
        };

        recognition.onerror = function(event) {
            console.error("Speech recognition error", event.error);
        };
    } else {
        micBtn.style.display = 'none';
    }

    function startVoiceInput() {
        if (recognition) {
            try {
                recognition.start();
            } catch(e){}
        } else {
            alert("Speech recognition is not supported in this browser.");
        }
    }
</script>
