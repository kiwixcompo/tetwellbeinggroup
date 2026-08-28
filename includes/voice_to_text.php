<?php
/**
 * Tet Wellbeing Group - Universal Voice-to-Text Input Module (voice_to_text.php)
 * Attaches a sleek microphone button to text inputs, textareas, search bars, and journaling fields.
 * Supports live real-time speech-to-text transcription with continuous edit mode.
 */
?>
<style>
.voice-mic-active {
    background-color: #E76F51 !important;
    color: white !important;
    animation: voice-pulse 1.5s infinite !important;
}
@keyframes voice-pulse {
    0% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0.7); transform: scale(1); }
    70% { box-shadow: 0 0 0 10px rgba(231, 111, 81, 0); transform: scale(1.08); }
    100% { box-shadow: 0 0 0 0 rgba(231, 111, 81, 0); transform: scale(1); }
}
</style>

<script>
class UniversalVoiceToText {
    constructor() {
        this.recognition = null;
        this.isRecording = false;
        this.activeTargetInput = null;
        this.activeMicButton = null;
        this.initSpeechEngine();
    }

    initSpeechEngine() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';

            this.recognition.onstart = () => {
                this.isRecording = true;
                if (this.activeMicButton) {
                    this.activeMicButton.classList.add('voice-mic-active');
                    this.activeMicButton.setAttribute('title', 'Listening... Tap to stop');
                }
            };

            this.silenceTimer = null;

            this.recognition.onresult = (event) => {
                if (!this.activeTargetInput) return;
                
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }

                const textToAppend = (finalTranscript || interimTranscript).trim();
                if (finalTranscript) {
                    const currentVal = this.activeTargetInput.value.trim();
                    this.activeTargetInput.value = currentVal ? `${currentVal} ${finalTranscript.trim()}` : finalTranscript.trim();
                    this.activeTargetInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Check auto-submit preference
                const isAutoSubmit = localStorage.getItem('tet_voice_auto_submit') === 'true' || this.activeTargetInput.dataset.autoSubmit === 'true';
                if (isAutoSubmit && textToAppend) {
                    clearTimeout(this.silenceTimer);
                    this.silenceTimer = setTimeout(() => {
                        if (this.isRecording && this.activeTargetInput && this.activeTargetInput.value.trim().length > 0) {
                            const form = this.activeTargetInput.closest('form');
                            this.stop();
                            if (form) {
                                form.requestSubmit ? form.requestSubmit() : form.submit();
                            }
                        }
                    }, 1400);
                }
            };

            this.recognition.onerror = (event) => {
                console.warn('Voice-to-Text Error:', event.error);
                this.stop();
            };

            this.recognition.onend = () => {
                if (this.isRecording) {
                    // Try to keep recording until user explicitly taps mic again
                    try { this.recognition.start(); } catch(e) { this.stop(); }
                } else {
                    this.stop();
                }
            };
        }
    }

    toggle(targetInputId, micButtonEl) {
        const targetInput = typeof targetInputId === 'string' ? document.getElementById(targetInputId) : targetInputId;
        if (!targetInput) return;

        if (this.isRecording && this.activeTargetInput === targetInput) {
            this.stop();
            return;
        }

        if (this.isRecording) {
            this.stop();
        }

        this.activeTargetInput = targetInput;
        this.activeMicButton = micButtonEl;

        if (this.recognition) {
            try {
                this.recognition.start();
            } catch(e) {
                console.warn('Voice start exception:', e);
            }
        } else {
            alert("Speech recognition is not supported in your current browser. Please try Chrome, Edge, or Safari.");
        }
    }

    stop() {
        this.isRecording = false;
        if (this.recognition) {
            try { this.recognition.stop(); } catch(e) {}
        }
        if (this.activeMicButton) {
            this.activeMicButton.classList.remove('voice-mic-active');
            this.activeMicButton.setAttribute('title', 'Voice-to-Text Input');
        }
        this.activeTargetInput = null;
        this.activeMicButton = null;
    }

    // Auto-bind voice buttons to fields marked with data-voice-field or common selectors
    autoBind() {
        document.querySelectorAll('textarea, input[type="text"]:not(#ai-widget-input)').forEach(input => {
            // Check if already bound
            if (input.dataset.voiceBound === 'true') return;
            input.dataset.voiceBound = 'true';

            // If input is inside a relative container or has an explicit transcribe button
            const wrapper = input.parentElement;
            if (wrapper && input.id && !wrapper.querySelector(`[data-voice-for="${input.id}"]`)) {
                // Check if it has a label header or is a large textarea
                if (input.tagName.toLowerCase() === 'textarea') {
                    const micBtn = document.createElement('button');
                    micBtn.type = 'button';
                    micBtn.className = 'absolute top-3 right-3 p-2 rounded-xl text-gray-400 hover:text-brand-sage hover:bg-brand-sageLight/50 transition-all text-xs font-semibold flex items-center gap-1 z-10';
                    micBtn.innerHTML = '<i class="fa-solid fa-microphone text-sm"></i>';
                    micBtn.setAttribute('title', 'Voice-to-Text (Tap to speak)');
                    micBtn.setAttribute('data-voice-for', input.id);
                    
                    if (window.getComputedStyle(wrapper).position === 'static') {
                        wrapper.classList.add('relative');
                    }
                    wrapper.appendChild(micBtn);
                    
                    micBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggle(input, micBtn);
                    });
                }
            }
        });

        // Also bind any explicit buttons with data-voice-target
        document.querySelectorAll('[data-voice-target]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = btn.getAttribute('data-voice-target');
                this.toggle(targetId, btn);
            });
        });
    }
}

// Global instance
window.TetVoiceToText = new UniversalVoiceToText();

document.addEventListener('DOMContentLoaded', () => {
    window.TetVoiceToText.autoBind();
});
</script>
