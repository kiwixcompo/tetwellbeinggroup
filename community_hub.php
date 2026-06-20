<?php
/**
 * Tet Wellbeing Group - Community & Peer Support Hub (community_hub.php)
 * Anonymous forums, moderated discussion channels, and peer connection layers.
 */
require_once 'db.php';

// Auth Guard: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));

// Fetch archetype
$user_archetype = $_SESSION['user_archetype'] ?? null;
if ($user_archetype === null) {
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT archetype FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_archetype = $stmt->fetchColumn();
            $_SESSION['user_archetype'] = $user_archetype;
        } catch (PDOException $ex) {}
    }
}
if ($user_archetype === null && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $user_archetype = $u['archetype'] ?? null;
            $_SESSION['user_archetype'] = $user_archetype;
            break;
        }
    }
}

// Current Category Channel
$channels = [
    'general' => 'General Chat',
    'caregiver-respite' => 'Respite Support',
    'mindfulness' => 'Meditation Tips',
    'daily-wins' => 'Positivity Log'
];

if ($user_archetype === 'dementia_carer') {
    $channels['dementia-support'] = 'Dementia Care Circle';
} elseif ($user_archetype === 'stressed_student') {
    $channels['student-stress'] = 'Student Anxiety Circle';
}

$current_channel = filter_input(INPUT_GET, 'channel', FILTER_SANITIZE_SPECIAL_CHARS);
if (!array_key_exists($current_channel, $channels)) {
    $current_channel = 'general';
}

$action_success = '';
$action_error = '';

// Handle crisis dismissal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_crisis') {
    $_SESSION['crisis_state'] = 0;
    header("Location: community_hub.php?channel=" . urlencode($current_channel));
    exit;
}

// 1. HANDLE HEART INTERACTION (PRG Pattern)
if (isset($_POST['action']) && $_POST['action'] === 'heart_post') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    if ($post_id) {
        $heart_incremented = false;
        
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE community_posts SET hearts = hearts + 1 WHERE id = ?");
                $stmt->execute([$post_id]);
                $heart_incremented = true;
            } catch (PDOException $ex) {}
        }
        
        // Fallback or session mock update
        if (!$heart_incremented) {
            foreach ($_SESSION['mock_community_posts'] as &$mock_p) {
                if ($mock_p['id'] == $post_id) {
                    $mock_p['hearts']++;
                    break;
                }
            }
        }
        
        // Redirect back to current channel to prevent post resubmission on page refresh
        header("Location: community_hub.php?channel=" . urlencode($current_channel));
        exit;
    }
}

// 2. HANDLE COMPOSING A POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_SPECIAL_CHARS);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if (!empty($content)) {
        $post_saved = false;
        
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO community_posts (user_id, author_name, channel, content, is_anonymous, hearts) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute([$user_id, $user_name, $current_channel, $content, $is_anonymous]);
                $post_saved = true;
                $action_success = "Your post has been published to the channel.";
            } catch (PDOException $ex) {}
        }
        
        // Fallback session write
        if (!$post_saved) {
            $new_post_id = count($_SESSION['mock_community_posts']) + 1;
            $_SESSION['mock_community_posts'][] = [
                'id' => $new_post_id,
                'user_id' => $user_id,
                'author_name' => $user_name,
                'channel' => $current_channel,
                'content' => $content,
                'is_anonymous' => $is_anonymous,
                'hearts' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $action_success = "Your post has been published successfully (Session mock storage).";
        }
    } else {
        $action_error = "Unable to publish empty post content.";
    }
}

// 3. FETCH POSTS FOR ACTIVE CHANNEL
$posts = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM community_posts WHERE channel = ? ORDER BY created_at DESC");
        $stmt->execute([$current_channel]);
        $posts = $stmt->fetchAll();
    } catch (PDOException $ex) {}
}

if (empty($posts) && isset($_SESSION['mock_community_posts'])) {
    $filtered = array_filter($_SESSION['mock_community_posts'], function($p) use ($current_channel) {
        return $p['channel'] === $current_channel;
    });
    // Sort descending by date
    usort($filtered, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });
    $posts = $filtered;
}

// Helper function to show relative time
function getRelativeTime($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return "just now";
    $diff_mins = Math_Round($diff / 60);
    if ($diff_mins < 60) return $diff_mins . "m ago";
    $diff_hours = Math_Round($diff_mins / 60);
    if ($diff_hours < 24) return $diff_hours . "h ago";
    return date('M j, Y', $time);
}

// Simple polyfill for math round in case
function Math_Round($val) {
    return (int)round($val);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Hub - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',       // Soft warm off-white
                            sage: '#5E8C71',     // Calming Sage Green
                            slate: '#264653',    // Deep Slate
                            sky: '#8ECAE6',      // Soft Sky Blue
                            coral: '#E76F51',    // Muted Coral
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
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        'active': '0 12px 24px -6px rgba(94, 140, 113, 0.15)'
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
            -webkit-font-smoothing: antialiased;
        }
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #F7F5F0;
        }
        ::-webkit-scrollbar-thumb {
            background: #D9D5CB;
            border-radius: 9999px;
            border: 2px solid #F7F5F0;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #5E8C71;
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Emergency Support</span>
                </button>

                <!-- Profile Avatar -->
                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <hr class="my-2 border-gray-100">
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-4xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Header / Banner Card (The Unfiltered Circle) -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-lg">
                <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit">Ecosystem Layer 2</p>
                <h1 class="text-3xl md:text-4xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    Community & <span class="text-brand-sage">Peer Support</span>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed mt-1">
                    Connect with others. Share anonymously, find understanding, and build strength together in safe, fully-moderated supportive peer circles.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-56 h-36 md:h-36 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/unfiltered_circle.png" alt="Community Support Group" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold">
            <a href="dashboard.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all">Teletherapy Hub</a>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if (!empty($action_success)): ?>
            <div id="action-toast-s" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Post Shared</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_success; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-s').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
            <div id="action-toast-e" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Notice</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_error; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-e').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- LEFT COLUMN: CHANNEL SELECTOR -->
            <div class="md:col-span-1 space-y-4">
                <div class="bg-white rounded-3xl p-5 shadow-soft border border-[#EBE8E0]">
                    <h3 class="text-sm font-bold text-brand-slate uppercase tracking-wider mb-4 font-outfit">Discussion Channels</h3>
                    
                    <div class="flex flex-row md:flex-col overflow-x-auto md:overflow-x-visible gap-2 pb-2 md:pb-0">
                        <?php foreach ($channels as $slug => $name): ?>
                            <?php $isActive = ($current_channel === $slug); ?>
                            <a 
                                href="community_hub.php?channel=<?php echo urlencode($slug); ?>" 
                                class="shrink-0 flex items-center gap-2 px-4 py-3 rounded-2xl text-xs font-semibold transition-all <?php echo $isActive ? 'bg-brand-sage text-white shadow-soft font-bold' : 'bg-brand-bg text-brand-slate hover:bg-brand-sageLight hover:text-brand-sage'; ?>"
                            >
                                <span class="<?php echo $isActive ? 'text-white' : 'text-brand-sage'; ?>">#</span>
                                <span><?php echo $name; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Guidelines Card -->
                <div class="hidden md:block bg-brand-sageLight/40 rounded-3xl p-5 border border-brand-sage/10 text-xs text-brand-slate leading-relaxed space-y-2">
                    <div class="font-bold text-brand-sage flex items-center gap-1.5">
                        <span>🛡</span>
                        <span>Safe Space Guidelines</span>
                    </div>
                    <p class="text-gray-500 text-[11px]">
                        Please remain respectful, offer support, and maintain confidentiality. Our peer forums are moderated 24/7. Avoid posting personal identifiers (addresses, phone numbers).
                    </p>
                </div>
            </div>

            <!-- RIGHT COLUMN: POST COMPOSER & FEED -->
            <div class="md:col-span-2 space-y-6">
                
                <!-- COMPOSER -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-brand-sage"></div>
                    <h3 class="text-base font-bold font-outfit text-brand-slate mb-3">Share a thought in #<?php echo $channels[$current_channel]; ?></h3>
                    
                    <form method="POST" action="community_hub.php?channel=<?php echo urlencode($current_channel); ?>" class="space-y-4">
                        <input type="hidden" name="action" value="create_post">
                        
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="post-content" class="block text-xs font-semibold text-brand-slate">Your Post Content</label>
                                <button type="button" id="transcribe-post-content" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-2 py-0.5 rounded-lg border border-brand-sage/10 transition-all select-none">
                                    <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                    </svg>
                                    <span>Transcribe Voice</span>
                                </button>
                            </div>
                            <textarea 
                                name="content" 
                                id="post-content"
                                rows="3" 
                                required
                                class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg p-4 text-brand-slate placeholder-gray-400 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 text-xs transition-all"
                                placeholder="What is on your mind?..."
                            ></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <!-- Anonymous Checkbox -->
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_anonymous" class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-0.5">
                                <span class="text-xs text-gray-500 font-semibold">Post Anonymously</span>
                            </label>

                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-soft transition-all active:scale-95">
                                Share Post
                            </button>
                        </div>
                    </form>
                </section>

                <!-- PEER FEED -->
                <section class="space-y-4">
                    <div class="flex items-center justify-between text-xs text-gray-400 font-bold px-1 uppercase tracking-wider">
                        <span>Latest Feed Activity</span>
                        <span>#<?php echo $channels[$current_channel]; ?></span>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($posts)): ?>
                            <div class="bg-white rounded-3xl p-12 text-center border border-[#EBE8E0] text-gray-400 text-xs shadow-soft">
                                <span class="text-3xl block mb-2">💬</span>
                                No threads in this channel yet.<br>Be the first to share a supportive thought!
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <?php 
                                    $anon = (bool)$post['is_anonymous'];
                                    $author = $anon ? "Anonymous Peer" : htmlspecialchars($post['author_name']);
                                    $initial = $anon ? "?" : strtoupper(substr($author, 0, 1));
                                ?>
                                <div class="bg-white rounded-3xl p-5 border border-[#EBE8E0]/70 shadow-soft flex gap-4 transition-all hover:shadow-card">
                                    
                                    <!-- Avatar -->
                                    <div class="shrink-0">
                                        <div class="h-9 w-9 rounded-full flex items-center justify-center font-bold text-xs <?php echo $anon ? 'bg-gray-100 text-gray-400' : 'bg-brand-sageLight text-brand-sage border border-brand-sage/20'; ?>">
                                            <?php echo $initial; ?>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-grow space-y-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-brand-slate"><?php echo $author; ?></span>
                                            <span class="text-gray-400 text-[10px]"><?php echo getRelativeTime($post['created_at']); ?></span>
                                        </div>
                                        
                                        <p class="text-xs text-gray-600 leading-relaxed">
                                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                        </p>

                                        <!-- Footer Actions (Hearts) -->
                                        <div class="flex items-center justify-between pt-2">
                                            <!-- Hearts Form -->
                                            <form method="POST" action="community_hub.php?channel=<?php echo urlencode($current_channel); ?>" class="inline">
                                                <input type="hidden" name="action" value="heart_post">
                                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                                
                                                <button type="submit" class="flex items-center gap-1.5 rounded-full px-3 py-1 bg-brand-bg hover:bg-brand-coralLight hover:text-brand-coral text-gray-400 transition-colors text-[10px] font-bold">
                                                    <svg class="h-3.5 w-3.5 fill-current text-brand-coral" viewBox="0 0 24 24">
                                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                                    </svg>
                                                    <span>Support (<?php echo (int)$post['hearts']; ?>)</span>
                                                </button>
                                            </form>

                                            <!-- Reply button mock -->
                                            <button type="button" class="text-gray-400 hover:text-brand-sage text-[10px] font-semibold transition-colors flex items-center gap-1">
                                                <span>💬 Reply</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </div>

    </main>

    <!-- MOBILE NAVIGATION BAR (Sticky Bottom) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-[#EBE8E0] bg-white/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Journal</span>
            </a>
            <a href="caregiver_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Caregiver</span>
            </a>
            <a href="community_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Community</span>
            </a>
        </div>
    </nav>

    <!-- EMERGENCY SUPPORT MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Crisis Text Line (US/CA)</h4>
                        <p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p>
                    </div>
                    <a href="sms:741741?body=HOME" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-xs text-gray-500">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text SHOUT</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- CLINICAL SAFETY TRIAGE CRISIS MODAL -->
    <?php if (isset($_SESSION['crisis_state']) && $_SESSION['crisis_state'] == 1): ?>
    <div id="crisis-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-md"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/30 transform scale-100 transition-all duration-300">
            
            <div class="flex items-center gap-3.5 mb-5 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-[10px] text-brand-coral font-bold uppercase tracking-wider font-outfit">Un-bypassable safety notice</p>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                Our safety scans detected keywords indicating severe distress in your journal entry. Your life and wellbeing are valuable to us. Please connect with one of these free, confidential support resources immediately:
            </p>

            <div class="space-y-3 mb-6">
                <!-- 988 -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">988 Suicide & Crisis Lifeline</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 988</a>
                </div>

                <!-- Samaritans UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Samaritans Helpline (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 116 123</a>
                </div>

                <!-- Shout UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-3.5 py-1.5 rounded-xl bg-brand-slate text-white font-bold text-[10px] shadow-sm hover:bg-gray-800">Text SHOUT</a>
                </div>
            </div>

            <form method="POST" action="community_hub.php?channel=<?php echo urlencode($current_channel); ?>">
                <input type="hidden" name="action" value="clear_crisis">
                
                <label class="flex items-start gap-2.5 mb-4 cursor-pointer select-none text-[10px] text-gray-500 leading-normal">
                    <input type="checkbox" required class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-0.5">
                    <span>I acknowledge these resources and confirm that I am safe or currently seeking appropriate help.</span>
                </label>

                <button type="submit" class="w-full py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-colors text-center">
                    Acknowledge & Dismiss Modal
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- VANILLA JAVASCRIPT -->
    <script>
        // Emergency Modal actions
        const emergencyModal = document.getElementById('emergency-modal');

        function openEmergencyModal() {
            emergencyModal.classList.remove('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-95');
            emergencyModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEmergencyModal() {
            emergencyModal.classList.add('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-100');
            emergencyModal.querySelector('.relative').classList.add('scale-95');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !emergencyModal.classList.contains('opacity-0')) {
                closeEmergencyModal();
            }
        });

        // Speech Transcription Helper
        function initVoiceTranscription(textareaId, buttonId) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                const btn = document.getElementById(buttonId);
                if (btn) btn.style.display = 'none';
                return;
            }
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            
            const textarea = document.getElementById(textareaId);
            const button = document.getElementById(buttonId);
            let isRecording = false;
            
            button.addEventListener('click', (e) => {
                e.preventDefault();
                if (isRecording) {
                    recognition.stop();
                } else {
                    recognition.start();
                }
            });
            
            recognition.onstart = () => {
                isRecording = true;
                button.innerHTML = `
                    <svg class="h-3.5 w-3.5 text-brand-coral animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                    </svg>
                    <span class="text-brand-coral font-bold">Listening...</span>
                `;
                button.classList.add('bg-brand-coralLight', 'border-brand-coral/20');
            };
            
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                if (textarea.value) {
                    textarea.value += ' ' + transcript;
                } else {
                    textarea.value = transcript;
                }
                textarea.dispatchEvent(new Event('input'));
            };
            
            recognition.onspeechend = () => {
                recognition.stop();
            };
            
            recognition.onend = () => {
                isRecording = false;
                button.innerHTML = `
                    <svg class="h-3 w-3 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                    <span>Transcribe Voice</span>
                `;
                button.classList.remove('bg-brand-coralLight', 'border-brand-coral/20');
            };
            
            recognition.onerror = (event) => {
                console.error("Speech recognition error", event.error);
                recognition.stop();
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            initVoiceTranscription('post-content', 'transcribe-post-content');
        });
    </script>
</body>
</html>
