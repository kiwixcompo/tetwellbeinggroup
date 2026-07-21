<!-- Floating AI Companion Widget -->
<div id="ai-widget-btn" class="fixed bottom-6 right-6 h-14 w-14 rounded-full bg-brand-sage shadow-lg flex items-center justify-center cursor-pointer hover:bg-brand-sageHover transition-colors z-50">
    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
</div>

<div id="ai-widget-panel" class="fixed bottom-24 right-6 w-80 md:w-96 bg-white rounded-3xl shadow-2xl border border-gray-100 z-50 flex flex-col hidden overflow-hidden transition-all duration-300 transform scale-95 opacity-0 h-[500px]">
    <!-- Header -->
    <div class="bg-brand-sage p-4 flex justify-between items-center text-white">
        <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-full bg-white/20 flex items-center justify-center">🤖</div>
            <span class="font-bold font-outfit text-sm">TET AI Companion</span>
        </div>
        <button id="ai-widget-close" class="hover:text-gray-200"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
    </div>
    
    <!-- Chat Area -->
    <div id="ai-chat-area" class="flex-grow p-4 overflow-y-auto bg-gray-50 flex flex-col gap-3">
        <!-- Initial Message -->
        <div class="flex gap-2 max-w-[85%]">
            <div class="h-6 w-6 rounded-full bg-brand-sageLight text-brand-sage flex items-center justify-center text-[10px] shrink-0 mt-1">AI</div>
            <div class="bg-white p-3 rounded-2xl rounded-tl-sm border border-gray-100 shadow-sm text-xs text-brand-slate">
                Hello. I am here to listen. How are you feeling today?
            </div>
        </div>
    </div>
    
    <!-- Input -->
    <div class="p-3 bg-white border-t border-gray-100">
        <form id="ai-chat-form" class="flex items-center gap-2">
            <input type="text" id="ai-chat-input" class="flex-grow bg-brand-inputBg rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-sage text-brand-slate" placeholder="Type a message...">
            <button type="submit" class="h-9 w-9 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white flex items-center justify-center shrink-0">
                <svg class="h-4 w-4 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
            </button>
        </form>
    </div>
</div>

<!-- Puter.js integration -->
<script src="https://js.puter.com/v2/"></script>
<script>
    const aiBtn = document.getElementById('ai-widget-btn');
    const aiPanel = document.getElementById('ai-widget-panel');
    const aiClose = document.getElementById('ai-widget-close');
    const aiChatArea = document.getElementById('ai-chat-area');
    const aiChatForm = document.getElementById('ai-chat-form');
    const aiChatInput = document.getElementById('ai-chat-input');

    let chatHistory = [
        { role: 'system', content: 'You are an empathetic, professional AI Cognitive Behavioral Therapy (CBT) Companion. Respond to the patient using compassionate, evidence-based CBT techniques. Keep your response conversational, supportive, and relatively short (2-4 sentences).' }
    ];

    function toggleAiWidget() {
        if (aiPanel.classList.contains('hidden')) {
            aiPanel.classList.remove('hidden');
            // Small delay to allow display:block to apply before animating opacity/transform
            setTimeout(() => {
                aiPanel.classList.remove('scale-95', 'opacity-0');
                aiPanel.classList.add('scale-100', 'opacity-100');
            }, 10);
        } else {
            aiPanel.classList.remove('scale-100', 'opacity-100');
            aiPanel.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                aiPanel.classList.add('hidden');
            }, 300);
        }
    }

    aiBtn.addEventListener('click', toggleAiWidget);
    aiClose.addEventListener('click', toggleAiWidget);

    function addMessage(sender, text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = sender === 'user' ? 'flex gap-2 max-w-[85%] self-end flex-row-reverse' : 'flex gap-2 max-w-[85%]';
        
        const avatar = document.createElement('div');
        avatar.className = sender === 'user' 
            ? 'h-6 w-6 rounded-full bg-brand-skyLight text-brand-slate flex items-center justify-center text-[10px] shrink-0 mt-1' 
            : 'h-6 w-6 rounded-full bg-brand-sageLight text-brand-sage flex items-center justify-center text-[10px] shrink-0 mt-1';
        avatar.innerText = sender === 'user' ? 'ME' : 'AI';

        const bubble = document.createElement('div');
        bubble.className = sender === 'user'
            ? 'bg-brand-sage text-white p-3 rounded-2xl rounded-tr-sm shadow-sm text-xs'
            : 'bg-white p-3 rounded-2xl rounded-tl-sm border border-gray-100 shadow-sm text-xs text-brand-slate';
        bubble.innerText = text;

        msgDiv.appendChild(avatar);
        msgDiv.appendChild(bubble);
        aiChatArea.appendChild(msgDiv);
        aiChatArea.scrollTop = aiChatArea.scrollHeight;
    }

    aiChatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = aiChatInput.value.trim();
        if (!msg) return;

        addMessage('user', msg);
        aiChatInput.value = '';
        
        chatHistory.push({ role: 'user', content: msg });
        
        // Add typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.id = 'ai-typing';
        typingDiv.className = 'flex gap-2 max-w-[85%]';
        typingDiv.innerHTML = `
            <div class="h-6 w-6 rounded-full bg-brand-sageLight text-brand-sage flex items-center justify-center text-[10px] shrink-0 mt-1">AI</div>
            <div class="bg-white p-3 rounded-2xl rounded-tl-sm border border-gray-100 shadow-sm text-xs text-gray-400 flex items-center gap-1">
                <span class="animate-bounce inline-block">.</span><span class="animate-bounce inline-block" style="animation-delay: 0.1s">.</span><span class="animate-bounce inline-block" style="animation-delay: 0.2s">.</span>
            </div>
        `;
        aiChatArea.appendChild(typingDiv);
        aiChatArea.scrollTop = aiChatArea.scrollHeight;

        try {
            // Using Puter.js Chat API
            const response = await puter.ai.chat(chatHistory);
            
            // Remove typing indicator
            document.getElementById('ai-typing').remove();
            
            const reply = response.message.content;
            addMessage('ai', reply);
            chatHistory.push({ role: 'assistant', content: reply });
        } catch (error) {
            console.error('Puter AI Error:', error);
            document.getElementById('ai-typing').remove();
            addMessage('ai', "I'm sorry, I'm having trouble connecting right now.");
        }
    });
</script>
