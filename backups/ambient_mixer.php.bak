<?php
/**
 * Tet Wellbeing Group - Ambient Sound Mixer (ambient_mixer.php)
 * A procedural Web Audio API ambient noise generator for focus and sleep.
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
    <title>Ambient Sound Mixer | Tet Wellbeing</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#0F172A',
                            card: '#1E293B',
                            slate: '#F1F5F9',
                            sage: '#84A98C',
                            sageLight: '#CAD2C5',
                            coral: '#E07A5F',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        body { background-color: #0F172A; font-family: 'Inter', sans-serif; color: #F1F5F9; }
        
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #84A98C;
            cursor: pointer;
            margin-top: -8px;
            box-shadow: 0 0 10px rgba(132, 169, 140, 0.5);
        }
        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            cursor: pointer;
            background: #334155;
            border-radius: 2px;
        }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="min-h-screen pb-32">
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <!-- NAV BAR -->
    <?php if (!isset($_GET['embed'])): ?>

<?php endif; ?>

    <div class="max-w-7xl mx-auto px-6 mt-8">
        </div>

    <main class="max-w-3xl mx-auto px-6 mt-8">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 mb-2">
                <?php echo getPlanBadge('guest'); ?>
            </div>
            <h1 class="text-4xl font-extrabold font-outfit text-white tracking-tight mb-3">Ambient Mixer</h1>
            <p class="text-gray-400">Blend procedural soundscapes for deep focus or restful sleep.</p>
            <p id="audio-status" class="mt-4 inline-block bg-brand-coral/20 text-brand-coral px-3 py-1 rounded-full text-xs font-bold border border-brand-coral/30">Click Play to enable Web Audio</p>
        </div>

        <div class="bg-brand-card p-8 rounded-3xl border border-white/5 shadow-2xl space-y-8 relative overflow-hidden">
            <!-- Glow effect behind -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-brand-sage/10 rounded-full blur-[80px] pointer-events-none"></div>

            <!-- Master Controls -->
            <div class="flex items-center justify-between bg-slate-800/50 p-4 rounded-2xl border border-white/5 relative z-10">
                <button id="master-play" class="bg-brand-sage hover:bg-brand-sageLight text-slate-900 w-14 h-14 rounded-full flex items-center justify-center font-bold transition-transform active:scale-95 shadow-lg">
                    <svg class="w-6 h-6 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </button>

                <div class="flex items-center gap-4">
                    <label class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Sleep Timer:</label>
                    <select id="sleep-timer" class="bg-slate-900 border border-white/10 text-brand-sage rounded-xl px-4 py-2 text-sm font-bold outline-none focus:border-brand-sage transition-colors">
                        <option value="0">Off</option>
                        <option value="15">15 Minutes</option>
                        <option value="30">30 Minutes</option>
                        <option value="60">60 Minutes</option>
                    </select>
                </div>
            </div>
            
            <div id="timer-display" class="text-center text-sm font-bold text-brand-coral hidden">
                Fading out in <span id="timer-countdown">00:00</span>
            </div>

            <!-- Mixer Sliders -->
            <div class="space-y-6 relative z-10">
                <!-- Rain -->
                <div class="bg-slate-800/30 p-5 rounded-2xl border border-white/5 transition-colors hover:border-white/10">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🌧️</span>
                            <span class="font-semibold text-brand-slate tracking-wide">Heavy Rain</span>
                        </div>
                        <span id="vol-val-rain" class="text-xs font-bold text-brand-sage">0%</span>
                    </div>
                    <input type="range" id="vol-rain" min="0" max="1" step="0.01" value="0">
                </div>

                <!-- Fire -->
                <div class="bg-slate-800/30 p-5 rounded-2xl border border-white/5 transition-colors hover:border-white/10">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🔥</span>
                            <span class="font-semibold text-brand-slate tracking-wide">Campfire</span>
                        </div>
                        <span id="vol-val-fire" class="text-xs font-bold text-brand-sage">0%</span>
                    </div>
                    <input type="range" id="vol-fire" min="0" max="1" step="0.01" value="0">
                </div>

                <!-- Brown Noise (Ocean) -->
                <div class="bg-slate-800/30 p-5 rounded-2xl border border-white/5 transition-colors hover:border-white/10">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🌊</span>
                            <span class="font-semibold text-brand-slate tracking-wide">Deep Ocean (Brown Noise)</span>
                        </div>
                        <span id="vol-val-ocean" class="text-xs font-bold text-brand-sage">0%</span>
                    </div>
                    <input type="range" id="vol-ocean" min="0" max="1" step="0.01" value="0">
                </div>

                <!-- Binaural Beats -->
                <div class="bg-slate-800/30 p-5 rounded-2xl border border-white/5 transition-colors hover:border-white/10">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🎧</span>
                            <span class="font-semibold text-brand-slate tracking-wide">Binaural Focus (432Hz)</span>
                        </div>
                        <span id="vol-val-binaural" class="text-xs font-bold text-brand-sage">0%</span>
                    </div>
                    <input type="range" id="vol-binaural" min="0" max="1" step="0.01" value="0">
                </div>
            </div>

        </div>
    </main>

    <script>
        // Web Audio API Synthesizer
        let audioCtx;
        let isPlaying = false;
        
        // Nodes
        const nodes = {
            rain: null,
            fire: null,
            ocean: null,
            binauralL: null,
            binauralR: null
        };
        
        // Gains
        const gains = {
            master: null,
            rain: null,
            fire: null,
            ocean: null,
            binaural: null
        };

        // Sleep timer state
        let timerInterval;
        let fadeSeconds = 0;

        function initAudio() {
            if (audioCtx) return;
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            
            gains.master = audioCtx.createGain();
            gains.master.gain.value = 1;
            gains.master.connect(audioCtx.destination);

            // 1. Rain (Pink Noise with filter)
            gains.rain = audioCtx.createGain();
            gains.rain.gain.value = 0;
            gains.rain.connect(gains.master);
            nodes.rain = createPinkNoise(audioCtx);
            let rainFilter = audioCtx.createBiquadFilter();
            rainFilter.type = 'lowpass';
            rainFilter.frequency.value = 1200;
            nodes.rain.connect(rainFilter);
            rainFilter.connect(gains.rain);

            // 2. Ocean (Brown Noise)
            gains.ocean = audioCtx.createGain();
            gains.ocean.gain.value = 0;
            gains.ocean.connect(gains.master);
            nodes.ocean = createBrownNoise(audioCtx);
            let oceanFilter = audioCtx.createBiquadFilter();
            oceanFilter.type = 'lowpass';
            oceanFilter.frequency.value = 400;
            nodes.ocean.connect(oceanFilter);
            oceanFilter.connect(gains.ocean);

            // 3. Fire (Crackle + White Noise)
            gains.fire = audioCtx.createGain();
            gains.fire.gain.value = 0;
            gains.fire.connect(gains.master);
            nodes.fire = createWhiteNoise(audioCtx);
            let fireFilter = audioCtx.createBiquadFilter();
            fireFilter.type = 'highpass';
            fireFilter.frequency.value = 1000;
            nodes.fire.connect(fireFilter);
            fireFilter.connect(gains.fire);

            // 4. Binaural Beats (432Hz left, 436Hz right for 4Hz Theta beat)
            gains.binaural = audioCtx.createGain();
            gains.binaural.gain.value = 0;
            gains.binaural.connect(gains.master);
            
            let merger = audioCtx.createChannelMerger(2);
            nodes.binauralL = audioCtx.createOscillator();
            nodes.binauralR = audioCtx.createOscillator();
            nodes.binauralL.frequency.value = 432;
            nodes.binauralR.frequency.value = 436;
            
            nodes.binauralL.connect(merger, 0, 0); // left
            nodes.binauralR.connect(merger, 0, 1); // right
            merger.connect(gains.binaural);
            
            nodes.binauralL.start();
            nodes.binauralR.start();

            document.getElementById('audio-status').textContent = 'Audio Engine Ready. Adjust sliders.';
            document.getElementById('audio-status').classList.replace('bg-brand-coral/20', 'bg-brand-sage/20');
            document.getElementById('audio-status').classList.replace('text-brand-coral', 'text-brand-sage');
            document.getElementById('audio-status').classList.replace('border-brand-coral/30', 'border-brand-sage/30');
        }

        // Noise Generators
        function createWhiteNoise(ctx) {
            const bufferSize = 2 * ctx.sampleRate;
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const output = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                output[i] = Math.random() * 2 - 1;
            }
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            noise.loop = true;
            noise.start(0);
            return noise;
        }

        function createPinkNoise(ctx) {
            const bufferSize = 2 * ctx.sampleRate;
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const output = buffer.getChannelData(0);
            let b0=0, b1=0, b2=0, b3=0, b4=0, b5=0, b6=0;
            for (let i = 0; i < bufferSize; i++) {
                let white = Math.random() * 2 - 1;
                b0 = 0.99886 * b0 + white * 0.0555179;
                b1 = 0.99332 * b1 + white * 0.0750759;
                b2 = 0.96900 * b2 + white * 0.1538520;
                b3 = 0.86650 * b3 + white * 0.3104856;
                b4 = 0.55000 * b4 + white * 0.5329522;
                b5 = -0.7616 * b5 - white * 0.0168980;
                output[i] = b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362;
                output[i] *= 0.11; // compensation
                b6 = white * 0.115926;
            }
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            noise.loop = true;
            noise.start(0);
            return noise;
        }

        function createBrownNoise(ctx) {
            const bufferSize = 2 * ctx.sampleRate;
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const output = buffer.getChannelData(0);
            let lastOut = 0;
            for (let i = 0; i < bufferSize; i++) {
                let white = Math.random() * 2 - 1;
                output[i] = (lastOut + (0.02 * white)) / 1.02;
                lastOut = output[i];
                output[i] *= 3.5; // compensation
            }
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            noise.loop = true;
            noise.start(0);
            return noise;
        }

        // Setup Sliders
        const sliderMap = {
            'rain': 'vol-rain',
            'fire': 'vol-fire',
            'ocean': 'vol-ocean',
            'binaural': 'vol-binaural'
        };

        for (let [key, id] of Object.entries(sliderMap)) {
            const slider = document.getElementById(id);
            const valLabel = document.getElementById('vol-val-' + key);
            
            slider.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value);
                valLabel.textContent = Math.round(val * 100) + '%';
                if (audioCtx && gains[key]) {
                    // Exponential curve for natural volume
                    gains[key].gain.setTargetAtTime(val * val, audioCtx.currentTime, 0.1);
                }
            });
        }

        // Play/Pause Button
        const playBtn = document.getElementById('master-play');
        playBtn.addEventListener('click', () => {
            if (!audioCtx) initAudio();
            
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
                isPlaying = true;
                playBtn.innerHTML = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
            } else if (audioCtx.state === 'running') {
                audioCtx.suspend();
                isPlaying = false;
                playBtn.innerHTML = '<svg class="w-6 h-6 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
            }
        });

        // Sleep Timer
        document.getElementById('sleep-timer').addEventListener('change', (e) => {
            clearInterval(timerInterval);
            const minutes = parseInt(e.target.value);
            const display = document.getElementById('timer-display');
            
            if (minutes === 0) {
                display.classList.add('hidden');
                if (gains.master) gains.master.gain.setValueAtTime(1, audioCtx.currentTime);
                return;
            }

            fadeSeconds = minutes * 60;
            display.classList.remove('hidden');
            
            // Initial volume
            if (gains.master) gains.master.gain.setValueAtTime(1, audioCtx.currentTime);

            timerInterval = setInterval(() => {
                fadeSeconds--;
                let m = Math.floor(fadeSeconds / 60);
                let s = fadeSeconds % 60;
                document.getElementById('timer-countdown').textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                
                // Fade out logic in last 60 seconds
                if (fadeSeconds <= 60 && gains.master) {
                    gains.master.gain.setValueAtTime(fadeSeconds / 60, audioCtx.currentTime);
                }

                if (fadeSeconds <= 0) {
                    clearInterval(timerInterval);
                    display.classList.add('hidden');
                    document.getElementById('sleep-timer').value = "0";
                    if (audioCtx && audioCtx.state === 'running') {
                        audioCtx.suspend();
                        isPlaying = false;
                        playBtn.innerHTML = '<svg class="w-6 h-6 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
                    }
                }
            }, 1000);
        });
    </script>
</body>
</html>
