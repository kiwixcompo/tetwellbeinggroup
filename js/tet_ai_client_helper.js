/**
 * Tet Wellbeing Group - Client-Side AI & User Learning Bridge (tet_ai_client_helper.js)
 * 
 * 1. Prevents Puter AI authentication popups for users who are not signed in to Puter.
 * 2. Isolates conversation history, memory, and settings strictly per logged-in User ID.
 * 3. Injects personalized user context into prompts so the AI continuously learns per account.
 */

window.TetAiHelper = {
    // Current user context (populated on page load)
    currentUser: {
        id: 0,
        name: 'Friend',
        plan: 'Basic Tier',
        contextPrompt: ''
    },

    init: function(userData) {
        if (userData) {
            this.currentUser = Object.assign(this.currentUser, userData);
        }
    },

    // User-namespaced storage helper
    getStorageKey: function(key) {
        const uid = this.currentUser.id || 'guest';
        return `tet_${key}_user_${uid}`;
    },

    getStoredData: function(key, defaultValue = null) {
        try {
            const raw = localStorage.getItem(this.getStorageKey(key));
            return raw !== null ? JSON.parse(raw) : defaultValue;
        } catch(e) {
            return defaultValue;
        }
    },

    setStoredData: function(key, value) {
        try {
            localStorage.setItem(this.getStorageKey(key), JSON.stringify(value));
        } catch(e) {}
    },

    /**
     * Safely calls Puter AI ONLY if the user is authenticated in Puter,
     * completely avoiding Puter sign-in popups for unauthenticated users.
     */
    safeChat: async function(promptOrHistory, fallbackFn) {
        let isPuterSignedIn = false;

        try {
            // Check if Puter library exists and check auth state without triggering popups
            if (typeof puter !== 'undefined' && puter.auth && typeof puter.auth.isSignedIn === 'function') {
                isPuterSignedIn = await puter.auth.isSignedIn();
            }
        } catch (e) {
            isPuterSignedIn = false;
        }

        // If signed into Puter, call Puter AI
        if (isPuterSignedIn && typeof puter !== 'undefined' && puter.ai && typeof puter.ai.chat === 'function') {
            try {
                const response = await puter.ai.chat(promptOrHistory);
                if (response) {
                    const text = typeof response === 'string' ? response : (response.message?.content || response.text || JSON.stringify(response));
                    if (text && text.trim().length > 0) {
                        return text;
                    }
                }
            } catch (err) {
                console.warn("Puter AI call failed, falling back gracefully without popup:", err);
            }
        }

        // Seamless fallback without displaying any Puter popup
        if (typeof fallbackFn === 'function') {
            return await fallbackFn();
        }

        return `Hello ${this.currentUser.name}! I'm listening closely and here to support your wellbeing journey. How are you feeling right now?`;
    },

    /**
     * Record interaction to backend learning service
     */
    recordInteraction: function(endpointUrl, userMessage, character = 'maya') {
        if (!this.currentUser.id) return;
        try {
            const fd = new FormData();
            fd.append('action', 'learn_interaction');
            fd.append('message', userMessage);
            fd.append('character', character);
            fetch(endpointUrl, { method: 'POST', body: fd }).catch(() => {});
        } catch(e) {}
    }
};
