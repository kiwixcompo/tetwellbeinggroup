<?php
/**
 * Tet Wellbeing Group - Wellbeing Streaming Hub (streaming_hub.php)
 * A premium 'Mental Health Netflix' portal offering guided meditation, sleep therapy,
 * caregiver wellbeing talks, parenting de-escalation, and anxiety management content.
 * Includes inline Web Audio synthesizer streaming and AI recommended queues.
 */

// Initialize database & session
require_once __DIR__ . '/db.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$user_name = $_SESSION['user_name'] ?? 'Staff Member';

// Handle Play Increment via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'play_track') {
    $track_id = intval($_POST['track_id'] ?? 0);
    if ($track_id > 0) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE streaming_content SET plays_count = plays_count + 1 WHERE id = ?");
                $stmt->execute([$track_id]);
            } catch (PDOException $e) {}
        } else {
            foreach ($_SESSION['mock_streaming_content'] as &$c) {
                if ($c['id'] == $track_id) {
                    $c['plays_count']++;
                    break;
                }
            }
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Fetch categories
$categories = [];
if ($db_connected && $pdo) {
    try {
        $categories = $pdo->query("SELECT * FROM streaming_categories")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $categories = $_SESSION['mock_streaming_categories'] ?? [];
    }
} else {
    $categories = $_SESSION['mock_streaming_categories'] ?? [];
}
// Create category maps
$cat_map = [];
$cat_emoji_map = [];
foreach ($categories as $c) {
    $cat_map[$c['id']] = $c['name'];
    $cat_emoji_map[$c['id']] = $c['icon_emoji'];
}

// Fetch content library
$all_content = [];
if ($db_connected && $pdo) {
    try {
        $all_content = $pdo->query("SELECT * FROM streaming_content ORDER BY plays_count DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $all_content = $_SESSION['mock_streaming_content'] ?? [];
    }
} else {
    $all_content = $_SESSION['mock_streaming_content'] ?? [];
}
// Sort by plays descending
usort($all_content, function($a, $b) {
    return $b['plays_count'] - $a['plays_count'];
});

// Calculate AI recommendations based on user biometrics / stress spikes
$rec_category_id = 1; // Default: Guided Meditation
$rec_reason = "Based on standard positive psychology. Centering sessions ground your daily workflow.";
$warnings_list = [];

if ($db_connected && $pdo) {
    try {
        // Query latest telemetry
        $stmt = $pdo->prepare("SELECT sleep_hours, heart_rate_variability FROM user_telemetry_logs WHERE user_id = ? ORDER BY date DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $telemetry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($telemetry) {
            if ($telemetry['sleep_hours'] < 6.0) {
                $rec_category_id = 2; // Sleep Therapy
                $rec_reason = "Recommended due to your recent sleep debt warning (under 6.0 hours sleep).";
                $warnings_list[] = "Sleep Debt detected";
            } elseif ($telemetry['heart_rate_variability'] < 40) {
                $rec_category_id = 5; // Anxiety Management
                $rec_reason = "Recommended due to elevated physical stress indicators (low HRV).";
                $warnings_list[] = "High HRV Stress detected";
            }
        }
    } catch (PDOException $e) {}
} else {
    // Mock user defaults
    $rec_category_id = 2; // Sleep Therapy for Mark
    $rec_reason = "Recommended due to your recent sleep debt warning (under 6.0 hours sleep).";
    $warnings_list[] = "Sleep Debt detected";
}

// Highlight featured track
$featured_track = null;
foreach ($all_content as $item) {
    if ($item['category_id'] == $rec_category_id) {
        $featured_track = $item;
        break;
    }
}
if (!$featured_track && !empty($all_content)) {
    $featured_track = $all_content[0];
}

// Filtered content selection
$active_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$filtered_content = $all_content;
if ($active_filter > 0) {
    $filtered_content = array_filter($all_content, function($item) use ($active_filter) {
        return $item['category_id'] == $active_filter;
    });
}

$active_tab = $_GET['tab'] ?? 'browse';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wellbeing Streaming Hub | Tet Wellbeing</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#0F172A', // Premium dark backdrop
                            card: '#1E293B',
                            slate: '#F1F5F9',
                            sage: '#84A98C',
                            sageLight: '#CAD2C5',
                            coral: '#E07A5F',
                            coralLight: '#FBEBE7',
                            sand: '#F2CC8F',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    boxShadow: {
                        glow: '0 0 25px rgba(132, 169, 140, 0.25)',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        body { background-color: #0F172A; font-family: 'Inter', sans-serif; }
        .glassmorphism {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="text-brand-slate min-h-screen pb-32">

    <!-- NAV BAR -->
    <header class="border-b border-white/5 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <span class="text-xl font-extrabold tracking-tight font-outfit text-white flex items-center gap-2">
                    <object data="logo.svg" type="image/svg+xml" class="h-6 w-6 pointer-events-none filter invert"></object>
                    Tet <span class="text-brand-sage font-medium">Wellbeing</span>
                </span>
            </a>
            
            <div class="flex items-center gap-3">
                <span class="hidden md:inline text-xs font-semibold text-gray-400 bg-slate-800 border border-white/5 px-3.5 py-1.5 rounded-full">
                    🎥 Streaming Account: <strong class="text-brand-slate font-bold"><?php echo htmlspecialchars($user_name); ?></strong>
                </span>
                <a href="logout.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-brand-coral rounded-xl transition-all">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <!-- TITLE BANNER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 border-b border-white/5 pb-6">
            <div>
                <span class="px-3 py-1 rounded-full bg-brand-sage/20 text-brand-sage border border-brand-sage/35 font-bold text-xs uppercase tracking-wider font-outfit">Ecosystem Layer 6</span>
                <h2 class="text-3xl font-extrabold font-outfit text-white mt-2">Wellbeing Streaming Platform</h2>
                <p class="text-sm text-gray-400 mt-1 leading-relaxed max-w-2xl">
                    "Mental Health Netflix" - browse, search, and stream guided meditations, therapeutic talks, and relaxing sleep therapy tracks.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-3 bg-brand-card p-4.5 rounded-2xl border border-white/5">
                <div class="h-10 w-10 rounded-xl bg-brand-sage/15 flex items-center justify-center text-xl">🧘</div>
                <div>
                    <span class="text-[10px] text-gray-400 font-semibold block uppercase">AI Suggested Track</span>
                    <span class="text-xs font-bold text-brand-sage"><?php echo htmlspecialchars($featured_track['title']); ?></span>
                </div>
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <?php include 'nav_menu.php'; ?>

        <!-- HERO CINEMATIC SPOTLIGHT -->
        <?php if ($featured_track): ?>
            <div class="glassmorphism rounded-3xl p-6 md:p-8 flex flex-col md:flex-row justify-between items-center gap-8 mb-12 shadow-glow border border-brand-sage/10 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-brand-sage/5 to-transparent pointer-events-none"></div>
                
                <div class="space-y-4 relative z-10 max-w-2xl">
                    <span class="text-[10px] font-bold tracking-widest uppercase text-brand-sage bg-brand-sage/10 px-3 py-1 rounded-full border border-brand-sage/20">🔥 Recommended for You</span>
                    <h3 class="text-2xl md:text-3xl font-extrabold font-outfit text-white tracking-tight leading-tight"><?php echo htmlspecialchars($featured_track['title']); ?></h3>
                    <p class="text-sm text-gray-300 leading-relaxed"><?php echo htmlspecialchars($featured_track['description']); ?></p>
                    
                    <div class="flex items-center gap-3.5 pt-2 text-xs font-semibold text-gray-400">
                        <span>🕒 <?php echo round($featured_track['duration_seconds'] / 60); ?> Min</span>
                        <span>•</span>
                        <span>🎧 <?php echo htmlspecialchars($cat_map[$featured_track['category_id']] ?? 'Mindfulness'); ?></span>
                        <span>•</span>
                        <span class="text-brand-sage">✨ <?php echo $rec_reason; ?></span>
                    </div>
                </div>

                <div class="relative shrink-0 z-10">
                    <button onclick="playMedia(<?php echo $featured_track['id']; ?>, '<?php echo addslashes($featured_track['title']); ?>', '<?php echo htmlspecialchars($cat_map[$featured_track['category_id']]); ?>', <?php echo $featured_track['duration_seconds']; ?>)" class="h-16 w-16 md:h-20 md:w-20 rounded-full bg-brand-sage text-white flex items-center justify-center shadow-lg transition-all hover:scale-105 active:scale-95 group">
                        <svg class="h-8 w-8 text-slate-900 fill-slate-900 ml-1 transition-transform group-hover:scale-110" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- SUB-TABS NAVIGATION -->
        <div class="flex items-center gap-2 mb-8 text-xs overflow-x-auto whitespace-nowrap pb-2">
            <a href="streaming_hub.php?tab=browse" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'browse') ? 'bg-brand-sage text-white' : 'bg-brand-card text-brand-slate hover:bg-slate-800 border border-white/5'; ?>">Browse Categories</a>
            <a href="streaming_hub.php?tab=all" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'all') ? 'bg-brand-sage text-white' : 'bg-brand-card text-brand-slate hover:bg-slate-800 border border-white/5'; ?>">Full Library (<?php echo count($all_content); ?>)</a>
            <a href="streaming_hub.php?tab=ai" class="px-4 py-2 rounded-xl transition-all font-bold <?php echo ($active_tab === 'ai') ? 'bg-brand-sage text-white' : 'bg-brand-card text-brand-slate hover:bg-slate-800 border border-white/5'; ?>">AI Recommendations</a>
        </div>

        <!-- ==================== TAB: BROWSE CATEGORIES ==================== -->
        <?php if ($active_tab === 'browse'): ?>
            <div class="space-y-8">
                <!-- Categories Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    <?php foreach ($categories as $cat): ?>
                        <a href="streaming_hub.php?tab=all&category=<?php echo $cat['id']; ?>" class="p-6 rounded-3xl bg-brand-card hover:bg-slate-800 border border-white/5 hover:border-brand-sage/20 transition-all flex flex-col justify-between h-40 group">
                            <span class="text-3xl"><?php echo $cat['icon_emoji']; ?></span>
                            <div>
                                <h4 class="font-bold text-sm text-white group-hover:text-brand-sage transition-colors font-outfit"><?php echo htmlspecialchars($cat['name']); ?></h4>
                                <p class="text-[11px] text-gray-400 mt-1 leading-relaxed line-clamp-2"><?php echo htmlspecialchars($cat['description']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        <!-- ==================== TAB: ALL LIBRARY ==================== -->
        <?php elseif ($active_tab === 'all'): ?>
            <div class="space-y-8">
                <!-- Inline Filters -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 whitespace-nowrap text-xs">
                    <a href="streaming_hub.php?tab=all" class="px-3 py-1.5 rounded-lg border <?php echo ($active_filter === 0) ? 'bg-brand-sage/20 border-brand-sage text-brand-sage' : 'border-white/5 text-gray-400 hover:text-white'; ?>">All Tracks</a>
                    <?php foreach ($categories as $c): ?>
                        <a href="streaming_hub.php?tab=all&category=<?php echo $c['id']; ?>" class="px-3 py-1.5 rounded-lg border <?php echo ($active_filter === $c['id']) ? 'bg-brand-sage/20 border-brand-sage text-brand-sage' : 'border-white/5 text-gray-400 hover:text-white'; ?>">
                            <?php echo $c['icon_emoji'] . ' ' . $c['name']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Grid library -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php if (empty($filtered_content)): ?>
                        <div class="col-span-3 text-center py-10 text-gray-400 text-sm">No tracks found matching this filter.</div>
                    <?php else: ?>
                        <?php foreach ($filtered_content as $track): ?>
                            <div class="p-5 rounded-3xl bg-brand-card border border-white/5 flex flex-col justify-between hover:border-brand-sage/25 transition-all group">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-start">
                                        <span class="text-xs font-bold text-brand-sage bg-brand-sage/10 px-2.5 py-0.5 rounded-full border border-brand-sage/20">
                                            <?php echo htmlspecialchars($cat_map[$track['category_id']] ?? 'Mindfulness'); ?>
                                        </span>
                                        <span class="text-[10px] text-gray-400 font-bold">🎧 <?php echo $track['plays_count']; ?> streams</span>
                                    </div>
                                    <h4 class="font-bold text-base text-white font-outfit leading-snug"><?php echo htmlspecialchars($track['title']); ?></h4>
                                    <p class="text-xs text-gray-400 leading-relaxed"><?php echo htmlspecialchars($track['description']); ?></p>
                                </div>
                                
                                <div class="flex justify-between items-center pt-4 border-t border-white/5 mt-4">
                                    <span class="text-xs font-semibold text-gray-500">🕒 <?php echo round($track['duration_seconds'] / 60); ?> Mins</span>
                                    <button onclick="playMedia(<?php echo $track['id']; ?>, '<?php echo addslashes($track['title']); ?>', '<?php echo htmlspecialchars($cat_map[$track['category_id']]); ?>', <?php echo $track['duration_seconds']; ?>)" class="h-9 w-9 rounded-full bg-brand-sage text-slate-900 flex items-center justify-center transition-transform hover:scale-105 active:scale-95">
                                        <svg class="h-4.5 w-4.5 fill-slate-900 ml-0.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <!-- ==================== TAB: AI RECOMMENDATIONS ==================== -->
        <?php elseif ($active_tab === 'ai'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left 2 Cols: Recommendations Queue -->
                <div class="lg:col-span-2 space-y-6">
                    <section class="p-6 rounded-3xl bg-brand-card border border-white/5 shadow-sm space-y-6">
                        <div class="border-b border-white/5 pb-3">
                            <h3 class="font-bold text-lg font-outfit text-white">Your AI Mindfulness Queue</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Custom list constructed by matching biometric indicators and stress forecasting logs.</p>
                        </div>

                        <div class="space-y-4">
                            <?php 
                            $rec_count = 0;
                            foreach ($all_content as $track): 
                                if ($track['category_id'] == $rec_category_id):
                                    $rec_count++;
                            ?>
                                <div class="p-4.5 rounded-2xl bg-slate-900/60 border border-white/5 flex items-center justify-between gap-6 hover:border-brand-sage/20 transition-all">
                                    <div class="space-y-1.5 max-w-lg">
                                        <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 bg-brand-sage/10 text-brand-sage rounded border border-brand-sage/20">
                                            <?php echo htmlspecialchars($cat_emoji_map[$track['category_id']] ?? '🧘') . ' ' . htmlspecialchars($cat_map[$track['category_id']]); ?>
                                        </span>
                                        <h4 class="text-sm font-bold text-white font-outfit"><?php echo htmlspecialchars($track['title']); ?></h4>
                                        <p class="text-xs text-gray-400 leading-relaxed"><?php echo htmlspecialchars($track['description']); ?></p>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-3.5">
                                        <span class="text-xs font-semibold text-gray-500"><?php echo round($track['duration_seconds'] / 60); ?>m</span>
                                        <button onclick="playMedia(<?php echo $track['id']; ?>, '<?php echo addslashes($track['title']); ?>', '<?php echo htmlspecialchars($cat_map[$track['category_id']]); ?>', <?php echo $track['duration_seconds']; ?>)" class="h-9 w-9 rounded-full bg-brand-sage text-slate-900 flex items-center justify-center transition-transform hover:scale-105 active:scale-95">
                                            <svg class="h-4.5 w-4.5 fill-slate-900 ml-0.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            if ($rec_count == 0):
                            ?>
                                <div class="text-center py-8 text-gray-400 text-sm">No specialized recommendations generated for your current telemetry status. Enjoy browsing categories!</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- Right Col: Risk Forecast Guidelines -->
                <div class="space-y-8">
                    <div class="p-6 rounded-3xl bg-brand-card border border-white/5 shadow-sm space-y-3">
                        <h3 class="font-bold text-sm uppercase tracking-wider text-gray-400">Recommendation Triggers</h3>
                        <div class="space-y-4 text-xs text-gray-300 leading-relaxed">
                            <div>
                                <h5 class="font-bold text-white mb-0.5">📊 HRV & HRV Anomalies</h5>
                                <p>If telemetry scans return Low HRV values, exposure audio guidance and relaxation breath rhythms are prioritized.</p>
                            </div>
                            <div>
                                <h5 class="font-bold text-white mb-0.5">💤 Sleep Quality Index</h5>
                                <p>When sleep debt registers under 6 hours, binaural sleep therapy guides and natural soundscapes populate your recommendation list.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <!-- INLINE WEB AUDIO STREAMING PLAYER BAR -->
    <div id="media-player-bar" class="fixed bottom-0 left-0 right-0 z-50 transform translate-y-full transition-transform duration-300 ease-out border-t border-white/10 bg-slate-950 px-6 py-4.5 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
        
        <!-- Track metadata -->
        <div class="flex items-center gap-3.5 max-w-md w-full">
            <div class="h-11 w-11 rounded-xl bg-brand-sage/15 flex items-center justify-center text-xl shrink-0">🎧</div>
            <div class="min-w-0">
                <h4 id="player-title" class="font-bold text-sm text-white truncate font-outfit">Ambient Soundscape</h4>
                <span id="player-category" class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Guided Meditation</span>
            </div>
        </div>

        <!-- Live Wave Visualizer -->
        <div class="hidden sm:flex flex-grow justify-center max-w-sm">
            <canvas id="visual-canvas" width="220" height="35" class="bg-transparent border border-white/5 rounded-lg"></circle>
        </div>

        <!-- Player controls -->
        <div class="flex items-center gap-4 shrink-0">
            <span id="player-time" class="text-xs text-gray-400 font-semibold font-mono">0:00 / 5:00</span>
            
            <button id="player-toggle" onclick="togglePlayback()" class="h-10 w-10 rounded-full bg-brand-sage text-slate-900 flex items-center justify-center shadow-lg transition-transform hover:scale-105 active:scale-95">
                <svg id="toggle-play-icon" class="h-5 w-5 fill-slate-900" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <svg id="toggle-pause-icon" class="h-5 w-5 fill-slate-900 hidden" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
            </button>

            <button onclick="closeMediaPlayer()" class="text-gray-400 hover:text-white transition-colors p-1">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-white/5 bg-slate-950/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-white transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-white transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Journal</span>
            </a>
            <a href="caregiver_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-white transition-colors font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Caregiver</span>
            </a>
            <a href="streaming_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-brand-sage font-medium font-sans">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Streaming</span>
            </a>
        </div>
    </nav>

    <!-- WEB AUDIO SYNTHESIZER AND CANVAS SCRIPTS -->
    <script>
        let currentTrackId = 0;
        let trackDuration = 0;
        let isPlaying = false;
        let currentSeconds = 0;
        let progressInterval = null;

        // Web Audio Variables
        let audioCtx = null;
        let oscillator = null;
        let gainNode = null;
        let filterNode = null;
        let isSynthesizing = false;

        // Canvas Variables
        let canvas = null;
        let ctx = null;
        let animationId = null;

        function playMedia(id, title, category, duration) {
            currentTrackId = id;
            trackDuration = duration;
            currentSeconds = 0;
            
            document.getElementById('player-title').innerText = title;
            document.getElementById('player-category').innerText = category;
            
            // Show Bar
            const playerBar = document.getElementById('media-player-bar');
            playerBar.classList.remove('translate-y-full');

            // Trigger increment play log via background AJAX
            const formData = new FormData();
            formData.append('action', 'play_track');
            formData.append('track_id', id);
            fetch('streaming_hub.php', {
                method: 'POST',
                body: formData
            });

            // Start Audio
            startPlayback();
        }

        function startPlayback() {
            isPlaying = true;
            document.getElementById('toggle-play-icon').classList.add('hidden');
            document.getElementById('toggle-pause-icon').classList.remove('hidden');

            // Trigger synthesized sound
            playAmbientDrone();

            // Start timer
            clearInterval(progressInterval);
            updatePlayerTime();
            progressInterval = setInterval(() => {
                currentSeconds++;
                if (currentSeconds >= trackDuration) {
                    stopPlayback();
                } else {
                    updatePlayerTime();
                }
            }, 1000);

            // Start Canvas Wave
            startCanvasWave();
        }

        function stopPlayback() {
            isPlaying = false;
            document.getElementById('toggle-play-icon').classList.remove('hidden');
            document.getElementById('toggle-pause-icon').classList.add('hidden');
            clearInterval(progressInterval);
            stopAmbientDrone();
            stopCanvasWave();
        }

        function togglePlayback() {
            if (isPlaying) {
                stopPlayback();
            } else {
                startPlayback();
            }
        }

        function updatePlayerTime() {
            const formatTime = (secs) => {
                const m = Math.floor(secs / 60);
                const s = Math.floor(secs % 60);
                return m + ":" + (s < 10 ? "0" + s : s);
            };
            document.getElementById('player-time').innerText = formatTime(currentSeconds) + " / " + formatTime(trackDuration);
        }

        function closeMediaPlayer() {
            stopPlayback();
            document.getElementById('media-player-bar').classList.add('translate-y-full');
        }

        // Web Audio Synthesizer: Soothing low frequency wave drone
        function playAmbientDrone() {
            try {
                if (isSynthesizing) return;
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                
                // Base oscillator
                oscillator = audioCtx.createOscillator();
                gainNode = audioCtx.createGain();
                filterNode = audioCtx.createBiquadFilter();

                // Soothing low frequency (A2 = 110Hz or C3 = 130.81Hz)
                oscillator.type = 'triangle';
                oscillator.frequency.value = 130.81;

                // Modulator for pulse wave effect (simulating breathing swells)
                let lfo = audioCtx.createOscillator();
                let lfoGain = audioCtx.createGain();
                lfo.frequency.value = 0.15; // Slow modulation every 6 seconds
                lfoGain.gain.value = 8; // mod depth

                lfo.connect(lfoGain);
                lfoGain.connect(oscillator.frequency);

                // Low-pass filter to keep it extremely smooth and warm
                filterNode.type = 'lowpass';
                filterNode.frequency.value = 350;

                gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.08, audioCtx.currentTime + 1.5); // Fade-in

                oscillator.connect(filterNode);
                filterNode.connect(gainNode);
                gainNode.connect(audioCtx.destination);

                oscillator.start();
                lfo.start();
                isSynthesizing = true;
            } catch (e) {
                console.error("Web Audio failed to init: ", e);
            }
        }

        function stopAmbientDrone() {
            if (!isSynthesizing) return;
            try {
                if (gainNode && audioCtx) {
                    gainNode.gain.setValueAtTime(gainNode.gain.value, audioCtx.currentTime);
                    gainNode.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 1.0); // Fade-out
                    setTimeout(() => {
                        if (oscillator) {
                            oscillator.stop();
                        }
                        isSynthesizing = false;
                    }, 1050);
                }
            } catch (e) {}
        }

        // Canvas Wave Animation
        function startCanvasWave() {
            canvas = document.getElementById('visual-canvas');
            ctx = canvas.getContext('2d');
            animateWave();
        }

        function animateWave() {
            if (!isPlaying) return;
            animationId = requestAnimationFrame(animateWave);
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            ctx.strokeStyle = 'rgba(132, 169, 140, 0.85)';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            
            const time = Date.now() * 0.005;
            for (let i = 0; i < canvas.width; i++) {
                // Generate layered sine waves for complexity
                const y = canvas.height / 2 + 
                          Math.sin(i * 0.05 + time) * 6 + 
                          Math.cos(i * 0.02 + time * 1.5) * 3;
                if (i === 0) {
                    ctx.moveTo(i, y);
                } else {
                    ctx.lineTo(i, y);
                }
            }
            ctx.stroke();
        }

        function stopCanvasWave() {
            cancelAnimationFrame(animationId);
            if (canvas && ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }
    </script>

</body>
</html>
