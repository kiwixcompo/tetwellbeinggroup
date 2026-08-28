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
                $return_url = filter_input(INPUT_POST, 'return_url', FILTER_DEFAULT);
                if ($return_url) {
                    header("Location: " . $return_url . (strpos($return_url, '?') !== false ? '&' : '?') . "circle_joined=1");
                } else {
                    $slug = 'general';
                    foreach ($all_circles as $c) {
                        if ($c['id'] == $circle_id) {
                            $slug = $c['slug'];
                            break;
                        }
                    }
                    header("Location: community.php?channel=" . urlencode($slug) . "&circle_joined=1");
                }
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
                $return_url = filter_input(INPUT_POST, 'return_url', FILTER_DEFAULT);
                if ($return_url) {
                    header("Location: " . $return_url . (strpos($return_url, '?') !== false ? '&' : '?') . "circle_left=1");
                } else {
                    header("Location: community.php?channel=general&circle_left=1");
                }
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
<?php require_once 'gating_helper.php'; ?>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-4">Community & Care</h3>
                    
                    <a href="?tab=home" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-users w-5 text-center"></i> 
                            Community Feed
                        </span>
                        <?php echo getPlanBadge('free'); ?>
                    </a>
                    
                    <a href="?tab=teletherapy" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'teletherapy' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-user-md w-5 text-center"></i> 
                            Teletherapy Services
                        </span>
                        <?php echo getPlanBadge('premium'); ?>
                    </a>
                    
                    <a href="?tab=befriender" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'befriender' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-handshake-angle w-5 text-center"></i> 
                            Befriender Network
                        </span>
                        <?php echo getPlanBadge('basic'); ?>
                    </a>

                    <a href="?tab=caregiver" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'caregiver' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-hands-holding-child w-5 text-center"></i> 
                            Caregiver Hub
                        </span>
                        <?php echo getPlanBadge('free'); ?>
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

        <!-- Peer Support Video Groups (WebRTC / Interactive Conferencing) -->
        <div class="mb-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-brand-slate font-heading">Peer Support Video Conferencing</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Encrypted, peer-to-peer video rooms for live discussions, mindfulness meetups, and group support.</p>
                </div>
                <button onclick="startInstantCircleVideoRoom()" class="flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold transition-all shadow-md active:scale-95">
                    <i class="fa-solid fa-video"></i>
                    <span>Start Custom Video Room</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Group Card 1 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-500 mb-4 text-xl">
                            <i class="fa-solid fa-mars"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1 font-heading text-brand-slate">Men's Group</h3>
                        <p class="text-gray-500 mb-4 text-xs">Discuss mental health, challenges, and mutual support.</p>
                    </div>
                    <button onclick="openVideoConference('tet-wellbeing-mens-group', 'Men\'s Support Circle')" class="block text-center w-full py-2.5 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-xs font-outfit shadow-sm">
                        <i class="fa-solid fa-video mr-1.5"></i> Join Video Chat
                    </button>
                </div>

                <!-- Group Card 2 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-pink-100 rounded-2xl flex items-center justify-center text-pink-500 mb-4 text-xl">
                            <i class="fa-solid fa-venus"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1 font-heading text-brand-slate">Women's Group</h3>
                        <p class="text-gray-500 mb-4 text-xs">Share experiences, empowerment, and wellness.</p>
                    </div>
                    <button onclick="openVideoConference('tet-wellbeing-womens-group', 'Women\'s Support Circle')" class="block text-center w-full py-2.5 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-xs font-outfit shadow-sm">
                        <i class="fa-solid fa-video mr-1.5"></i> Join Video Chat
                    </button>
                </div>

                <!-- Group Card 3 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-brand-sky/20 rounded-2xl flex items-center justify-center text-brand-sky mb-4 text-xl">
                            <i class="fa-solid fa-person-praying"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1 font-heading text-brand-slate">Loneliness</h3>
                        <p class="text-gray-500 mb-4 text-xs">Connect with others who understand the journey.</p>
                    </div>
                    <button onclick="openVideoConference('tet-wellbeing-loneliness', 'Overcoming Loneliness Circle')" class="block text-center w-full py-2.5 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-xs font-outfit shadow-sm">
                        <i class="fa-solid fa-video mr-1.5"></i> Join Video Chat
                    </button>
                </div>

                <!-- Group Card 4 -->
                <div class="bg-white p-6 rounded-3xl shadow-soft border border-[#EBE8E0] hover:border-brand-sage/50 transition-all flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 bg-brand-coralLight rounded-2xl flex items-center justify-center text-brand-coral mb-4 text-xl">
                            <i class="fa-solid fa-briefcase"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1 font-heading text-brand-slate">Workplace Stress</h3>
                        <p class="text-gray-500 mb-4 text-xs">Manage burnout, work-life balance, and exhaustion.</p>
                    </div>
                    <button onclick="openVideoConference('tet-wellbeing-workplace-stress', 'Workplace Stress & Burnout Circle')" class="block text-center w-full py-2.5 bg-brand-sage/10 text-brand-sage rounded-xl font-bold hover:bg-brand-sage hover:text-white transition-colors text-xs font-outfit shadow-sm">
                        <i class="fa-solid fa-video mr-1.5"></i> Join Video Chat
                    </button>
                </div>
            </div>
        </div>

        <!-- Befriender CTA Block -->
        <section class="bg-brand-slate text-white rounded-3xl p-8 md:p-10 mb-10 flex flex-col md:flex-row items-center justify-between shadow-lg relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-coral rounded-full opacity-20 blur-3xl"></div>
            <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-brand-sky rounded-full opacity-20 blur-3xl"></div>
            
            <div class="z-10 mb-6 md:mb-0 md:pr-8">
                <div class="inline-flex items-center gap-2 mb-2">
                    <?php echo getPlanBadge('basic'); ?>
                </div>
                <h2 class="text-3xl font-bold mb-2 font-heading">Need a 1-on-1 chat?</h2>
                <p class="text-white/80 max-w-xl text-sm md:text-base">Sometimes a personal conversation is what you need most. Match with a befriender today and talk in a safe, non-judgmental space.</p>
            </div>
            <div class="z-10 w-full md:w-auto shrink-0">
                <button onclick="openBefrienderSelectionModal()" class="inline-block w-full md:w-auto text-center px-8 py-4 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1">
                    Find a Befriender
                </button>
            </div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- LEFT COLUMN: CHANNEL SELECTOR -->
            <div class="md:col-span-1 space-y-4">
                <div class="bg-white rounded-3xl p-5 shadow-soft border border-[#EBE8E0]">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-bold text-brand-slate uppercase tracking-wider font-outfit">My Support Circles</h3>
                        <span class="text-[10px] font-bold text-brand-sage bg-brand-sageLight px-2 py-0.5 rounded-full"><?php echo count($channels); ?> Active</span>
                    </div>
                    
                    <div class="flex flex-col gap-1.5 max-h-[420px] overflow-y-auto pr-1">
                        <?php foreach ($all_circles as $circ): ?>
                            <?php 
                                $isJoined = in_array($circ['id'], $my_circle_ids) || $circ['slug'] === 'general';
                                $isActive = ($current_channel === $circ['slug']);
                                if (!$isJoined && count($my_circle_ids) > 0 && !$isActive) {
                                    // Only show joined circles in primary sidebar, but list all in Discover modal
                                    // or show top ones with quick join button
                                }
                            ?>
                            <div class="flex items-center justify-between p-1.5 rounded-2xl transition-all <?php echo $isActive ? 'bg-brand-sage text-white shadow-soft font-bold' : ($isJoined ? 'bg-brand-bg hover:bg-brand-sageLight/60 text-brand-slate' : 'bg-gray-50/70 hover:bg-gray-100 text-gray-400'); ?>">
                                <a 
                                    href="community.php?channel=<?php echo urlencode($circ['slug']); ?>" 
                                    class="flex items-center gap-2 flex-grow truncate px-2 py-1.5 text-xs font-semibold"
                                    title="Click to view <?php echo htmlspecialchars($circ['name']); ?>"
                                >
                                    <span class="opacity-70 text-[11px]">#</span>
                                    <span class="truncate"><?php echo htmlspecialchars($circ['name']); ?></span>
                                </a>
                                
                                <div class="flex items-center gap-1 shrink-0 ml-1">
                                    <!-- Preview Button -->
                                    <button 
                                        type="button" 
                                        onclick="openCirclePreview(<?php echo (int)$circ['id']; ?>)" 
                                        class="p-1.5 rounded-xl text-[11px] <?php echo $isActive ? 'text-white/80 hover:text-white hover:bg-white/20' : 'text-gray-400 hover:text-brand-sage hover:bg-white'; ?> transition-colors"
                                        title="Preview Circle Details"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <!-- Join / Leave Button -->
                                    <?php if ($circ['slug'] !== 'general'): ?>
                                        <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline m-0">
                                            <input type="hidden" name="action" value="<?php echo $isJoined ? 'leave_circle' : 'join_circle'; ?>">
                                            <input type="hidden" name="circle_id" value="<?php echo (int)$circ['id']; ?>">
                                            <input type="hidden" name="return_url" value="community.php?channel=<?php echo urlencode($current_channel); ?>">
                                            <button 
                                                type="submit" 
                                                class="px-2.5 py-1 rounded-xl text-[10px] font-bold transition-all <?php echo $isJoined ? ($isActive ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-brand-sage/10 text-brand-sage hover:bg-brand-sage hover:text-white') : 'bg-brand-coral hover:bg-brand-coralHover text-white shadow-xs'; ?>"
                                                title="<?php echo $isJoined ? 'Click to Leave Circle' : 'Click to Join Circle'; ?>"
                                            >
                                                <?php echo $isJoined ? 'Joined' : '+ Join'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" onclick="openDiscoverModal()" class="w-full mt-4 flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brand-sage/35 hover:border-brand-sage bg-[#FAF9F6] px-4 py-2.5 text-xs font-bold text-brand-sage transition-all hover:bg-brand-sageLight active:scale-95">
                        <i class="fa-solid fa-compass text-sm"></i>
                        <span>Explore All Support Circles</span>
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
                                    <button type="button" id="transcribe-post-content" onclick="window.TetVoiceToText.toggle('post-content', this)" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover bg-brand-sageLight/50 px-2 py-0.5 rounded-lg border border-brand-sage/10 transition-all select-none">
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

<!-- BEFRIENDER SELECTION MODAL (Step 3 & 7) -->
<div id="befriender-selection-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-gray-100 transform transition-all scale-95 duration-300 relative overflow-hidden" id="befriender-modal-box">
        
        <!-- Step 1: Companion Type Selection -->
        <div id="befriender-step-type">
            <div class="flex items-center justify-between mb-4">
                <span class="px-3 py-1 rounded-full bg-brand-coralLight text-brand-coral text-xs font-bold font-outfit uppercase tracking-wider">Companion Match</span>
                <button type="button" onclick="closeBefrienderSelectionModal()" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <h3 class="text-2xl font-extrabold font-outfit text-brand-slate mb-2">What kind of companion are you looking for?</h3>
            <p class="text-xs text-gray-500 mb-6">Choose between an authentic conversational AI Befriender or a verified human peer volunteer.</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <!-- Option A: AI Befriender -->
                <button type="button" onclick="selectBefrienderType('ai')" class="flex flex-col items-center text-center p-5 rounded-2xl border-2 border-brand-coral/20 bg-brand-coralLight/20 hover:border-brand-coral hover:bg-brand-coralLight/50 transition-all group active:scale-95 text-left">
                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-tr from-rose-400 to-brand-coral text-white flex items-center justify-center text-2xl mb-3 shadow-md group-hover:scale-110 transition-transform">
                        <span class="text-xl">👩 👨</span>
                    </div>
                    <h4 class="font-bold text-base text-brand-slate font-outfit mb-1 text-center">AI Befriender (Maya & Alex)</h4>
                    <p class="text-[11px] text-gray-500 leading-snug text-center">Choose between Maya (warm & caring) or Alex (grounded & friendly) for authentic 1-on-1 peer chats.</p>
                    <span class="mt-3 text-xs font-bold text-brand-coral flex items-center justify-center gap-1 w-full">Chat with AI Befriender &rarr;</span>
                </button>

                <!-- Option B: Human Befriender -->
                <button type="button" onclick="selectBefrienderType('human')" class="flex flex-col items-center text-center p-5 rounded-2xl border-2 border-brand-sage/20 bg-brand-sageLight/30 hover:border-brand-sage hover:bg-brand-sageLight transition-all group active:scale-95 text-left">
                    <div class="h-14 w-14 rounded-2xl bg-brand-sage text-white flex items-center justify-center text-2xl mb-3 shadow-md group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-handshake-angle"></i>
                    </div>
                    <h4 class="font-bold text-base text-brand-slate font-outfit mb-1 text-center">Human Befriender</h4>
                    <p class="text-[11px] text-gray-500 leading-snug text-center">Connect 1-on-1 with a trained volunteer from your support circles.</p>
                    <span class="mt-3 text-xs font-bold text-brand-sage flex items-center justify-center gap-1 w-full">Match With Peer &rarr;</span>
                </button>
            </div>
        </div>

        <!-- Step 2A: Circle Requirement Check (If user has NO circles) -->
        <div id="befriender-step-nocircles" class="hidden text-center py-2">
            <div class="h-16 w-16 bg-brand-coralLight text-brand-coral rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-people-roof"></i>
            </div>
            <h3 class="text-xl font-bold font-outfit text-brand-slate mb-2">Join a Circle First</h3>
            <p class="text-xs text-gray-500 max-w-sm mx-auto mb-6 leading-relaxed">
                To match you with the best like-minded peer, our system matches you based on shared Circles (such as Men's Group, Student Life, or Caregivers). Please join at least one Circle to proceed!
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="backToBefrienderType()" class="flex-1 py-3 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50">Back</button>
                <button type="button" onclick="closeBefrienderSelectionModal(); openDiscoverModal();" class="flex-1 py-3 bg-brand-sage text-white font-bold rounded-xl text-xs hover:bg-brand-sageHover shadow-md">Discover Circles</button>
            </div>
        </div>

        <!-- Step 2B: Human Preferences & Filters (If user HAS circles) -->
        <div id="befriender-step-preferences" class="hidden">
            <div class="flex items-center justify-between mb-4">
                <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage text-xs font-bold font-outfit uppercase tracking-wider">Peer Matching Criteria</span>
                <button type="button" onclick="closeBefrienderSelectionModal()" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <h3 class="text-xl font-bold font-outfit text-brand-slate mb-1">Set Your Match Preferences</h3>
            <p class="text-xs text-gray-500 mb-4">We will match you with a verified befriender based on your criteria.</p>

            <form onsubmit="submitHumanBefrienderMatch(event)" class="space-y-3.5">
                <div>
                    <label class="block text-xs font-bold text-brand-slate mb-1">Select Linked Circle / Focus</label>
                    <select id="pref-circle" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none" required>
                        <?php 
                        $hasCustomCircle = false;
                        foreach ($all_circles as $c): 
                            if ($c['slug'] !== 'general' && in_array($c['id'], $my_circle_ids)): 
                                $hasCustomCircle = true;
                        ?>
                                <option value="<?php echo htmlspecialchars($c['name']); ?>">#<?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['category']); ?>)</option>
                        <?php 
                            endif; 
                        endforeach; 
                        if (!$hasCustomCircle):
                        ?>
                            <option value="General Support">General Community Circle</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-brand-slate mb-1">Preferred Gender</label>
                        <select id="pref-gender" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none">
                            <option value="No Preference">No Preference</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Non-Binary">Non-Binary</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brand-slate mb-1">Age Range</label>
                        <select id="pref-age" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none">
                            <option value="Any">Any Age Range</option>
                            <option value="18-25">18–25 years</option>
                            <option value="26-40">26–40 years</option>
                            <option value="41-60">41–60 years</option>
                            <option value="60+">60+ years</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-brand-slate mb-1">Culture / Background</label>
                        <select id="pref-culture" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none">
                            <option value="Any / No Preference">Any / No Preference</option>
                            <option value="British / European">British / European</option>
                            <option value="African (West/East/South)">African (West/East/South)</option>
                            <option value="South Asian (Pakistani/Indian/Bengali)">South Asian</option>
                            <option value="Caribbean">Caribbean</option>
                            <option value="East / Southeast Asian">East / Southeast Asian</option>
                            <option value="Middle Eastern / Arab">Middle Eastern / Arab</option>
                            <option value="Latin American">Latin American</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-brand-slate mb-1">Primary Language</label>
                        <select id="pref-language" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none">
                            <option value="English">English</option>
                            <option value="Spanish">Spanish</option>
                            <option value="Urdu">Urdu</option>
                            <option value="Arabic">Arabic</option>
                            <option value="Yoruba">Yoruba</option>
                            <option value="Hausa">Hausa</option>
                            <option value="Igbo">Igbo</option>
                            <option value="French">French</option>
                            <option value="Mandarin">Mandarin</option>
                            <option value="Portuguese">Português</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-brand-slate mb-1">Communication Mode</label>
                    <select id="pref-mode" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-brand-inputBg text-brand-slate focus:border-brand-sage focus:outline-none">
                        <option value="Video Call">Live Video Call</option>
                        <option value="Audio Only">Voice Call</option>
                        <option value="Text Chat">Instant Messaging</option>
                    </select>
                </div>

                <div class="pt-3 flex gap-3">
                    <button type="button" onclick="backToBefrienderType()" class="w-1/3 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl text-xs hover:bg-gray-50">Back</button>
                    <button type="submit" class="w-2/3 py-2.5 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl text-xs shadow-md transition-all">Submit Match Request</button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- DISCOVER SUPPORT CIRCLES MODAL -->
<div id="discover-circles-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-3xl w-full max-h-[90vh] shadow-2xl border border-gray-100 transform transition-all scale-95 duration-300 relative overflow-hidden flex flex-col" id="discover-modal-box">
        
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-gray-100 shrink-0">
            <div>
                <span class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage text-[10px] font-bold uppercase tracking-wider font-outfit">Community Support</span>
                <h3 class="text-2xl font-extrabold font-outfit text-brand-slate mt-1">Explore Support Circles</h3>
                <p class="text-xs text-gray-500">Find moderated safe spaces dedicated to your life stage and challenges.</p>
            </div>
            <button type="button" onclick="closeDiscoverModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-xl hover:bg-gray-50">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Search & Filter Bar -->
        <div class="py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 shrink-0">
            <div class="relative flex-grow">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-gray-400 text-xs"></i>
                <input 
                    type="text" 
                    id="circle-search-input"
                    oninput="filterDiscoverCircles()"
                    placeholder="Search by topic, keyword, or focus..." 
                    class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-200 bg-brand-inputBg text-brand-slate placeholder-gray-400 focus:border-brand-sage focus:outline-none"
                >
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-hide text-xs shrink-0">
                <button type="button" onclick="setCircleCategoryFilter('all', this)" class="circle-cat-btn px-3 py-1.5 rounded-xl bg-brand-slate text-white font-bold text-[11px] transition-all">All</button>
                <button type="button" onclick="setCircleCategoryFilter('Caregiving', this)" class="circle-cat-btn px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-[11px] hover:bg-gray-200 transition-all">Caregiving</button>
                <button type="button" onclick="setCircleCategoryFilter('Mindfulness', this)" class="circle-cat-btn px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-[11px] hover:bg-gray-200 transition-all">Mindfulness</button>
                <button type="button" onclick="setCircleCategoryFilter('Workplace', this)" class="circle-cat-btn px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-[11px] hover:bg-gray-200 transition-all">Workplace</button>
                <button type="button" onclick="setCircleCategoryFilter('Anxiety', this)" class="circle-cat-btn px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-[11px] hover:bg-gray-200 transition-all">Anxiety</button>
            </div>
        </div>

        <!-- Circles Grid -->
        <div id="discover-circles-grid" class="flex-grow overflow-y-auto py-4 space-y-3 pr-1">
            <?php foreach ($all_circles as $circ): ?>
                <?php 
                    $isJoined = in_array($circ['id'], $my_circle_ids) || $circ['slug'] === 'general';
                ?>
                <div class="circle-card p-4 rounded-2xl border border-gray-100 bg-white hover:border-brand-sage/30 hover:shadow-soft transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4" data-name="<?php echo htmlspecialchars(strtolower($circ['name'])); ?>" data-category="<?php echo htmlspecialchars($circ['category']); ?>" data-desc="<?php echo htmlspecialchars(strtolower($circ['description'])); ?>">
                    <div class="space-y-1 max-w-lg">
                        <div class="flex items-center gap-2">
                            <a href="community.php?channel=<?php echo urlencode($circ['slug']); ?>" class="font-bold text-sm text-brand-slate hover:text-brand-sage transition-colors font-outfit" title="View Circle Feed">
                                #<?php echo htmlspecialchars($circ['name']); ?>
                            </a>
                            <span class="text-[9px] font-bold uppercase tracking-wider bg-brand-sageLight text-brand-sage px-2 py-0.5 rounded-full">
                                <?php echo htmlspecialchars($circ['category']); ?>
                            </span>
                            <?php if ($circ['slug'] === 'general'): ?>
                                <span class="text-[9px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Default</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed"><?php echo htmlspecialchars($circ['description']); ?></p>
                        <?php if (!empty($circ['champion_name'])): ?>
                            <p class="text-[10px] text-gray-400 font-semibold">
                                <i class="fa-solid fa-user-shield text-brand-sage mr-1"></i> Facilitator: <?php echo htmlspecialchars($circ['champion_name']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end pt-2 sm:pt-0 border-t sm:border-t-0 border-gray-50">
                        <!-- Preview Button -->
                        <button 
                            type="button" 
                            onclick="openCirclePreview(<?php echo (int)$circ['id']; ?>)" 
                            class="px-3.5 py-2 rounded-xl border border-gray-200 text-gray-600 hover:border-brand-sage hover:text-brand-sage text-xs font-bold transition-all flex items-center gap-1.5"
                        >
                            <i class="fa-solid fa-eye text-xs"></i>
                            <span>Preview</span>
                        </button>

                        <!-- Join / Leave Button (Stays on community page) -->
                        <?php if ($circ['slug'] !== 'general'): ?>
                            <form method="POST" action="community.php?channel=<?php echo urlencode($current_channel); ?>" class="inline m-0">
                                <input type="hidden" name="action" value="<?php echo $isJoined ? 'leave_circle' : 'join_circle'; ?>">
                                <input type="hidden" name="circle_id" value="<?php echo (int)$circ['id']; ?>">
                                <input type="hidden" name="return_url" value="community.php?channel=<?php echo urlencode($current_channel); ?>">
                                <button 
                                    type="submit" 
                                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 <?php echo $isJoined ? 'bg-brand-sageLight text-brand-sage hover:bg-brand-sage hover:text-white' : 'bg-brand-coral hover:bg-brand-coralHover text-white shadow-md'; ?>"
                                >
                                    <i class="fa-solid <?php echo $isJoined ? 'fa-check' : 'fa-plus'; ?> text-xs"></i>
                                    <span><?php echo $isJoined ? 'Joined' : 'Join Circle'; ?></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="community.php?channel=general" class="px-4 py-2 rounded-xl bg-brand-sage text-white text-xs font-bold shadow-xs">
                                View
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- CIRCLE PREVIEW MODAL -->
<div id="circle-preview-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-gray-100 transform transition-all scale-95 duration-300 relative overflow-hidden" id="circle-preview-box">
        
        <div class="flex items-center justify-between mb-4">
            <span id="preview-circle-category" class="px-3 py-1 rounded-full bg-brand-sageLight text-brand-sage text-[10px] font-bold uppercase tracking-wider font-outfit">Category</span>
            <button type="button" onclick="closeCirclePreview()" class="text-gray-400 hover:text-gray-600 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <h3 id="preview-circle-name" class="text-2xl font-extrabold font-outfit text-brand-slate mb-2">Circle Name</h3>
        <p id="preview-circle-desc" class="text-xs text-gray-600 leading-relaxed mb-6">Circle detailed description will appear here.</p>

        <!-- Circle Facilitator & Safety Box -->
        <div class="bg-brand-bg rounded-2xl p-4 border border-gray-200/80 mb-6 space-y-3 text-xs">
            <div class="flex items-center gap-3">
                <div id="preview-circle-avatar" class="h-10 w-10 rounded-full bg-brand-sage text-white font-bold flex items-center justify-center text-sm shrink-0">
                    M
                </div>
                <div>
                    <span class="text-[10px] text-gray-400 font-bold uppercase block">Facilitator</span>
                    <h4 id="preview-circle-champion" class="font-bold text-brand-slate text-sm">Marcus Vance, LCSW</h4>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200 flex items-center justify-between text-[11px] text-gray-500">
                <span>🛡 24/7 Moderated Safe Space</span>
                <span>🔒 Anonymous Posting Enabled</span>
            </div>
        </div>

        <!-- Action Controls -->
        <div class="flex gap-3">
            <a id="preview-circle-view-btn" href="community.php" class="flex-1 py-3 text-center rounded-xl border border-gray-200 text-brand-slate font-bold text-xs hover:bg-gray-50 transition-all">
                View Discussions
            </a>

            <form id="preview-circle-form" method="POST" action="community.php" class="flex-1 m-0">
                <input type="hidden" name="action" id="preview-form-action" value="join_circle">
                <input type="hidden" name="circle_id" id="preview-form-circle-id" value="1">
                <input type="hidden" name="return_url" value="community.php">
                <button type="submit" id="preview-form-submit-btn" class="w-full py-3 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl text-xs shadow-md transition-all">
                    Join Circle
                </button>
            </form>
        </div>

    </div>
</div>

<!-- IN-APP VIDEO CONFERENCING MODAL (Step 10) -->
<div id="video-conference-modal" class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-slate-900/80 backdrop-blur-md hidden opacity-0 transition-opacity duration-300">
    <div class="bg-slate-950 w-full max-w-5xl h-[88vh] rounded-3xl shadow-2xl border border-slate-800 flex flex-col overflow-hidden transform scale-95 transition-all duration-300" id="video-modal-box">
        <!-- Video Header -->
        <div class="bg-slate-900/90 border-b border-slate-800 px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <span class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <div>
                    <h3 id="video-room-title" class="font-bold text-sm font-outfit text-white">Community Video Room</h3>
                    <p id="video-room-subtitle" class="text-[10px] text-gray-400 font-mono">Encrypted Peer-to-Peer Session</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button onclick="copyVideoRoomLink()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-gray-300 text-xs font-bold transition-all flex items-center gap-1.5" title="Copy Invite Link">
                    <i class="fa-solid fa-link text-[11px]"></i>
                    <span class="hidden sm:inline">Copy Link</span>
                </button>
                <button onclick="openExternalVideoRoom()" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-gray-300 text-xs transition-all" title="Open in New Window">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </button>
                <button onclick="closeVideoConference()" class="p-2 rounded-xl bg-brand-coral/20 hover:bg-brand-coral text-brand-coral hover:text-white transition-all ml-2" title="Leave & Close Video">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Video Iframe Embed Container -->
        <div class="flex-grow bg-slate-950 relative overflow-hidden">
            <iframe id="video-conference-frame" class="w-full h-full border-0" allow="camera; microphone; display-capture; autoplay; clipboard-write"></iframe>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2"></div>

<script>
const allCirclesMap = <?php echo json_encode($all_circles); ?>;
const myCircleIds = <?php echo json_encode($my_circle_ids); ?>;
const userJoinedCirclesCount = <?php echo count($my_circle_ids); ?>;

/* Befriender Selection Modal Handlers */
function openBefrienderSelectionModal() {
    const modal = document.getElementById('befriender-selection-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    backToBefrienderType();
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        const box = document.getElementById('befriender-modal-box');
        if (box) {
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }
    }, 20);
}

function closeBefrienderSelectionModal() {
    const modal = document.getElementById('befriender-selection-modal');
    if (!modal) return;
    const box = document.getElementById('befriender-modal-box');
    if (box) {
        box.classList.add('scale-95');
        box.classList.remove('scale-100');
    }
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function selectBefrienderType(type) {
    if (type === 'ai') {
        closeBefrienderSelectionModal();
        window.location.href = 'ai_befriender.php';
    } else if (type === 'human') {
        const stepType = document.getElementById('befriender-step-type');
        if (stepType) stepType.classList.add('hidden');
        if (userJoinedCirclesCount === 0) {
            const noCirc = document.getElementById('befriender-step-nocircles');
            if (noCirc) noCirc.classList.remove('hidden');
        } else {
            const pref = document.getElementById('befriender-step-preferences');
            if (pref) pref.classList.remove('hidden');
        }
    }
}

function backToBefrienderType() {
    const noCirc = document.getElementById('befriender-step-nocircles');
    const pref = document.getElementById('befriender-step-preferences');
    const stepType = document.getElementById('befriender-step-type');
    if (noCirc) noCirc.classList.add('hidden');
    if (pref) pref.classList.add('hidden');
    if (stepType) stepType.classList.remove('hidden');
}

function submitHumanBefrienderMatch(e) {
    e.preventDefault();
    const circle = document.getElementById('pref-circle') ? document.getElementById('pref-circle').value : 'Support Circle';
    const gender = document.getElementById('pref-gender') ? document.getElementById('pref-gender').value : 'Any';
    const age = document.getElementById('pref-age') ? document.getElementById('pref-age').value : 'Any';
    const culture = document.getElementById('pref-culture') ? document.getElementById('pref-culture').value : 'Any';
    const lang = document.getElementById('pref-language') ? document.getElementById('pref-language').value : 'English';
    const mode = document.getElementById('pref-mode') ? document.getElementById('pref-mode').value : 'Video Call';

    closeBefrienderSelectionModal();
    showToast(`Peer match request submitted for ${circle} (${gender}, ${age}, ${culture}, ${lang}, ${mode})! A verified befriender will reach out soon.`);
}

/* Discover Circles Modal Handlers */
function openDiscoverModal() {
    const modal = document.getElementById('discover-circles-modal');
    if (!modal) return;
    const box = document.getElementById('discover-modal-box');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        if (box) {
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }
    }, 20);
}

function closeDiscoverModal() {
    const modal = document.getElementById('discover-circles-modal');
    if (!modal) return;
    const box = document.getElementById('discover-modal-box');
    if (box) {
        box.classList.add('scale-95');
        box.classList.remove('scale-100');
    }
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

let activeCatFilter = 'all';
function setCircleCategoryFilter(cat, btn) {
    activeCatFilter = cat;
    document.querySelectorAll('.circle-cat-btn').forEach(b => {
        b.className = 'circle-cat-btn px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 font-semibold text-[11px] hover:bg-gray-200 transition-all';
    });
    btn.className = 'circle-cat-btn px-3 py-1.5 rounded-xl bg-brand-slate text-white font-bold text-[11px] transition-all';
    filterDiscoverCircles();
}

function filterDiscoverCircles() {
    const query = (document.getElementById('circle-search-input')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('#discover-circles-grid .circle-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const cat = card.dataset.category || '';
        const desc = card.dataset.desc || '';
        
        const matchesQuery = !query || name.includes(query) || desc.includes(query) || cat.toLowerCase().includes(query);
        const matchesCat = (activeCatFilter === 'all' || cat.toLowerCase() === activeCatFilter.toLowerCase());

        if (matchesQuery && matchesCat) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

/* Circle Preview Modal Handlers */
function openCirclePreview(circleId) {
    const circleObj = allCirclesMap.find(c => Number(c.id) === Number(circleId));
    if (!circleObj) return;

    const isJoined = myCircleIds.includes(Number(circleId)) || myCircleIds.includes(String(circleId)) || circleObj.slug === 'general';

    const nameEl = document.getElementById('preview-circle-name');
    const catEl = document.getElementById('preview-circle-category');
    const descEl = document.getElementById('preview-circle-desc');
    const champEl = document.getElementById('preview-circle-champion');
    const avatarEl = document.getElementById('preview-circle-avatar');
    const viewBtn = document.getElementById('preview-circle-view-btn');

    if (nameEl) nameEl.textContent = '#' + circleObj.name;
    if (catEl) catEl.textContent = circleObj.category || 'General';
    if (descEl) descEl.textContent = circleObj.description || '';
    if (champEl) champEl.textContent = circleObj.champion_name || 'Trained Facilitator';
    if (avatarEl) avatarEl.textContent = (circleObj.champion_avatar || (circleObj.champion_name ? circleObj.champion_name.charAt(0) : 'T')).toUpperCase();
    if (viewBtn) viewBtn.href = 'community.php?channel=' + encodeURIComponent(circleObj.slug);

    const formActionEl = document.getElementById('preview-form-action');
    const circleIdEl = document.getElementById('preview-form-circle-id');
    const submitBtn = document.getElementById('preview-form-submit-btn');

    if (circleIdEl) circleIdEl.value = circleObj.id;
    if (formActionEl && submitBtn) {
        if (isJoined || circleObj.slug === 'general') {
            formActionEl.value = 'leave_circle';
            submitBtn.textContent = 'Leave Circle';
            submitBtn.className = 'w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl text-xs transition-all';
            if (circleObj.slug === 'general') {
                submitBtn.style.display = 'none';
            } else {
                submitBtn.style.display = 'block';
            }
        } else {
            formActionEl.value = 'join_circle';
            submitBtn.textContent = 'Join This Circle';
            submitBtn.className = 'w-full py-3 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl text-xs shadow-md transition-all';
            submitBtn.style.display = 'block';
        }
    }

    const modal = document.getElementById('circle-preview-modal');
    if (!modal) return;
    const box = document.getElementById('circle-preview-box');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        if (box) {
            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }
    }, 20);
}

function closeCirclePreview() {
    const modal = document.getElementById('circle-preview-modal');
    if (!modal) return;
    const box = document.getElementById('circle-preview-box');
    if (box) {
        box.classList.add('scale-95');
        box.classList.remove('scale-100');
    }
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

let currentActiveRoomSlug = 'tet-wellbeing-general';

function openVideoConference(roomSlug, roomTitle) {
    currentActiveRoomSlug = roomSlug;
    const modal = document.getElementById('video-conference-modal');
    const box = document.getElementById('video-modal-box');
    const frame = document.getElementById('video-conference-frame');
    const titleEl = document.getElementById('video-room-title');
    const subEl = document.getElementById('video-room-subtitle');

    if (titleEl) titleEl.textContent = roomTitle || 'Circle Video Room';
    if (subEl) subEl.textContent = `Room: #${roomSlug}`;
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

function openExternalVideoRoom() {
    window.open(`https://meet.jit.si/${encodeURIComponent(currentActiveRoomSlug)}`, '_blank');
}

function copyVideoRoomLink() {
    const link = `https://meet.jit.si/${encodeURIComponent(currentActiveRoomSlug)}`;
    navigator.clipboard.writeText(link).then(() => {
        showToast('Room link copied to clipboard! Share it with your circle peers.');
    }).catch(() => {
        showToast('Link: ' + link);
    });
}

function startInstantCircleVideoRoom() {
    const circleName = prompt('Enter a name for your custom Circle Video Room:', 'Mindfulness Check-in');
    if (circleName && circleName.trim()) {
        const safeSlug = 'tet-' + circleName.toLowerCase().replace(/[^a-z0-9]/g, '-') + '-' + Math.floor(Math.random() * 8999 + 1000);
        openVideoConference(safeSlug, circleName);
    }
}

function showToast(message) {
    const container = document.getElementById('toast-container');
    if (!container) return;
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
    }, 3500);
}
</script>
</body>

</html>
