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
    if ($befriender_id) {
        $befriender = null;
        foreach ($_SESSION['mock_befrienders'] as $b) {
            if ($b['id'] == $befriender_id) {
                $befriender = $b;
                break;
            }
        }
        if ($befriender) {
            $_SESSION['mock_befriender_sessions'][] = [
                'id' => time() . rand(100,999),
                'user_id' => $user_id,
                'befriender_id' => $befriender_id,
                'befriender_name' => $befriender['name'],
                'date' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'mode' => 'Video Chat',
                'status' => 'scheduled'
            ];
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
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate bg-brand-bg">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <!-- Emergency Support Button -->
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span class="hidden sm:inline">Emergency Support</span>
                </button>

                <!-- User Profile -->
                <div class="relative group">
                    <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-sageLight text-brand-sage font-bold shadow-sm transition-transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-brand-sage focus:ring-offset-2">
                        <?php echo $user_initial; ?>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white py-2 shadow-card ring-1 ring-black ring-opacity-5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                        <div class="px-4 py-2 border-b border-gray-50">
                            <p class="text-sm font-semibold text-brand-slate truncate"><?php echo htmlspecialchars($user_name); ?></p>
                        </div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-brand-coral hover:bg-brand-coralLight/50 transition-colors">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Layout -->
    <main class="flex-grow">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">
            
            <?php include 'nav_menu.php'; ?>

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
                    <h1 class="font-outfit text-4xl sm:text-5xl font-bold tracking-tight mb-4">Befriender Support Network</h1>
                    <p class="text-lg sm:text-xl text-brand-sageLight max-w-2xl mx-auto opacity-90">No one should have to face life's challenges alone. Connect with peers who understand your journey.</p>
                </div>
            </div>

            <!-- Custom Tabs Navigation -->
            <div class="flex gap-2 overflow-x-auto border-b border-gray-200 pb-px mb-8 scrollbar-hide">
                <button onclick="switchTab('match_me')" id="tab-match_me" class="px-6 py-3 font-outfit font-semibold text-sm rounded-t-xl transition-all border-b-2 border-brand-sage text-brand-sage bg-white shadow-sm">Match Me</button>
                <button onclick="switchTab('my_checkins')" id="tab-my_checkins" class="px-6 py-3 font-outfit font-medium text-sm rounded-t-xl transition-all border-b-2 border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50">My Check-ins</button>
                <button onclick="switchTab('coffee_chat')" id="tab-coffee_chat" class="px-6 py-3 font-outfit font-medium text-sm rounded-t-xl transition-all border-b-2 border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50">Coffee & Conversation</button>
                <button onclick="switchTab('academy')" id="tab-academy" class="px-6 py-3 font-outfit font-medium text-sm rounded-t-xl transition-all border-b-2 border-transparent text-gray-500 hover:text-brand-slate hover:bg-white/50">Academy</button>
            </div>

            <!-- Tab 1: Match Me -->
            <div id="content-match_me" class="tab-content active">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Filters -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl shadow-card p-6 sticky top-28">
                            <h2 class="text-xl font-outfit font-bold text-brand-slate mb-6">Find Your Match</h2>
                            <form method="GET" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Language</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all">
                                        <option>Any Language</option>
                                        <option>English</option>
                                        <option>Spanish</option>
                                        <option>Urdu</option>
                                        <option>Mandarin</option>
                                        <option>Portuguese</option>
                                        <option>Punjabi</option>
                                        <option>French</option>
                                        <option>Arabic</option>
                                        <option>Hindi</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Shared Experience</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all">
                                        <option>Any Experience</option>
                                        <option>Caregiving</option>
                                        <option>Bereavement</option>
                                        <option>Student Life</option>
                                        <option>Veteran</option>
                                        <option>Career Transition</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Gender Preference</label>
                                    <select class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all">
                                        <option>No Preference</option>
                                        <option>Female</option>
                                        <option>Male</option>
                                        <option>Non-binary</option>
                                    </select>
                                </div>
                                <button type="button" class="w-full rounded-xl bg-brand-sage py-3 text-sm font-bold text-white transition-all hover:bg-brand-sageHover hover:shadow-md mt-4">
                                    Filter Matches
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Available Befrienders -->
                    <div class="lg:col-span-2 space-y-6">
                        <?php foreach ($_SESSION['mock_befrienders'] as $befriender): ?>
                            <div class="bg-white rounded-2xl shadow-card p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6 border border-transparent hover:border-brand-sageLight transition-colors">
                                <div class="h-16 w-16 bg-brand-sageLight rounded-full flex items-center justify-center text-brand-sage font-bold text-2xl shrink-0">
                                    <?php echo substr($befriender['name'], 0, 1); ?>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-xl font-bold text-brand-slate"><?php echo htmlspecialchars($befriender['name']); ?></h3>
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
                                    </div>
                                </div>
                                <form method="POST" class="w-full sm:w-auto shrink-0">
                                    <input type="hidden" name="action" value="match_befriender">
                                    <input type="hidden" name="befriender_id" value="<?php echo $befriender['id']; ?>">
                                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-brand-slate text-white text-sm font-semibold hover:bg-opacity-90 transition-all shadow-md">
                                        Schedule Check-in
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tab 2: My Check-ins -->
            <div id="content-my_checkins" class="tab-content">
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
                                            </div>
                                            <span class="px-3 py-1 bg-brand-sageLight text-brand-sage text-xs font-bold rounded-full border border-brand-sage/20"><?php echo htmlspecialchars($session['mode']); ?></span>
                                        </div>
                                        <div class="flex gap-3 mt-6">
                                            <form method="POST" class="flex-1">
                                                <input type="hidden" name="action" value="join_conversation">
                                                <button type="submit" class="w-full px-4 py-2 bg-brand-sage text-white rounded-xl text-sm font-semibold hover:bg-brand-sageHover transition-colors shadow-sm">Join Now</button>
                                            </form>
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
            <div id="content-coffee_chat" class="tab-content">
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
            <div id="content-academy" class="tab-content">
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
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Why do you want to become a befriender?</label>
                                <textarea rows="4" required class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-4 py-3 text-sm focus:border-brand-sage focus:ring-1 focus:ring-brand-sage outline-none transition-all resize-none" placeholder="Share a little bit about your life experience and motivation..."></textarea>
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

    <!-- Scripts -->
    <script>
        function switchTab(tabId) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Reset all tab styles
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('border-brand-sage', 'text-brand-sage', 'bg-white', 'shadow-sm');
                el.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected content
            document.getElementById('content-' + tabId).classList.add('active');
            // Style selected tab
            const tabBtn = document.getElementById('tab-' + tabId);
            tabBtn.classList.remove('border-transparent', 'text-gray-500');
            tabBtn.classList.add('border-brand-sage', 'text-brand-sage', 'bg-white', 'shadow-sm');
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
    </script>
</body>
</html>
