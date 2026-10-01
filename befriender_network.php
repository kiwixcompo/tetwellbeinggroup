<?php
/**
 * Tet Wellbeing Group - Befriender Support Network
 * A premium page for connecting with peers, scheduling check-ins, joining coffee chats, and applying to the academy.
 */
require_once 'db.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));

// Tab Management
$active_subtab = filter_input(INPUT_GET, 'subtab', FILTER_DEFAULT) ?: 'match_me';
$allowed_subtabs = ['match_me', 'my_checkins', 'coffee_chat', 'academy'];
if (!in_array($active_subtab, $allowed_subtabs)) {
    $active_subtab = 'match_me';
}

// Initialize Mock Data if not set
if (!isset($_SESSION['mock_befrienders'])) {
    $_SESSION['mock_befrienders'] = [
        ['id' => 1, 'name' => 'Sarah J.', 'languages' => ['English', 'Spanish'], 'experiences' => ['Caregiving', 'New Parent'], 'rating' => 4.9, 'bio' => 'A friendly listener who has walked the caregiving journey.'],
        ['id' => 2, 'name' => 'Michael T.', 'languages' => ['English', 'Urdu'], 'experiences' => ['Veteran', 'Career Transition'], 'rating' => 4.8, 'bio' => 'Here to support fellow veterans and those undergoing major life shifts.'],
        ['id' => 3, 'name' => 'Elena R.', 'languages' => ['Spanish', 'Mandarin'], 'experiences' => ['Student Life', 'Anxiety'], 'rating' => 5.0, 'bio' => 'Navigating student pressures can be tough. Let\'s chat about it!'],
        ['id' => 4, 'name' => 'David W.', 'languages' => ['English'], 'experiences' => ['Bereavement', 'Retirement'], 'rating' => 4.7, 'bio' => 'Offering a compassionate ear to those dealing with loss.']
    ];
}

if (!isset($_SESSION['mock_befriender_sessions'])) {
    $_SESSION['mock_befriender_sessions'] = [];
}

$action = filter_input(INPUT_POST, 'action', FILTER_DEFAULT);

if ($action === 'match_befriender') {
    $befriender_id = filter_input(INPUT_POST, 'befriender_id', FILTER_VALIDATE_INT);
    $session_date = filter_input(INPUT_POST, 'session_date', FILTER_DEFAULT) ?: date('Y-m-d', strtotime('+2 days'));
    $session_time = filter_input(INPUT_POST, 'session_time', FILTER_DEFAULT) ?: '10:00 AM';
    $session_mode = filter_input(INPUT_POST, 'session_mode', FILTER_DEFAULT) ?: 'Video Chat';
    $session_note = trim(filter_input(INPUT_POST, 'session_note', FILTER_DEFAULT) ?: '');

    if ($befriender_id) {
        $befriender = null;
        foreach ($_SESSION['mock_befrienders'] as $b) {
            if ($b['id'] == $befriender_id) {
                $befriender = $b;
                break;
            }
        }
        if ($befriender) {
            $formatted_dt = date('Y-m-d H:i:s', strtotime("$session_date $session_time"));
            $_SESSION['mock_befriender_sessions'][] = [
                'id' => time() . rand(100,999),
                'user_id' => $user_id,
                'befriender_id' => $befriender_id,
                'befriender_name' => $befriender['name'],
                'date' => $formatted_dt,
                'mode' => $session_mode,
                'note' => $session_note,
                'status' => 'scheduled'
            ];

            // Sync to Wellbeing Passport
            if (!isset($_SESSION['user_activity_count'][$user_id])) {
                $_SESSION['user_activity_count'][$user_id] = 1;
            } else {
                $_SESSION['user_activity_count'][$user_id]++;
            }

            header("Location: befriender_network.php?matched=1");
            exit;
        }
    }
}

if ($action === 'cancel_session') {
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_DEFAULT);
    if ($session_id) {
        foreach ($_SESSION['mock_befriender_sessions'] as &$sess) {
            if ($sess['id'] == $session_id && $sess['user_id'] == $user_id) {
                $sess['status'] = 'cancelled';
                break;
            }
        }
        unset($sess);
        header("Location: befriender_network.php?cancelled=1");
        exit;
    }
}

if ($action === 'apply_academy') {
    header("Location: befriender_network.php?applied=1");
    exit;
}

if ($action === 'join_conversation') {
    header("Location: befriender_network.php?joined=1");
    exit;
}

// Read success messages
$success_msg = '';
if (isset($_GET['matched'])) $success_msg = "Successfully scheduled your check-in with a befriender.";
if (isset($_GET['cancelled'])) $success_msg = "Your session has been cancelled.";
if (isset($_GET['applied'])) $success_msg = "Application received! We will be in touch shortly.";
if (isset($_GET['joined'])) $success_msg = "Successfully RSVP'd/Joined the virtual meetup.";

?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Befriender Support Network - Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    borderRadius: {
                        '2xl': '1rem',
                        '3xl': '1.5rem'
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)'
                    }
                }
            }
        }
    </script>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script>
        window.switchTab = function(tabId) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
                el.style.display = 'none';
            });

            // Reset all tab button styles
            document.querySelectorAll('[id^="tab-"]').forEach(function(el) {
                el.classList.remove('border-brand-sage', 'text-brand-sage', 'bg-white', 'shadow-sm', 'font-semibold');
                el.classList.add('border-transparent', 'text-gray-500', 'font-medium');
            });
            
            // Show selected content
            var targetContent = document.getElementById('content-' + tabId);
            if (targetContent) {
                targetContent.classList.add('active');
                targetContent.style.display = 'block';
            }

            // Style selected tab
            var tabBtn = document.getElementById('tab-' + tabId);
            if (tabBtn) {
                tabBtn.classList.remove('border-transparent', 'text-gray-500', 'font-medium');
                tabBtn.classList.add('border-brand-sage', 'text-brand-sage', 'bg-white', 'shadow-sm', 'font-semibold');
            }

            // Update browser URL state without page reload
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('subtab', tabId);
                window.history.replaceState({ subtab: tabId }, '', url.toString());
            } catch (e) {}
        };

        // Auto initialize on DOM ready in case of direct URL or browser back/forward
        document.addEventListener('DOMContentLoaded', function() {
            var params = new URLSearchParams(window.location.search);
            var requestedTab = params.get('subtab') || '<?php echo $active_subtab; ?>';
            if (requestedTab && document.getElementById('content-' + requestedTab)) {
                window.switchTab(requestedTab);
            }
        });
    </script>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate bg-brand-bg">
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <!-- TOP NAVIGATION BAR -->
    <?php if (!isset($_GET['embed'])): ?>

<?php endif; ?>

    <!-- Main Content Layout -->
    <main class="flex-grow">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">
            
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-xl bg-brand-sageLight border border-brand-sage/20 text-brand-sageHover flex items-start gap-3">
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-medium"><?php echo htmlspecialchars($success_msg); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Hero -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-sage to-brand-slate text-white shadow-card mb-8">
                <div class="absolute inset-0 opacity-20">
                    <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                        <defs><pattern id="circles" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="2"/></pattern></defs>
                        <rect width="100%" height="100%" fill="url(#circles)"/>
                    </svg>
                </div>
                <div class="relative px-8 py-16 sm:px-16 sm:py-24 text-center">
                    <?php require_once 'gating_helper.php'; ?>
                    <div class="inline-flex items-center gap-2 mb-3">
                        <?php echo getPlanBadge('basic'); ?>
                    </div>
                    <h1 class="font-outfit text-4xl sm:text-5xl font-bold tracking-tight mb-4">Befriender Support Network</h1>
                    <p class="text-lg sm:text-xl text-brand-sageLight max-w-2xl mx-auto opacity-90">No one should have to face life's challenges alone. Connect with peers who understand your journey.</p>
                </div>
            </div>

            <!-- Custom Tabs Navigation -->
            <div class="flex gap-2 overflow-x-auto border-b border-gray-200 pb-px mb-8 scrollbar-hide">
                <button type="button" onclick="switchTab('match_me')" id="tab-match_me" class="px-6 py-3 font-outfit text-sm rounded-t-xl transition-all border-b-2 <?php echo ($active_subtab === 'match_me') ? 'border-brand-sage text-brand-sage bg-white shadow-sm font-semibold' : 'border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50 font-medium'; ?>">Match Me</button>
                <button type="button" onclick="switchTab('my_checkins')" id="tab-my_checkins" class="px-6 py-3 font-outfit text-sm rounded-t-xl transition-all border-b-2 <?php echo ($active_subtab === 'my_checkins') ? 'border-brand-sage text-brand-sage bg-white shadow-sm font-semibold' : 'border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50 font-medium'; ?>">My Check-ins</button>
                <button type="button" onclick="switchTab('coffee_chat')" id="tab-coffee_chat" class="px-6 py-3 font-outfit text-sm rounded-t-xl transition-all border-b-2 <?php echo ($active_subtab === 'coffee_chat') ? 'border-brand-sage text-brand-sage bg-white shadow-sm font-semibold' : 'border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50 font-medium'; ?>">Coffee & Conversation</button>
                <button type="button" onclick="switchTab('academy')" id="tab-academy" class="px-6 py-3 font-outfit text-sm rounded-t-xl transition-all border-b-2 <?php echo ($active_subtab === 'academy') ? 'border-brand-sage text-brand-sage bg-white shadow-sm font-semibold' : 'border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50 font-medium'; ?>">Academy</button>
            </div>

            <!-- Tab 1: Match Me -->
            <div id="content-match_me" class="tab-content <?php echo ($active_subtab === 'match_me') ? 'active' : ''; ?>">
                
                <!-- Companion Type Selector Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                    <!-- AI Companion Card -->
                    <div class="bg-white rounded-3xl p-6 shadow-card border-2 border-brand-coral/20 flex flex-col justify-between hover:border-brand-coral transition-all group">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="h-14 w-14 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center text-2xl shrink-0 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-face-smile"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-brand-coral bg-brand-coral/10 px-2.5 py-0.5 rounded-full">Instant 24/7 AI Peer</span>
                                <h3 class="text-xl font-bold font-outfit text-brand-slate mt-1">AI Befriender (Maya & Alex)</h3>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Need someone to talk with right now? Choose between Maya (warm & caring) or Alex (grounded & friendly) for authentic 1-on-1 peer conversations with realistic voices.
                                </p>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Available 24/7</span>
                            <a href="ai_befriender.php" class="px-5 py-2.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-xs transition-all shadow-sm active:scale-95 flex items-center gap-2">
                                <i class="fa-solid fa-comments"></i> Chat with AI Befriender
                            </a>
                        </div>
                    </div>

                    <!-- Human Peer Befriender Card -->
                    <div class="bg-white rounded-3xl p-6 shadow-card border-2 border-brand-coral/20 flex flex-col justify-between hover:border-brand-coral transition-all group">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="h-14 w-14 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center text-2xl shrink-0 group-hover:scale-105 transition-transform">
                                🤝
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-brand-coral bg-brand-coral/10 px-2.5 py-0.5 rounded-full">Human Peer Match</span>
                                <h3 class="text-xl font-bold font-outfit text-brand-slate mt-1">Human Befriender</h3>
                                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                    Prefer a human connection? Filter by background, language, and shared lived experience below to schedule a check-in.
                                </p>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-400">Verified Volunteers</span>
                            <a href="#human-match-section" class="px-5 py-2.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-xs transition-all shadow-sm active:scale-95 flex items-center gap-2">
                                <i class="fa-solid fa-sliders"></i> Filter Peers Below
                            </a>
                        </div>
                    </div>
                </div>

                <div id="human-match-section" class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-2">
                    <!-- Filters -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-3xl shadow-card p-6 sticky top-28 border border-[#EBE8E0]">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-outfit font-bold text-brand-slate">Peer Match Filters</h2>
                                <span class="text-[10px] font-bold text-brand-sage uppercase bg-brand-sageLight px-2 py-0.5 rounded-md">Human</span>
                            </div>
                            <form method="GET" class="space-y-3.5">
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Search or Topic</label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="peer-search-filter" 
                                            onkeyup="filterPeerList()" 
                                            placeholder="Search name, caregiving, veteran..."
                                            class="w-full rounded-xl border border-gray-200 bg-brand-inputBg pl-3 pr-8 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate"
                                        >
                                        <button type="button" onclick="window.TetVoiceToText.toggle('peer-search-filter', this)" title="Voice Search" class="absolute right-2 top-1.5 p-1 text-gray-400 hover:text-brand-sage transition-all rounded">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Language</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3.5 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate">
                                        <option>Any Language</option>
                                        <option>English</option>
                                        <option>Spanish</option>
                                        <option>Urdu</option>
                                        <option>Arabic</option>
                                        <option>Yoruba</option>
                                        <option>Hausa</option>
                                        <option>Igbo</option>
                                        <option>French</option>
                                        <option>Mandarin</option>
                                        <option>Punjabi</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Culture / Background</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3.5 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate">
                                        <option>Any / No Preference</option>
                                        <option>British / European</option>
                                        <option>African (West/East/South)</option>
                                        <option>South Asian (Pakistani/Indian/Bengali)</option>
                                        <option>Caribbean</option>
                                        <option>East / Southeast Asian</option>
                                        <option>Middle Eastern / Arab</option>
                                        <option>Latin American</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Age Range</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3.5 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate">
                                        <option>Any Age Range</option>
                                        <option>18–25 years</option>
                                        <option>26–40 years</option>
                                        <option>41–60 years</option>
                                        <option>60+ years</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Shared Experience</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3.5 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate">
                                        <option>Any Experience</option>
                                        <option>Caregiving</option>
                                        <option>Bereavement</option>
                                        <option>Student Life</option>
                                        <option>Veteran</option>
                                        <option>Career Transition</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-brand-slate mb-1">Gender Preference</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3.5 py-2 text-xs focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all text-brand-slate">
                                        <option>No Preference</option>
                                        <option>Female</option>
                                        <option>Male</option>
                                        <option>Non-binary</option>
                                    </select>
                                </div>
                                <button type="button" onclick="alert('Filters applied to peer list.')" class="w-full rounded-xl bg-brand-sage py-2.5 text-xs font-bold text-white transition-all hover:bg-brand-sageHover hover:shadow-md mt-3">
                                    Filter Matches
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Available Befrienders -->
                    <div class="lg:col-span-2 space-y-6">
                        <?php foreach ($_SESSION['mock_befrienders'] as $befriender): ?>
                            <div class="peer-card bg-white rounded-2xl shadow-card p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6 border border-transparent hover:border-brand-sageLight transition-colors" data-name="<?php echo htmlspecialchars(strtolower($befriender['name'])); ?>" data-experiences="<?php echo htmlspecialchars(strtolower(implode(' ', $befriender['experiences']))); ?>" data-languages="<?php echo htmlspecialchars(strtolower(implode(' ', $befriender['languages']))); ?>">
                                <div class="h-16 w-16 bg-brand-sageLight rounded-full flex items-center justify-center text-brand-sage font-bold text-2xl shrink-0">
                                    <?php echo substr($befriender['name'], 0, 1); ?>
                                </div>
                                <div class="flex-grow">
                                        <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <h3 class="text-xl font-bold text-brand-slate"><?php echo htmlspecialchars($befriender['name']); ?></h3>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="inline-flex items-center gap-1 text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    🛡️ Enhanced DBS Checked
                                                </span>
                                                <span class="text-[10px] text-gray-400 font-medium">Safeguarding Level 2</span>
                                            </div>
                                        </div>
                                        <span class="flex items-center text-sm font-bold text-brand-sage">
                                            <svg class="w-4 h-4 mr-1 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            <?php echo number_format($befriender['rating'], 1); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?php echo htmlspecialchars($befriender['bio']); ?></p>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <?php foreach ($befriender['experiences'] as $exp): ?>
                                            <span class="px-2.5 py-1 bg-brand-bg rounded-md text-xs font-medium text-brand-slate border border-gray-200"><?php echo htmlspecialchars($exp); ?></span>
                                        <?php endforeach; ?>
                                        <?php foreach ($befriender['languages'] as $lang): ?>
                                            <span class="px-2.5 py-1 bg-brand-sky/10 rounded-md text-xs font-medium text-brand-slate border border-brand-sky/20"><?php echo htmlspecialchars($lang); ?></span>
                                        <?php endforeach; ?>
                                        <span class="px-2.5 py-1 bg-purple-50 rounded-md text-xs font-medium text-purple-700 border border-purple-200">Gentle Listener</span>
                                    </div>
                                </div>
                                <div class="w-full sm:w-auto shrink-0">
                                    <button type="button" onclick="openBefrienderBookingModal(<?php echo $befriender['id']; ?>, '<?php echo addslashes($befriender['name']); ?>')" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-brand-slate text-white text-sm font-semibold hover:bg-opacity-90 transition-all shadow-md active:scale-95 flex items-center justify-center gap-1.5">
                                        <i class="fa-regular fa-calendar text-xs"></i>
                                        <span>Schedule Check-in</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tab 2: My Check-ins -->
            <div id="content-my_checkins" class="tab-content <?php echo ($active_subtab === 'my_checkins') ? 'active' : ''; ?>">
                <div class="bg-white rounded-3xl shadow-card p-8">
                    <h2 class="text-2xl font-outfit font-bold text-brand-slate mb-8">My Scheduled Check-ins</h2>
                    
                    <?php 
                    $has_sessions = false;
                    foreach ($_SESSION['mock_befriender_sessions'] as $session) {
                        if ($session['user_id'] == $user_id && $session['status'] === 'scheduled') {
                            $has_sessions = true;
                            break;
                        }
                    }
                    ?>

                    <?php if (!$has_sessions): ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-bg text-gray-400 mb-4">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <h3 class="text-lg font-bold text-brand-slate mb-2">No scheduled check-ins</h3>
                            <p class="text-gray-500 mb-6">You don't have any upcoming sessions with a befriender.</p>
                            <button onclick="switchTab('match_me')" class="px-6 py-2.5 rounded-xl bg-brand-sage text-white font-semibold hover:bg-brand-sageHover transition-colors">Find a Match</button>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php foreach ($_SESSION['mock_befriender_sessions'] as $session): ?>
                                <?php if ($session['user_id'] == $user_id && $session['status'] === 'scheduled'): ?>
                                    <div class="border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                                        <div class="absolute top-0 left-0 w-1 h-full bg-brand-sage"></div>
                                        <div class="flex justify-between items-start mb-4">
                                            <div>
                                                <h4 class="font-bold text-brand-slate text-lg">Chat with <?php echo htmlspecialchars($session['befriender_name']); ?></h4>
                                                <p class="text-sm text-gray-500 flex items-center mt-1">
                                                    <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                    <?php echo date('F j, Y \a\t g:i A', strtotime($session['date'])); ?>
                                                </p>
                                                <?php if (!empty($session['note'])): ?>
                                                    <p class="text-xs text-gray-400 mt-2 italic bg-brand-bg p-2 rounded-lg">
                                                        "<?php echo htmlspecialchars($session['note']); ?>"
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="px-3 py-1 bg-brand-sageLight text-brand-sage text-xs font-bold rounded-full border border-brand-sage/20"><?php echo htmlspecialchars($session['mode']); ?></span>
                                        </div>
                                        <div class="flex gap-3 mt-6">
                                            <button type="button" onclick="openVideoConference('tet-befriender-<?php echo $session['id']; ?>', 'Check-in with <?php echo addslashes($session['befriender_name']); ?>')" class="flex-1 px-4 py-2 bg-brand-sage text-white rounded-xl text-sm font-semibold hover:bg-brand-sageHover transition-colors shadow-sm flex items-center justify-center gap-1.5 active:scale-95">
                                                <span>📹</span> Join Now
                                            </button>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="cancel_session">
                                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                <button type="submit" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-sm font-medium hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors">Cancel</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab 3: Coffee & Conversation -->
            <div id="content-coffee_chat" class="tab-content <?php echo ($active_subtab === 'coffee_chat') ? 'active' : ''; ?>">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-outfit font-bold text-brand-slate">Virtual Meetups</h2>
                        <p class="text-gray-500 mt-1">Join our moderated group sessions for informal support and chat.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Meetup Card 1 -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-transparent hover:border-brand-sky/30 transition-all flex flex-col h-full">
                        <div class="w-12 h-12 bg-brand-sky/10 rounded-xl flex items-center justify-center text-brand-sky mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <h3 class="text-lg font-bold text-brand-slate mb-2">Caregivers Coffee Chat</h3>
                        <p class="text-sm text-gray-600 mb-4 flex-grow">A safe space for those caring for loved ones to share advice and vent over coffee.</p>
                        <div class="flex items-center text-xs text-gray-500 mb-6 font-medium">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Every Tuesday, 10:00 AM
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="join_conversation">
                            <button type="submit" class="w-full py-2.5 bg-brand-bg border border-gray-200 text-brand-slate font-semibold rounded-xl hover:bg-brand-sageLight hover:text-brand-sage hover:border-brand-sage/30 transition-all">RSVP Now</button>
                        </form>
                    </div>

                    <!-- Meetup Card 2 -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-transparent hover:border-brand-sage/30 transition-all flex flex-col h-full relative overflow-hidden">
                        <div class="absolute top-0 right-0 bg-brand-coral text-white text-xs font-bold px-3 py-1 rounded-bl-lg">Starts soon</div>
                        <div class="w-12 h-12 bg-brand-sageLight rounded-xl flex items-center justify-center text-brand-sage mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                        </div>
                        <h3 class="text-lg font-bold text-brand-slate mb-2">Veterans Support Group</h3>
                        <p class="text-sm text-gray-600 mb-4 flex-grow">Connect with fellow service members transitioning to civilian life.</p>
                        <div class="flex items-center text-xs text-brand-coral mb-6 font-medium">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Today, 6:00 PM
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="join_conversation">
                            <button type="submit" class="w-full py-2.5 bg-brand-sage text-white font-semibold rounded-xl hover:bg-brand-sageHover transition-all shadow-sm">Join Meetup</button>
                        </form>
                    </div>

                    <!-- Meetup Card 3 -->
                    <div class="bg-white rounded-2xl p-6 shadow-card border border-transparent hover:border-yellow-400/30 transition-all flex flex-col h-full">
                        <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center text-yellow-500 mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" /></svg>
                        </div>
                        <h3 class="text-lg font-bold text-brand-slate mb-2">New Parents Mixer</h3>
                        <p class="text-sm text-gray-600 mb-4 flex-grow">Swap sleep tips, milestones, and encouragement with other new parents.</p>
                        <div class="flex items-center text-xs text-gray-500 mb-6 font-medium">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Every Thursday, 2:00 PM
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="join_conversation">
                            <button type="submit" class="w-full py-2.5 bg-brand-bg border border-gray-200 text-brand-slate font-semibold rounded-xl hover:bg-brand-sageLight hover:text-brand-sage hover:border-brand-sage/30 transition-all">RSVP Now</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Academy -->
            <div id="content-academy" class="tab-content <?php echo ($active_subtab === 'academy') ? 'active' : ''; ?>">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <span class="px-3 py-1 bg-brand-sky/10 text-brand-sky font-bold text-sm rounded-full tracking-wide">TET ACADEMY</span>
                        <h2 class="text-3xl font-outfit font-bold text-brand-slate mt-4 mb-6">Become a Befriender</h2>
                        <p class="text-lg text-gray-600 mb-8 leading-relaxed">Turn your life experiences into a source of support for others. Join our rigorous training academy and learn how to provide active listening, empathy, and crisis spotting for the community.</p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage shrink-0 mt-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-brand-slate">Certified Training</h4>
                                    <p class="text-sm text-gray-500 mt-1">Complete our 20-hour online modules on active listening and boundary setting.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage shrink-0 mt-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-brand-slate">Flexible Volunteering</h4>
                                    <p class="text-sm text-gray-500 mt-1">Set your own hours and connect with peers from the comfort of your home.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage shrink-0 mt-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-brand-slate">Give Back</h4>
                                    <p class="text-sm text-gray-500 mt-1">Make a tangible difference in someone's day when they need it most.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-3xl shadow-card p-8 border border-gray-100">
                        <h3 class="text-xl font-bold text-brand-slate mb-6">Apply to the Academy</h3>
                        <form method="POST" class="space-y-5">
                            <input type="hidden" name="action" value="apply_academy">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                                <input type="text" required class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-3 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all" value="<?php echo htmlspecialchars($user_name); ?>">
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label for="academy-motivation" class="block text-sm font-semibold text-gray-700">Why do you want to become a befriender?</label>
                                    <button type="button" onclick="window.TetVoiceToText.toggle('academy-motivation', this)" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-2 py-0.5 rounded-lg border border-brand-sage/10 transition-all select-none">
                                        <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                        </svg>
                                        <span>Dictate Voice</span>
                                    </button>
                                </div>
                                <textarea id="academy-motivation" name="motivation" rows="4" required class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-3 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all resize-none" placeholder="Share a little bit about your life experience and motivation..."></textarea>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="terms" required class="w-4 h-4 text-brand-sage rounded border-gray-300 focus:ring-brand-sage">
                                <label for="terms" class="text-sm text-gray-600">I agree to a background check as part of the vetting process.</label>
                            </div>
                            <button type="submit" class="w-full py-3 bg-brand-slate text-white rounded-xl font-bold hover:bg-opacity-90 transition-all shadow-md mt-2">Submit Application</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Emergency Modal -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="text-center mb-6 mt-2">
                <div class="mx-auto w-16 h-16 bg-brand-coralLight rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <h3 class="text-2xl font-outfit font-bold text-brand-slate mb-2">Need Immediate Help?</h3>
                <p class="text-gray-600">If you are in a crisis or experiencing a psychiatric emergency, please reach out immediately.</p>
            </div>
            <div class="space-y-3">
                <a href="tel:999" class="flex items-center justify-center gap-3 w-full rounded-xl bg-brand-coral py-3.5 text-white font-bold hover:bg-brand-coralHover transition-colors shadow-md">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Call Emergency Services (999)
                </a>
                <a href="tel:111" class="flex items-center justify-center gap-3 w-full rounded-xl bg-brand-bg border border-brand-coral/30 py-3.5 text-brand-coral font-bold hover:bg-brand-coralLight transition-colors">
                    Call NHS 111
                </a>
            </div>
        </div>
    </div>

    <!-- BEFRIENDER BOOKING MODAL -->
    <div id="befriender-booking-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/50 backdrop-blur-sm" onclick="closeBefrienderBookingModal()"></div>
        <div class="relative bg-white w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300">
            <button onclick="closeBefrienderBookingModal()" class="absolute top-5 right-5 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="mb-4">
                <span class="px-2.5 py-0.5 rounded-full bg-brand-sageLight text-brand-sage text-[10px] font-bold uppercase tracking-wider font-outfit">1-on-1 Peer Match</span>
                <h3 class="text-xl font-bold font-outfit text-brand-slate mt-1">Schedule Befriender Check-in</h3>
                <p id="modal-befriender-name" class="text-xs text-brand-sage font-semibold mt-0.5">With Sarah J.</p>
            </div>
            <form method="POST" action="befriender_network.php" class="space-y-4">
                <input type="hidden" name="action" value="match_befriender">
                <input type="hidden" name="befriender_id" id="modal-befriender-id" value="">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-brand-slate mb-1">Session Date</label>
                        <input type="date" name="session_date" id="modal-session-date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand-slate mb-1">Time Slot</label>
                        <select name="session_time" class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate font-semibold">
                            <option value="09:00 AM">09:00 AM (Morning)</option>
                            <option value="11:30 AM" selected>11:30 AM (Midday)</option>
                            <option value="02:00 PM">02:00 PM (Afternoon)</option>
                            <option value="04:30 PM">04:30 PM (Evening)</option>
                            <option value="07:00 PM">07:00 PM (Night)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-brand-slate mb-1">Preferred Connection Mode</label>
                    <div class="grid grid-cols-2 gap-2 text-xs font-semibold">
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 bg-brand-inputBg cursor-pointer hover:border-brand-sage">
                            <input type="radio" name="session_mode" value="Video Chat" checked class="text-brand-sage">
                            <span>📹 Video Call</span>
                        </label>
                        <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 bg-brand-inputBg cursor-pointer hover:border-brand-sage">
                            <input type="radio" name="session_mode" value="Voice Only" class="text-brand-sage">
                            <span>🎙️ Voice Only</span>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="modal-session-note" class="block text-xs font-semibold text-brand-slate">Topic or notes for your befriender (Optional)</label>
                        <button type="button" onclick="window.TetVoiceToText.toggle('modal-session-note', this)" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-2 py-0.5 rounded-lg border border-brand-sage/10 transition-all select-none">
                            <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                            <span>Voice Notes</span>
                        </button>
                    </div>
                    <textarea name="session_note" id="modal-session-note" rows="2" placeholder="e.g., Feeling overwhelmed with family caregiving, just need a non-judgmental chat..." class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate placeholder-gray-400 resize-none"></textarea>
                </div>

                <div class="pt-2 flex flex-col gap-2">
                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 active:scale-95">
                        Confirm & Schedule Check-in
                    </button>
                    <button type="button" onclick="closeBefrienderBookingModal()" class="w-full py-2 text-center text-xs font-semibold text-gray-400 hover:text-brand-slate transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIDEO ROOM MODAL -->
    <div id="video-conference-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-slate/80 backdrop-blur-md hidden opacity-0 transition-opacity duration-300">
        <div id="video-modal-box" class="relative w-full max-w-4xl h-[85vh] bg-[#1a1e24] rounded-3xl overflow-hidden shadow-2xl border border-white/10 flex flex-col transform scale-95 transition-transform duration-300">
            <div class="px-6 py-4 bg-[#121519] border-b border-white/10 flex items-center justify-between text-white">
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-emerald-400 animate-pulse"></span>
                    <div>
                        <h4 id="video-room-title" class="text-sm font-bold font-outfit text-white">Befriender Live Room</h4>
                        <p id="video-room-subtitle" class="text-[10px] text-white/50">Private Peer Support Session</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="toggleIcebreakerDeck()" class="px-3 py-1.5 rounded-xl bg-purple-900/60 border border-purple-400/30 text-purple-200 hover:bg-purple-800 text-xs font-bold transition-all flex items-center gap-1.5" title="Open Conversation Prompts">
                        <span>💬</span>
                        <span class="hidden sm:inline">Icebreaker Prompts</span>
                    </button>
                    <button type="button" onclick="closeVideoConference()" class="px-3.5 py-1.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white text-xs font-bold transition-all shadow-sm">
                        Leave Call
                    </button>
                </div>
            </div>
            <div class="flex-grow w-full h-full relative bg-black">
                <iframe id="video-conference-frame" src="about:blank" allow="camera; microphone; fullscreen; display-capture; autoplay" class="w-full h-full border-0"></iframe>

                <!-- In-Call Icebreaker Card Deck Overlay -->
                <div id="icebreaker-deck-overlay" class="absolute bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-80 bg-slate-900/95 border border-purple-500/40 p-4 rounded-2xl shadow-2xl backdrop-blur-md hidden z-30 transition-all text-xs text-white space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-700 pb-2">
                        <span class="font-bold text-purple-300">💡 Gentle Conversation Starters</span>
                        <button type="button" onclick="toggleIcebreakerDeck()" class="text-gray-400 hover:text-white">✕</button>
                    </div>
                    <p id="current-icebreaker-text" class="text-xs text-gray-200 italic py-2 leading-relaxed">
                        "What is one small thing that brought you a moment of comfort or peace this week?"
                    </p>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[10px] text-gray-400">Card <span id="prompt-num">1</span> of 5</span>
                        <button type="button" onclick="nextIcebreakerPrompt()" class="px-2.5 py-1 rounded-lg bg-purple-700 hover:bg-purple-600 text-white font-bold text-[10px] transition-all">
                            Next Prompt ➔
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Local alias to window.switchTab defined in head
        function switchTab(tabId) {
            if (typeof window.switchTab === 'function') {
                window.switchTab(tabId);
            }
        }

        function openEmergencyModal() {
            const modal = document.getElementById('emergency-modal');
            const content = modal.querySelector('.relative');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeEmergencyModal() {
            const modal = document.getElementById('emergency-modal');
            const content = modal.querySelector('.relative');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0', 'pointer-events-none');
            }, 200);
        }

        // Befriender Booking Modal Controls
        function openBefrienderBookingModal(id, name) {
            document.getElementById('modal-befriender-id').value = id;
            document.getElementById('modal-befriender-name').textContent = 'With ' + name;
            
            const modal = document.getElementById('befriender-booking-modal');
            const content = modal.querySelector('.relative');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeBefrienderBookingModal() {
            const modal = document.getElementById('befriender-booking-modal');
            const content = modal.querySelector('.relative');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0', 'pointer-events-none');
            }, 200);
        }

        // Peer Search Filter
        function filterPeerList() {
            const query = (document.getElementById('peer-search-filter')?.value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.peer-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const exp = card.getAttribute('data-experiences') || '';
                const lang = card.getAttribute('data-languages') || '';
                const fullText = card.innerText.toLowerCase();

                if (!query || name.includes(query) || exp.includes(query) || lang.includes(query) || fullText.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Icebreaker Card Deck Script
        const icebreakerPrompts = [
            "What is one small thing that brought you a moment of comfort or peace this week?",
            "What has felt slightly heavier than usual recently that you'd like to share?",
            "If you could have an uninterrupted afternoon doing anything gentle, what would you choose?",
            "Who or what usually helps you feel grounded when things get noisy?",
            "What is a kind thought you might offer to someone who is in your exact shoes today?"
        ];
        let currentPromptIndex = 0;

        function toggleIcebreakerDeck() {
            const overlay = document.getElementById('icebreaker-deck-overlay');
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        }

        function nextIcebreakerPrompt() {
            currentPromptIndex = (currentPromptIndex + 1) % icebreakerPrompts.length;
            const textEl = document.getElementById('current-icebreaker-text');
            const numEl = document.getElementById('prompt-num');
            if (textEl) textEl.textContent = `"${icebreakerPrompts[currentPromptIndex]}"`;
            if (numEl) numEl.textContent = currentPromptIndex + 1;
        }

        // Video Room Launchers
        function openVideoConference(roomSlug, roomTitle) {
            const modal = document.getElementById('video-conference-modal');
            const box = document.getElementById('video-modal-box');
            const frame = document.getElementById('video-conference-frame');
            const titleEl = document.getElementById('video-room-title');

            if (titleEl) titleEl.textContent = roomTitle || 'Befriender Live Room';
            if (frame) frame.src = `https://meet.jit.si/${encodeURIComponent(roomSlug)}#config.prejoinPageEnabled=false&userInfo.displayName=${encodeURIComponent('<?php echo htmlspecialchars($user_name); ?>')}`;

            if (modal) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    if (box) {
                        box.classList.remove('scale-95');
                        box.classList.add('scale-100');
                    }
                }, 20);
            }
        }

        function closeVideoConference() {
            const modal = document.getElementById('video-conference-modal');
            const box = document.getElementById('video-modal-box');
            const frame = document.getElementById('video-conference-frame');

            if (box) {
                box.classList.add('scale-95');
                box.classList.remove('scale-100');
            }
            if (modal) {
                modal.classList.add('opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    if (frame) frame.src = 'about:blank';
                }, 300);
            }
        }

        // Global Voice-to-Text Universal Controller for Befriender Network
        window.TetVoiceToText = {
            activeRecognition: null,
            activeButton: null,
            
            toggle: function(targetInputId, btn) {
                const target = document.getElementById(targetInputId);
                if (!target) return;

                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    alert('Speech Recognition is not supported in this browser. Please use Chrome, Edge, or Safari.');
                    return;
                }

                if (this.activeRecognition) {
                    this.activeRecognition.stop();
                    this.activeRecognition = null;
                    if (this.activeButton) {
                        this.resetButton(this.activeButton);
                        this.activeButton = null;
                    }
                    return;
                }

                try {
                    const recognition = new SpeechRecognition();
                    recognition.continuous = true;
                    recognition.interimResults = true;
                    recognition.lang = 'en-US';

                    let originalContent = target.value || '';
                    if (originalContent.length > 0 && !originalContent.endsWith(' ')) {
                        originalContent += ' ';
                    }

                    recognition.onstart = () => {
                        this.setListeningButton(btn);
                    };

                    recognition.onresult = (event) => {
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
                            originalContent += finalTranscript + ' ';
                            target.value = originalContent;
                        } else if (interimTranscript) {
                            target.value = originalContent + interimTranscript;
                        }

                        if (target.id === 'peer-search-filter') {
                            filterPeerList();
                        }
                    };

                    recognition.onerror = () => {
                        this.resetButton(btn);
                        this.activeRecognition = null;
                        this.activeButton = null;
                    };

                    recognition.onend = () => {
                        this.resetButton(btn);
                        this.activeRecognition = null;
                        this.activeButton = null;
                    };

                    this.activeRecognition = recognition;
                    this.activeButton = btn;
                    recognition.start();
                } catch (err) {
                    this.resetButton(btn);
                }
            },

            setListeningButton: function(btn) {
                if (!btn) return;
                btn.dataset.originalHtml = btn.innerHTML;
                btn.classList.add('bg-brand-coral', 'text-white', 'animate-pulse');
                btn.classList.remove('text-brand-sage', 'text-gray-400');
                btn.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-white animate-ping mr-1"></span> 🎙️`;
            },

            resetButton: function(btn) {
                if (!btn) return;
                if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                }
                btn.classList.remove('bg-brand-coral', 'text-white', 'animate-pulse');
                btn.classList.add('text-brand-sage');
            }
        };
    </script>
</body>
</html>
