<?php
/**
 * Tet Wellbeing Group - Global Floating AI Widget (global_ai_widget.php)
 * Powered by Puter.js AI
 */
?>

<!-- Floating Action Button -->
<button id="ai-widget-toggle" onclick="toggleAiWidget()" class="fixed bottom-6 right-6 h-16 w-16 bg-brand-sage hover:bg-brand-sageHover text-white rounded-full shadow-2xl flex items-center justify-center transition-all hover:scale-105 z-50 group">
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
                <h3 class="font-bold font-outfit text-sm">TET AI Companion</h3>
                <p class="text-[10px] text-brand-sageLight">Always here for you</p>
            </div>
        </div>
        <button onclick="toggleAiWidget()" class="text-brand-sageLight hover:text-white p-1 rounded-full transition-colors">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
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

    <!-- Widget Footer (Input) -->
    <div class="p-3 bg-white border-t border-gray-100 shrink-0">
        <form onsubmit="handleAiWidgetSubmit(event)" class="relative flex items-center">
            <input type="text" id="ai-widget-input" placeholder="Type a message..." class="w-full pl-4 pr-12 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage text-xs text-brand-slate">
            <button type="submit" id="ai-widget-send-btn" class="absolute right-2 h-8 w-8 rounded-xl bg-brand-sage text-white flex items-center justify-center shadow-sm hover:bg-brand-sageHover transition-colors disabled:opacity-50">
                <svg class="h-4 w-4 translate-x-px translate-y-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
    </div>
</div>

<script src="https://js.puter.com/v2/"></script>
<script>
    let aiWidgetOpen = false;
    const widgetContainer = document.getElementById('ai-widget-container');
    const chatBox = document.getElementById('ai-widget-chat');
    const sendBtn = document.getElementById('ai-widget-send-btn');
    const inputField = document.getElementById('ai-widget-input');

    let chatHistory = [
        { role: 'system', content: 'You are a compassionate, empathetic Cognitive Behavioral Therapy (CBT) assistant. Keep responses under 3 sentences. Be very supportive. Never provide medical diagnoses.' }
    ];

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

        msgDiv.innerHTML = `
            ${avatar}
            <div class="p-3 rounded-2xl ${bubbleClass} text-xs leading-relaxed">
                ${text.replace(/\n/g, '<br>')}
            </div>
        `;
        
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function handleAiWidgetSubmit(e) {
        e.preventDefault();
        const text = inputField.value.trim();
        if (!text) return;

        // Add user message
        appendMessage(text, true);
        inputField.value = '';
        sendBtn.disabled = true;

        // Add to history
        chatHistory.push({ role: 'user', content: text });

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
            // Call Puter.js AI
            let promptText = chatHistory.map(msg => msg.role.toUpperCase() + ": " + msg.content).join("\\n\\n") + "\\n\\nASSISTANT: ";
            
            const response = await puter.ai.chat(promptText);
            const aiText = response.message.content.trim();
            
            // Remove typing indicator
            document.getElementById(typingId).remove();
            
            // Append AI response
            appendMessage(aiText, false);
            chatHistory.push({ role: 'assistant', content: aiText });
            
            if (chatHistory.length > 10) {
                chatHistory.splice(1, 2);
            }
        } catch (err) {
            document.getElementById(typingId).remove();
            appendMessage("I'm sorry, I'm having trouble connecting right now. Please try again in a moment.", false);
        } finally {
            sendBtn.disabled = false;
            inputField.focus();
        }
    }
</script>
