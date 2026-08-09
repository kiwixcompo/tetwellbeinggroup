<?php
/**
 * Tet Wellbeing Group - Community & Peer Support Hub (community.php)
 * Anonymous forums, moderated discussion channels, and peer connection layers.
 */
require_once 'db.php';
$tab = $_GET['tab'] ?? 'home';

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

// 1. FETCH CIRCLES & CHAMPION ROLE
$all_circles = [];
$my_circle_ids = [];

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM community_circles ORDER BY id ASC");
        $all_circles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_m = $pdo->prepare("SELECT circle_id FROM community_circle_members WHERE user_id = ?");
        $stmt_m->execute([$user_id]);
        $my_circle_ids = $stmt_m->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $ex) {}
}

if (empty($all_circles) && isset($_SESSION['mock_community_circles'])) {
    $all_circles = $_SESSION['mock_community_circles'];
    if (isset($_SESSION['mock_community_circle_members'])) {
        foreach ($_SESSION['mock_community_circle_members'] as $mem) {
            if ($mem['user_id'] == $user_id) {
                $my_circle_ids[] = $mem['circle_id'];
            }
        }
    }
}

// Convert circles to slugs lookup and build $channels list for nav
$channels = [];
$circle_by_slug = [];
foreach ($all_circles as $circle) {
    $circle_by_slug[$circle['slug']] = $circle;
    if (in_array($circle['id'], $my_circle_ids) || $circle['slug'] === 'general') {
        $channels[$circle['slug']] = $circle['name'];
    }
}

$current_channel = filter_input(INPUT_GET, 'channel', FILTER_DEFAULT);
if (!array_key_exists($current_channel, $channels)) {
    if (isset($circle_by_slug[$current_channel])) {
        $channels[$current_channel] = $circle_by_slug[$current_channel]['name'];
    } else {
        $current_channel = 'general';
    }
}
$active_circle = $circle_by_slug[$current_channel] ?? null;

// Determine if user is champion
$user_is_champion = 0;
if ($db_connected && $pdo) {
    try {
        $stmt_c = $pdo->prepare("SELECT is_champion FROM users WHERE id = ?");
        $stmt_c->execute([$user_id]);
        $user_is_champion = (int)$stmt_c->fetchColumn();
    } catch (PDOException $ex) {}
} else if (isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $user_is_champion = (int)($u['is_champion'] ?? 0);
            break;
        }
    }
}

$action_success = '';
$action_error = '';

// Check URL redirect notifications
if (isset($_GET['circle_joined'])) {
    $action_success = "Successfully joined the supportive moderated circle!";
} elseif (isset($_GET['circle_left'])) {
    $action_success = "You have left the circle.";
} elseif (isset($_GET['comment_added'])) {
    $action_success = "Comment shared successfully.";
} elseif (isset($_GET['comment_deleted'])) {
    $action_success = "Comment moderated and removed.";
} elseif (isset($_GET['post_pinned'])) {
    $action_success = $_GET['post_pinned'] == 1 ? "Thread pinned to top successfully." : "Thread unpinned.";
} elseif (isset($_GET['post_deleted'])) {
    $action_success = "Thread moderated and removed.";
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Crisis Dismissal
    if ($action === 'clear_crisis') {
        $_SESSION['crisis_state'] = 0;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET crisis_state = 0 WHERE id = ?");
                $stmt->execute([$user_id]);
            } catch (PDOException $ex) {}
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$mu) {
                if ($mu['id'] == $user_id) {
                    $mu['crisis_state'] = 0;
                }
            }
            unset($mu);
        }
        header("Location: community.php?channel=" . urlencode($current_channel));
        exit;
    }

    // Heart Post
    if ($action === 'heart_post') {
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
            if (!$heart_incremented && isset($_SESSION['mock_community_posts'])) {
                foreach ($_SESSION['mock_community_posts'] as &$mock_p) {
                    if ($mock_p['id'] == $post_id) {
                        $mock_p['hearts']++;
                        break;
                    }
                }
                unset($mock_p);
            }
            header("Location: community.php?channel=" . urlencode($current_channel));
            exit;
        }
    }

    // Create Post
    if ($action === 'create_post') {
        $content = filter_input(INPUT_POST, 'content', FILTER_DEFAULT);
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        if (!empty($content)) {
            $post_saved = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO community_posts (user_id, author_name, channel, content, is_anonymous, hearts, is_pinned) VALUES (?, ?, ?, ?, ?, 0, 0)");
                    $stmt->execute([$user_id, $user_name, $current_channel, $content, $is_anonymous]);
                    $post_saved = true;
                    $action_success = "Your post has been published to the circle.";
                } catch (PDOException $ex) {}
            }
            if (!$post_saved && isset($_SESSION['mock_community_posts'])) {
                $new_post_id = count($_SESSION['mock_community_posts']) + 1;
                $_SESSION['mock_community_posts'][] = [
                    'id' => $new_post_id,
                    'user_id' => $user_id,
                    'author_name' => $user_name,
                    'channel' => $current_channel,
                    'content' => $content,
                    'is_anonymous' => $is_anonymous,
                    'hearts' => 0,
                    'is_pinned' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $action_success = "Your post has been published successfully (Session mock).";
            }
        } else {
            $action_error = "Unable to publish empty post content.";
        }
    }

    // Join Circle
    if ($action === 'join_circle') {
        $circle_id = intval($_POST['circle_id'] ?? 0);
        if ($circle_id > 0) {
            $joined = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO community_circle_members (user_id, circle_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $circle_id]);
                    $joined = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_community_circle_members'])) {
                $exists = false;
                foreach ($_SESSION['mock_community_circle_members'] as $mem) {
                    if ($mem['user_id'] == $user_id && $mem['circle_id'] == $circle_id) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $_SESSION['mock_community_circle_members'][] = ['user_id' => $user_id, 'circle_id' => $circle_id];
                }
                $joined = true;
            }
            if ($joined) {
                $slug = 'general';
                foreach ($all_circles as $c) {
                    if ($c['id'] == $circle_id) {
                        $slug = $c['slug'];
                        break;
                    }
                }
                header("Location: community.php?channel=" . urlencode($slug) . "&circle_joined=1");
                exit;
            }
        }
    }

    // Leave Circle
    if ($action === 'leave_circle') {
        $circle_id = intval($_POST['circle_id'] ?? 0);
        if ($circle_id > 0) {
            $left = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM community_circle_members WHERE user_id = ? AND circle_id = ?");
                    $stmt->execute([$user_id, $circle_id]);
                    $left = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_community_circle_members'])) {
                $_SESSION['mock_community_circle_members'] = array_filter($_SESSION['mock_community_circle_members'], function($mem) use ($user_id, $circle_id) {
                    return !($mem['user_id'] == $user_id && $mem['circle_id'] == $circle_id);
                });
                $left = true;
            }
            if ($left) {
                header("Location: community.php?channel=general&circle_left=1");
                exit;
            }
        }
    }

    // Add Comment
    if ($action === 'add_comment') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        if ($post_id > 0 && !empty($content)) {
            $saved = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO community_replies (post_id, user_id, author_name, content, is_anonymous, is_champion) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$post_id, $user_id, $user_name, $content, $is_anonymous, $user_is_champion]);
                    $saved = true;
                } catch (PDOException $ex) {}
            }
            if (!$saved && isset($_SESSION['mock_community_replies'])) {
                $new_reply_id = count($_SESSION['mock_community_replies']) + 1;
                $_SESSION['mock_community_replies'][] = [
                    'id' => $new_reply_id,
                    'post_id' => $post_id,
                    'user_id' => $user_id,
                    'author_name' => $user_name,
                    'content' => $content,
                    'is_anonymous' => $is_anonymous,
                    'is_champion' => $user_is_champion,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $saved = true;
            }
            if ($saved) {
                header("Location: community.php?channel=" . urlencode($current_channel) . "&comment_added=1");
                exit;
            }
        } else {
            $action_error = "Comment content cannot be empty.";
        }
    }

    // Delete Comment (Champions/Admins only)
    if ($action === 'delete_comment') {
        $reply_id = intval($_POST['reply_id'] ?? 0);
        if ($reply_id > 0 && ($user_is_champion || $_SESSION['user_role'] === 'admin')) {
            $deleted = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM community_replies WHERE id = ?");
                    $stmt->execute([$reply_id]);
                    $deleted = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_community_replies'])) {
                $_SESSION['mock_community_replies'] = array_filter($_SESSION['mock_community_replies'], function($reply) use ($reply_id) {
                    return $reply['id'] != $reply_id;
                });
                $deleted = true;
            }
            if ($deleted) {
                header("Location: community.php?channel=" . urlencode($current_channel) . "&comment_deleted=1");
                exit;
            }
        }
    }

    // Pin Post (Champions/Admins only)
    if ($action === 'pin_post') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $pin_val = intval($_POST['pin_value'] ?? 1);
        if ($post_id > 0 && ($user_is_champion || $_SESSION['user_role'] === 'admin')) {
            $pinned = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE community_posts SET is_pinned = ? WHERE id = ?");
                    $stmt->execute([$pin_val, $post_id]);
                    $pinned = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_community_posts'])) {
                foreach ($_SESSION['mock_community_posts'] as &$p) {
                    if ($p['id'] == $post_id) {
                        $p['is_pinned'] = $pin_val;
                        break;
                    }
                }
                unset($p);
                $pinned = true;
            }
            if ($pinned) {
                header("Location: community.php?channel=" . urlencode($current_channel) . "&post_pinned=" . $pin_val);
                exit;
            }
        }
    }

    // Delete Post (Champions/Admins only)
    if ($action === 'delete_post') {
        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id > 0 && ($user_is_champion || $_SESSION['user_role'] === 'admin')) {
            $deleted = false;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
                    $stmt->execute([$post_id]);
                    $deleted = true;
                } catch (PDOException $ex) {}
            }
            if (isset($_SESSION['mock_community_posts'])) {
                $_SESSION['mock_community_posts'] = array_filter($_SESSION['mock_community_posts'], function($post) use ($post_id) {
                    return $post['id'] != $post_id;
                });
                $deleted = true;
            }
            if ($deleted) {
                header("Location: community.php?channel=" . urlencode($current_channel) . "&post_deleted=1");
                exit;
            }
        }
    }
}

// 3. FETCH POSTS FOR ACTIVE CHANNEL
$posts = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM community_posts WHERE channel = ? ORDER BY is_pinned DESC, created_at DESC");
        $stmt->execute([$current_channel]);
        $posts = $stmt->fetchAll();
    } catch (PDOException $ex) {}
}

if (empty($posts) && isset($_SESSION['mock_community_posts'])) {
    $filtered = array_filter($_SESSION['mock_community_posts'], function($p) use ($current_channel) {
        return $p['channel'] === $current_channel;
    });
    usort($filtered, function($a, $b) {
        $pinA = intval($a['is_pinned'] ?? 0);
        $pinB = intval($b['is_pinned'] ?? 0);
        if ($pinA !== $pinB) {
            return $pinB - $pinA;
        }
        return strcmp($b['created_at'], $a['created_at']);
    });
    $posts = $filtered;
}

// Fetch comments for posts
$replies_by_post = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM community_replies ORDER BY created_at ASC");
        $all_replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_replies as $reply) {
            $replies_by_post[$reply['post_id']][] = $reply;
        }
    } catch (PDOException $ex) {}
} else if (isset($_SESSION['mock_community_replies'])) {
    foreach ($_SESSION['mock_community_replies'] as $reply) {
        $replies_by_post[$reply['post_id']][] = $reply;
    }
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
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Hub - Tet Wellbeing Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <?php include 'nav_menu.php'; ?>

    <!-- MAIN BODY CONTENT (Phase 4 Community Layout) -->
    <main class="flex-grow mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-12 fade-in">
        
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT SIDEBAR (20%) -->
            <div class="w-full lg:w-1/4 xl:w-1/5 shrink-0">
                <div class="sticky top-28 space-y-1">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-4">Community & Care</h3>
                    
                    <a href="?tab=home" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-users w-5 text-center"></i> 
                        Community Feed
                    </a>
                    
                    <a href="?tab=teletherapy" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'teletherapy' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-user-md w-5 text-center"></i> 
                        Teletherapy Services
                    </a>
                    
                    <a href="?tab=befriender" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'befriender' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-handshake-angle w-5 text-center"></i> 
                        Befriender Network
                    </a>

                    <a href="?tab=caregiver" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'caregiver' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <i class="fa-solid fa-hands-holding-child w-5 text-center"></i> 
                        Caregiver Hub
                    </a>
                </div>
            </div>

            <!-- RIGHT CONTENT AREA (80%) -->
            <div class="w-full lg:w-3/4 xl:w-4/5">
                <?php if ($tab === 'home'): ?>
                    <div class="max-w-4xl mx-auto">
                        
        
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

        <!-- Peer Support Video Groups (Jitsi) -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-brand-slate mb-6 font-heading">Peer Support Video Groups</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Group Card 1 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all">
                    <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-500 mb-4 text-xl">
                        <i class="fa-solid fa-mars"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 font-heading">Men's Group</h3>
                    <p class="text-gray-500 mb-4 text-xs">Discuss mental health, challenges, and support.</p>
                    <a href="https://meet.jit.si/tet-wellbeing-mens-group" target="_blank" class="block text-center w-full py-2 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-sm">Join Video Chat</a>
                </div>

                <!-- Group Card 2 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all">
                    <div class="w-12 h-12 bg-pink-100 rounded-2xl flex items-center justify-center text-pink-500 mb-4 text-xl">
                        <i class="fa-solid fa-venus"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 font-heading">Women's Group</h3>
                    <p class="text-gray-500 mb-4 text-xs">Share experiences, empowerment, and wellness.</p>
                    <a href="https://meet.jit.si/tet-wellbeing-womens-group" target="_blank" class="block text-center w-full py-2 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-sm">Join Video Chat</a>
                </div>

                <!-- Group Card 3 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all">
                    <div class="w-12 h-12 bg-brand-sky/20 rounded-2xl flex items-center justify-center text-brand-sky mb-4 text-xl">
                        <i class="fa-solid fa-person-praying"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 font-heading">Loneliness</h3>
                    <p class="text-gray-500 mb-4 text-xs">Connect with others who understand the feeling.</p>
                    <a href="https://meet.jit.si/tet-wellbeing-loneliness" target="_blank" class="block text-center w-full py-2 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-sm">Join Video Chat</a>
                </div>

                <!-- Group Card 4 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all">
                    <div class="w-12 h-12 bg-brand-coralLight rounded-2xl flex items-center justify-center text-brand-coral mb-4 text-xl">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 font-heading">Workplace Stress</h3>
                    <p class="text-gray-500 mb-4 text-xs">Manage burnout and professional exhaustion.</p>
                    <a href="https://meet.jit.si/tet-wellbeing-workplace-stress" target="_blank" class="block text-center w-full py-2 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-sm">Join Video Chat</a>
                </div>
            </div>
        </div>

        <!-- Befriender CTA Block -->
        <section class="bg-brand-slate text-white rounded-3xl p-8 md:p-10 mb-10 flex flex-col md:flex-row items-center justify-between shadow-lg relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-coral rounded-full opacity-20 blur-3xl"></div>
            <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-brand-sky rounded-full opacity-20 blur-3xl"></div>
            
            <div class="z-10 mb-6 md:mb-0 md:pr-8">
                <h2 class="text-3xl font-bold mb-2 font-heading">Need a 1-on-1 chat?</h2>
                <p class="text-white/80 max-w-xl text-sm md:text-base">Sometimes a personal conversation is what you need most. Match with a befriender today and talk in a safe, non-judgmental space.</p>
            </div>
            <div class="z-10 w-full md:w-auto shrink-0">
                <?php if (isset($_SESSION['user_subscription_plan']) && $_SESSION['user_subscription_plan'] !== 'free'): ?>
                <button onclick="showToast('Match request sent! A befriender will be in touch soon.')" class="inline-block w-full md:w-auto text-center px-8 py-4 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1">
                    Find a Befriender
                </button>
                <?php else: ?>
                <a href="subscription.php" class="inline-block w-full md:w-auto text-center px-8 py-4 bg-gray-500 hover:bg-gray-600 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1">
                    Upgrade to Unlock
                </a>
                <?php endif; ?>
            </div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- LEFT COLUMN: CHANNEL SELECTOR -->
            <div class="md:col-span-1 space-y-4">
                <div class="bg-white rounded-3xl p-5 shadow-soft border border-[#EBE8E0]">
                    <h3 class="text-sm font-bold text-brand-slate uppercase tracking-wider mb-4 font-outfit">My Support Circles</h3>
                    
                    <div class="flex flex-row md:flex-col overflow-x-auto md:overflow-x-visible gap-2 pb-2 md:pb-0">
                        <?php foreach ($channels as $slug => $name): ?>
                            <?php $isActive = ($current_channel === $slug); ?>
                            <a 
                                href="community.php?channel=<?php echo urlencode($slug); ?>" 
                                class="shrink-0 flex items-center gap-2 px-4 py-3 rounded-2xl text-xs font-semibold transition-all <?php echo $isActive ? 'bg-brand-sage text-white shadow-soft font-bold' : 'bg-brand-bg text-brand-slate hover:bg-brand-sageLight hover:text-brand-sage'; ?>"
                            >
                                <span><?php echo $name; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" onclick="openDiscoverModal()" class="w-full mt-4 flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brand-sage/35 hover:border-brand-sage bg-[#FAF9F6] px-4 py-2.5 text-xs font-bold text-brand-sage transition-all hover:bg-brand-sageLight active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Discover Circles</span>
                    </button>
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

                <!-- ACTIVE CIRCLE HEADER -->
                <?php if ($active_circle): ?>
                <div class="bg-white rounded-3xl p-6 border border-[#EBE8E0] shadow-soft space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold font-outfit text-brand-slate leading-tight"><?php echo htmlspecialchars($active_circle['name']); ?></h2>
                                <span class="text-[9px] bg-brand-sageLight text-brand-sage font-extrabold uppercase px-2 py-0.5 rounded-full whitespace-nowrap">
                                    <?php echo htmlspecialchars($active_circle['category']); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed"><?php echo htmlspecialchars($active_circle['description']); ?></p>
                        </div>

                        <!-- Trained Champion facilitator details -->
                        <?php if (!empty($active_circle['champion_name'])): ?>
                        <div class="shrink-0 flex items-center gap-2 bg-brand-sageLight/50 px-3 py-2 rounded-2xl border border-brand-sage/10 text-xs">
                            <div class="h-8 w-8 rounded-full bg-brand-sage text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-sm">
                                <?php echo htmlspecialchars($active_circle['champion_avatar'] ?? 'T'); ?>
                            </div>
                            <div>
                                <div class="font-bold text-brand-slate leading-tight text-[11px]"><?php echo htmlspecialchars($active_circle['champion_name']); ?></div>
                                <div class="text-[8px] text-brand-sage font-extrabold uppercase tracking-wider mt-0.5">Trained Facilitator</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Join/Leave status controls -->
                    <div class="flex items-center justify-between border-t border-[#EBE8E0]/60 pt-3 text-xs">
                        <span class="text-gray-400 font-bold text-[10px] uppercase tracking-wider">
                            <?php 
                                $isJoined = in_array($active_circle['id'], $my_circle_ids); 
                                echo $isJoined ? '✅ Member' : '👀 Preview Mode';
                            ?>
                        </span>
                        
                        <?php if ($active_circle['slug'] !== 'general'): ?>
                            <?php if ($isJoined): ?>
                                <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>">
                                    <input type="hidden" name="action" value="leave_circle">
                                    <input type="hidden" name="circle_id" value="<?php echo (int)$active_circle['id']; ?>">
                                    <button type="submit" class="px-4 py-1.5 rounded-xl border border-brand-coral/30 hover:border-brand-coral bg-white hover:bg-brand-coralLight text-brand-coral font-bold text-[10px] uppercase tracking-wider transition-all active:scale-95">
                                        Leave Circle
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>">
                                    <input type="hidden" name="action" value="join_circle">
                                    <input type="hidden" name="circle_id" value="<?php echo (int)$active_circle['id']; ?>">
                                    <button type="submit" class="px-5 py-1.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-[10px] uppercase tracking-wider shadow-sm transition-all active:scale-95">
                                        Join Circle
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                    $isJoinedOrGeneral = ($current_channel === 'general' || in_array($active_circle['id'] ?? 0, $my_circle_ids));
                ?>

                <?php if (!$isJoinedOrGeneral): ?>
                    <!-- JOIN CIRCLE BANNER -->
                    <div class="bg-white rounded-3xl p-8 shadow-soft border border-[#EBE8E0] text-center space-y-4 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-brand-coral"></div>
                        <span class="text-4xl block">🤝</span>
                        <h3 class="font-extrabold font-outfit text-brand-slate text-sm">Join #<?php echo htmlspecialchars($active_circle['name']); ?> to post</h3>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                            This is a moderated peer circle. You are currently viewing it in preview mode. To publish posts or reply to comments, please join this circle.
                        </p>
                        <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>">
                            <input type="hidden" name="action" value="join_circle">
                            <input type="hidden" name="circle_id" value="<?php echo (int)$active_circle['id']; ?>">
                            <button type="submit" class="px-6 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs uppercase tracking-wider shadow-soft transition-all active:scale-95">
                                Join Support Circle
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- COMPOSER -->
                    <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0] relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-brand-sage"></div>
                        <h3 class="text-base font-bold font-outfit text-brand-slate mb-3">Share a thought in #<?php echo $channels[$current_channel]; ?></h3>
                        
                        <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="space-y-4">
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
                <?php endif; ?>

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
                                    $isPinned = (bool)($post['is_pinned'] ?? false);
                                ?>
                                <div class="bg-white rounded-3xl p-5 border <?php echo $isPinned ? 'border-brand-sage/40 ring-1 ring-brand-sage/10 bg-brand-sageLight/5' : 'border-[#EBE8E0]/70'; ?> shadow-soft flex gap-4 transition-all hover:shadow-card relative">
                                    
                                    <?php if ($isPinned): ?>
                                        <div class="absolute top-2.5 right-3 bg-brand-sageLight border border-brand-sage/20 text-brand-sage text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-md flex items-center gap-1 shadow-sm select-none">
                                            📌 Pinned
                                        </div>
                                    <?php endif; ?>

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

                                        <!-- Footer Actions (Hearts + Moderation) -->
                                        <div class="flex items-center justify-between pt-2 border-t border-gray-100/50">
                                            <div class="flex items-center gap-2">
                                                <!-- Hearts Form -->
                                                <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline">
                                                    <input type="hidden" name="action" value="heart_post">
                                                    <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                                    
                                                    <button type="submit" class="flex items-center gap-1.5 rounded-full px-3 py-1 bg-brand-bg hover:bg-brand-coralLight hover:text-brand-coral text-gray-400 transition-colors text-[10px] font-bold">
                                                        <svg class="h-3.5 w-3.5 fill-current text-brand-coral" viewBox="0 0 24 24">
                                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                                        </svg>
                                                        <span>Support (<?php echo (int)$post['hearts']; ?>)</span>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Moderation buttons -->
                                            <?php if ($user_is_champion || $_SESSION['user_role'] === 'admin'): ?>
                                                <div class="flex items-center gap-2 pl-3">
                                                    <!-- Pin/Unpin Action -->
                                                    <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline">
                                                        <input type="hidden" name="action" value="pin_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                                        <input type="hidden" name="pin_value" value="<?php echo $post['is_pinned'] ? 0 : 1; ?>">
                                                        <button type="submit" class="text-brand-sage hover:text-brand-sageHover text-[10px] font-bold transition-colors">
                                                            <?php echo $post['is_pinned'] ? '📍 Unpin' : '📌 Pin'; ?>
                                                        </button>
                                                    </form>

                                                    <span class="text-gray-200">|</span>

                                                    <!-- Delete Action -->
                                                    <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this thread?');">
                                                        <input type="hidden" name="action" value="delete_post">
                                                        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                                        <button type="submit" class="text-brand-coral hover:text-brand-coralHover text-[10px] font-bold transition-colors">
                                                            🗑️ Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- COMMENTS SECTION -->
                                        <?php 
                                            $post_replies = $replies_by_post[$post['id']] ?? [];
                                        ?>
                                        <?php if (!empty($post_replies)): ?>
                                            <div class="mt-4 space-y-2.5 border-l-2 border-brand-sage/20 pl-3">
                                                <?php foreach ($post_replies as $reply): ?>
                                                    <?php 
                                                        $r_anon = (bool)$reply['is_anonymous'];
                                                        $r_author = $r_anon ? "Anonymous Peer" : htmlspecialchars($reply['author_name']);
                                                        $r_initial = $r_anon ? "?" : strtoupper(substr($r_author, 0, 1));
                                                        $r_is_champion = (bool)$reply['is_champion'];
                                                    ?>
                                                    <div class="p-3 rounded-2xl text-xs space-y-1 relative <?php echo $r_is_champion ? 'bg-amber-50/20 border border-amber-200/50 ring-1 ring-amber-100/10' : 'bg-brand-bg/50 border border-gray-100/70'; ?>">
                                                        
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="h-5 w-5 rounded-full flex items-center justify-center font-bold text-[9px] <?php echo $r_anon ? 'bg-gray-100 text-gray-400' : 'bg-brand-sageLight text-brand-sage border border-brand-sage/10'; ?>">
                                                                    <?php echo $r_initial; ?>
                                                                </span>
                                                                <span class="font-bold text-brand-slate text-[11px]"><?php echo $r_author; ?></span>
                                                                <?php if ($r_is_champion): ?>
                                                                    <span class="bg-amber-100 border border-amber-200 text-amber-800 text-[8px] font-extrabold uppercase px-1.5 py-0.5 rounded shadow-sm select-none">
                                                                        ⭐ Champion Facilitator
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-gray-400 text-[9px]"><?php echo getRelativeTime($reply['created_at']); ?></span>
                                                                
                                                                <?php if ($user_is_champion || $_SESSION['user_role'] === 'admin'): ?>
                                                                    <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline" onsubmit="return confirm('Are you sure you want to delete this reply?');">
                                                                        <input type="hidden" name="action" value="delete_comment">
                                                                        <input type="hidden" name="reply_id" value="<?php echo (int)$reply['id']; ?>">
                                                                        <button type="submit" class="text-brand-coral hover:text-brand-coralHover text-[9px] font-bold transition-colors">
                                                                            🗑️
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <p class="text-gray-600 pl-6 leading-relaxed"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- INLINE REPLY FORM -->
                                        <?php if ($isJoinedOrGeneral): ?>
                                            <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="mt-4 pt-3 border-t border-gray-100/50 flex gap-2.5 items-end">
                                                <input type="hidden" name="action" value="add_comment">
                                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                                <div class="flex-grow">
                                                    <textarea name="content" rows="1" required class="w-full rounded-xl border border-gray-200 bg-brand-inputBg px-3 py-2 text-xs text-brand-slate placeholder-gray-400 focus:border-brand-sage focus:outline-none focus:ring-1 focus:ring-brand-sage/20 transition-all resize-none" placeholder="Write a supportive reply..."></textarea>
                                                    <div class="flex items-center justify-between mt-1">
                                                        <label class="flex items-center gap-1 cursor-pointer select-none text-[10px] text-gray-400 font-semibold">
                                                            <input type="checkbox" name="is_anonymous" class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage w-3 h-3">
                                                            <span>Reply Anonymously</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <button type="submit" class="px-4 py-2 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-[11px] shadow-sm transition-all shrink-0">
                                                    Reply
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </div>

    
                    </div>
                
                <?php elseif ($tab === 'teletherapy'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="teletherapy_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'befriender'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="befriender_network.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'caregiver'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="caregiver_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-20">
                        <h2 class="text-2xl font-bold text-brand-slate mb-4">Module Not Found</h2>
                        <a href="?tab=home" class="text-brand-sage underline">Return to Community</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

<div id="toast-container" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2"></div>
<script>
function showToast(message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'bg-brand-sage text-white px-6 py-3 rounded-xl shadow-xl transform transition-all duration-300 translate-y-full opacity-0 font-outfit text-sm font-semibold flex items-center gap-2';
    toast.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> ${message}`;
    container.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-y-full', 'opacity-0');
    }, 10);
    
    // Animate out and remove
    setTimeout(() => {
        toast.classList.add('translate-y-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>
</body>

</html>
