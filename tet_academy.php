<?php
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'home';

// Handle Toggle / Mark Lesson Completed via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lesson_completed') {
    header('Content-Type: application/json');
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $status = intval($_POST['status'] ?? 1); // 1 = complete, 0 = incomplete

    if ($lesson_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Lesson ID']);
        exit;
    }

    if ($db_connected && $pdo) {
        try {
            if ($status === 1) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO academy_user_progress (user_id, lesson_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $lesson_id]);
                $completed = true;
            } else {
                $stmt = $pdo->prepare("DELETE FROM academy_user_progress WHERE user_id = ? AND lesson_id = ?");
                $stmt->execute([$user_id, $lesson_id]);
                $completed = false;
            }

            // Sync user activity count in session
            if (!isset($_SESSION['user_activity_count'][$user_id])) {
                $_SESSION['user_activity_count'][$user_id] = 1;
            } else {
                $_SESSION['user_activity_count'][$user_id]++;
            }

            // Get updated count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_user_progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $completed_count = intval($stmt->fetchColumn());

            $total_count = intval($pdo->query("SELECT COUNT(*) FROM academy_lessons")->fetchColumn());

            echo json_encode([
                'success' => true,
                'completed' => $completed,
                'completed_count' => $completed_count,
                'total_count' => $total_count,
                'percentage' => $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        if (!isset($_SESSION['mock_academy_user_progress'])) {
            $_SESSION['mock_academy_user_progress'] = [];
        }

        $found_index = -1;
        foreach ($_SESSION['mock_academy_user_progress'] as $idx => $prog) {
            if ($prog['user_id'] == $user_id && $prog['lesson_id'] == $lesson_id) {
                $found_index = $idx;
                break;
            }
        }

        if ($status === 1 && $found_index === -1) {
            $_SESSION['mock_academy_user_progress'][] = [
                'user_id' => $user_id,
                'lesson_id' => $lesson_id
            ];
            $completed = true;
        } elseif ($status === 0 && $found_index !== -1) {
            unset($_SESSION['mock_academy_user_progress'][$found_index]);
            $_SESSION['mock_academy_user_progress'] = array_values($_SESSION['mock_academy_user_progress']);
            $completed = false;
        } else {
            $completed = ($found_index !== -1);
        }

        $completed_count = 0;
        foreach ($_SESSION['mock_academy_user_progress'] as $prog) {
            if ($prog['user_id'] == $user_id) $completed_count++;
        }
        $total_count = count($_SESSION['mock_academy_lessons'] ?? []);

        echo json_encode([
            'success' => true,
            'completed' => $completed,
            'completed_count' => $completed_count,
            'total_count' => $total_count,
            'percentage' => $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0
        ]);
    }
    exit;
}

// Handle Auto-Sync Video Duration via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lesson_duration') {
    header('Content-Type: application/json');
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $duration = trim($_POST['duration'] ?? '');

    if ($lesson_id <= 0 || empty($duration)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE academy_lessons SET duration = ? WHERE id = ?");
            $stmt->execute([$duration, $lesson_id]);
        } catch (PDOException $e) {}
    }

    if (isset($_SESSION['mock_academy_lessons'])) {
        foreach ($_SESSION['mock_academy_lessons'] as &$l) {
            if ($l['id'] == $lesson_id) {
                $l['duration'] = $duration;
                break;
            }
        }
    }

    echo json_encode(['success' => true, 'duration' => $duration]);
    exit;
}

// Fetch all Academy Lessons
$academy_lessons = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM academy_lessons ORDER BY sort_order ASC, id ASC");
        $academy_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($academy_lessons) && isset($_SESSION['mock_academy_lessons'])) {
    $academy_lessons = $_SESSION['mock_academy_lessons'];
}

// Fetch user completed lesson IDs
$completed_lesson_ids = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT lesson_id FROM academy_user_progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $completed_lesson_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
} else {
    if (isset($_SESSION['mock_academy_user_progress'])) {
        foreach ($_SESSION['mock_academy_user_progress'] as $prog) {
            if ($prog['user_id'] == $user_id) {
                $completed_lesson_ids[] = (int)$prog['lesson_id'];
            }
        }
    }
}

$total_lessons = count($academy_lessons);
$completed_lessons = count($completed_lesson_ids);
$progress_pct = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TET Academy - Tet Wellbeing Group</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
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
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        heading: ['"Outfit"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="text-brand-slate antialiased min-h-screen flex flex-col">

    <?php include 'nav_menu.php'; ?>

    <!-- MAIN BODY CONTENT (Phase 4 Learning Center Layout) -->
    <main class="flex-grow mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 pb-24 md:pb-12 fade-in">
        
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT SIDEBAR (20%) -->
            <div class="w-full lg:w-1/4 xl:w-1/5 shrink-0">
                <div class="sticky top-28 space-y-1">
<?php require_once 'gating_helper.php'; ?>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 mb-4">Learning Center</h3>
                    
                    <a href="?tab=home" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'home' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-graduation-cap w-5 text-center"></i> 
                            TET Academy
                        </span>
                        <?php echo getPlanBadge('free'); ?>
                    </a>
                    
                    <a href="?tab=streaming" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'streaming' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-play-circle w-5 text-center"></i> 
                            Streaming Hub
                        </span>
                        <?php echo getPlanBadge('basic'); ?>
                    </a>
                    
                    <a href="?tab=research" class="flex items-center justify-between px-4 py-3 rounded-2xl text-sm font-bold transition-all <?php echo $tab === 'research' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-500 hover:bg-gray-50 hover:text-brand-slate'; ?>">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-microscope w-5 text-center"></i> 
                            Research Centre
                        </span>
                        <?php echo getPlanBadge('guest'); ?>
                    </a>
                </div>
            </div>

            <!-- RIGHT CONTENT AREA (80%) -->
            <div class="w-full lg:w-3/4 xl:w-4/5">
                <?php if ($tab === 'home'): ?>
                    <div class="max-w-4xl mx-auto">
                        
        <!-- Hero Section -->
        <div class="bg-brand-sageLight rounded-3xl p-8 sm:p-12 mb-10 text-center relative overflow-hidden border border-brand-sage/20 shadow-sm">
            <div class="absolute inset-0 z-0">
                <img src="images/academy_hero.png" alt="Academy Hero" class="w-full h-full object-cover opacity-30 mix-blend-multiply">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-sageLight via-transparent to-transparent"></div>
            </div>
            <div class="relative z-10 max-w-3xl mx-auto">
                <h1 class="text-4xl sm:text-5xl font-heading font-bold text-brand-slate mb-4">TET Academy</h1>
                <p class="text-lg text-brand-slate opacity-80 max-w-2xl mx-auto">
                    Bite-sized emotional intelligence and wellbeing lessons for your daily life.
                </p>
            </div>
        </div>

        <!-- Progress Bar Section -->
        <div class="bg-brand-cardBg rounded-2xl p-6 mb-8 shadow-sm border border-brand-sageLight">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-heading font-semibold text-brand-slate">Your Learning Journey</h2>
                <span id="journey-percentage-text" class="text-brand-sage font-bold text-lg"><?php echo $progress_pct; ?>%</span>
            </div>
            <div class="w-full bg-brand-sageLight rounded-full h-4 overflow-hidden">
                <div id="journey-progress-bar" class="bg-brand-sage h-4 rounded-full transition-all duration-700" style="width: <?php echo $progress_pct; ?>%"></div>
            </div>
            <p id="journey-completion-subtext" class="text-sm text-brand-slate opacity-70 mt-3 text-right">
                <?php if ($completed_lessons >= $total_lessons && $total_lessons > 0): ?>
                    🎉 Fantastic work! You have completed all <?php echo $total_lessons; ?> lessons.
                <?php else: ?>
                    Keep going! You've completed <?php echo $completed_lessons; ?> of <?php echo $total_lessons; ?> video lessons.
                <?php endif; ?>
            </p>
        </div>

        <!-- Dynamic Lesson Modules Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12">
            <?php foreach ($academy_lessons as $lesson): 
                $is_completed = in_array((int)$lesson['id'], $completed_lesson_ids);
                
                // Color palette assignment based on category
                $cat_lower = strtolower($lesson['category']);
                $badge_color = 'bg-brand-sageLight text-brand-sage';
                $header_bg = 'bg-brand-sage';
                if (strpos($cat_lower, 'anxiety') !== false) {
                    $badge_color = 'bg-sky-100 text-sky-800';
                    $header_bg = 'bg-brand-sky';
                } elseif (strpos($cat_lower, 'sleep') !== false) {
                    $badge_color = 'bg-indigo-100 text-indigo-800';
                    $header_bg = 'bg-brand-slate';
                } elseif (strpos($cat_lower, 'boundaries') !== false) {
                    $badge_color = 'bg-orange-100 text-orange-800';
                    $header_bg = 'bg-brand-coral';
                } elseif (strpos($cat_lower, 'regulation') !== false) {
                    $badge_color = 'bg-emerald-100 text-emerald-800';
                    $header_bg = 'bg-brand-sage';
                }

                // Extract YouTube ID for high quality preview thumbnail
                $yt_thumb = '';
                if (preg_match('/(?:embed\/|watch\?v=|youtu\.be\/)([^"&?\/\s]{11})/', $lesson['youtube_url'], $matches)) {
                    $yt_thumb = 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg';
                }
            ?>
            <div id="lesson-card-<?php echo $lesson['id']; ?>" class="bg-brand-cardBg rounded-2xl overflow-hidden shadow-sm border border-brand-sageLight hover:shadow-md transition flex flex-col group relative">
                <!-- Video Thumbnail / Cover -->
                <div class="h-44 w-full bg-gray-900 relative overflow-hidden flex items-center justify-center cursor-pointer" onclick="openLessonModal(<?php echo htmlspecialchars(json_encode($lesson)); ?>, <?php echo $is_completed ? 'true' : 'false'; ?>)">
                    <?php if ($yt_thumb): ?>
                        <img src="<?php echo htmlspecialchars($yt_thumb); ?>" alt="<?php echo htmlspecialchars($lesson['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 opacity-90 group-hover:opacity-100">
                    <?php else: ?>
                        <div class="w-full h-full <?php echo $header_bg; ?> flex items-center justify-center text-white">
                            <i data-lucide="<?php echo htmlspecialchars($lesson['icon_name'] ?? 'play-circle'); ?>" class="w-14 h-14 opacity-80"></i>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Play Overlay Button -->
                    <div class="absolute inset-0 bg-black/30 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                        <div class="h-12 w-12 rounded-full bg-white/90 text-brand-slate flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-play text-brand-coral ml-0.5 text-base"></i>
                        </div>
                    </div>

                    <!-- Category Badge -->
                    <span class="absolute top-3 left-3 px-2.5 py-0.5 rounded-full text-[10px] font-bold shadow-sm <?php echo $badge_color; ?>">
                        <?php echo htmlspecialchars($lesson['category']); ?>
                    </span>

                    <!-- Status Pill -->
                    <span id="card-status-badge-<?php echo $lesson['id']; ?>" class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm <?php echo $is_completed ? 'bg-emerald-500 text-white' : 'bg-black/60 text-white backdrop-blur-sm'; ?>">
                        <?php echo $is_completed ? '✓ Completed' : htmlspecialchars($lesson['duration']); ?>
                    </span>
                </div>

                <!-- Content Area -->
                <div class="p-5 flex flex-col flex-grow justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h3 class="text-base font-heading font-bold text-brand-slate leading-snug group-hover:text-brand-sage transition-colors line-clamp-2">
                                <?php echo htmlspecialchars($lesson['title']); ?>
                            </h3>
                        </div>
                        <p class="text-xs text-brand-slate opacity-75 mb-4 line-clamp-3 leading-relaxed">
                            <?php echo htmlspecialchars($lesson['description']); ?>
                        </p>
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                        <button type="button" onclick="openLessonModal(<?php echo htmlspecialchars(json_encode($lesson)); ?>, <?php echo $is_completed ? 'true' : 'false'; ?>)" class="flex-1 py-2 px-3 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs transition-all shadow-sm flex items-center justify-center gap-1.5 active:scale-95">
                            <i class="fa-solid fa-circle-play text-xs"></i> Watch Video
                        </button>
                        <button type="button" onclick="toggleLessonProgress(<?php echo $lesson['id']; ?>)" id="quick-toggle-btn-<?php echo $lesson['id']; ?>" title="<?php echo $is_completed ? 'Mark as Incomplete' : 'Mark as Completed'; ?>" class="p-2 rounded-xl border border-gray-200 hover:border-brand-sage text-xs transition-colors shrink-0 <?php echo $is_completed ? 'bg-emerald-50 text-emerald-600 border-emerald-300' : 'text-gray-400 hover:text-brand-sage'; ?>">
                            <i id="quick-toggle-icon-<?php echo $lesson['id']; ?>" class="fa-solid <?php echo $is_completed ? 'fa-circle-check text-emerald-600' : 'fa-check'; ?>"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Admin Shortcut Bar -->
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div class="p-4 rounded-2xl bg-brand-sageLight/50 border border-brand-sage/20 mb-8 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-shield text-brand-sage"></i>
                <span class="text-xs font-bold text-brand-slate">Administrator Mode:</span>
                <span class="text-xs text-gray-600">Want to add more YouTube lessons to this page?</span>
            </div>
            <a href="admin_dashboard.php#academy-management" class="px-3 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold hover:bg-brand-slate/90 transition-all shadow-sm">
                + Add Video Lesson via Admin
            </a>
        </div>
        <?php endif; ?>

                    </div>
                
                <?php elseif ($tab === 'streaming'): ?>
                    <div class="bg-[#121212] rounded-[2rem] border border-gray-800 shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="streaming_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php elseif ($tab === 'research'): ?>
                    <div class="bg-white rounded-[2rem] border border-[#EBE8E0] shadow-soft overflow-hidden h-[85vh]">
                        <iframe src="research_hub.php?embed=1" class="w-full h-full border-0"></iframe>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-20">
                        <h2 class="text-2xl font-bold text-brand-slate mb-4">Module Not Found</h2>
                        <a href="?tab=home" class="text-brand-sage underline">Return to Learning Center</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- LESSON VIDEO PLAYER MODAL -->
    <div id="lesson-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div id="lesson-modal-box" class="bg-white rounded-3xl max-w-3xl w-full shadow-2xl border border-gray-100 overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between bg-brand-bg">
                <div class="flex items-center gap-2.5">
                    <span id="modal-category-badge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-coralLight text-brand-coral uppercase tracking-wider">
                        Anxiety
                    </span>
                    <h3 id="modal-lesson-title" class="text-base font-bold font-heading text-brand-slate truncate max-w-md">
                        Lesson Player
                    </h3>
                </div>
                <button type="button" onclick="closeLessonModal()" class="h-8 w-8 rounded-full hover:bg-gray-200 text-gray-400 hover:text-brand-slate flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- YouTube Video Embed -->
            <div class="w-full bg-black relative" style="padding-bottom: 56.25%;">
                <iframe id="modal-youtube-frame" src="about:blank" class="absolute inset-0 w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>

            <!-- Modal Footer & Controls -->
            <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white">
                <div class="space-y-1">
                    <p id="modal-lesson-desc" class="text-xs text-gray-600 leading-relaxed max-w-lg">
                        Lesson description goes here.
                    </p>
                    <span id="modal-lesson-duration" class="text-[10px] font-mono text-gray-400 block">
                        ⏱️ Duration: 5 mins
                    </span>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <button type="button" id="modal-complete-btn" onclick="toggleModalLessonCompletion()" class="px-4 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2">
                        <i class="fa-solid fa-circle-check"></i>
                        <span id="modal-complete-text">Mark Completed</span>
                    </button>
                    <button type="button" onclick="closeLessonModal()" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- YouTube IFrame Player API -->
    <script src="https://www.youtube.com/iframe_api"></script>

    <!-- Scripts -->
    <script>
        lucide.createIcons();

        let activeLessonId = null;
        let activeLessonData = null;
        let isCurrentLessonCompleted = false;
        let ytPlayer = null;
        let ytApiReady = false;

        function onYouTubeIframeAPIReady() {
            ytApiReady = true;
        }

        function formatSecondsToDuration(sec) {
            if (!sec || isNaN(sec)) return '5 mins';
            const m = Math.floor(sec / 60);
            const s = Math.floor(sec % 60);
            return `${m}:${s < 10 ? '0' + s : s}`;
        }

        function syncVideoDuration(realDurationStr) {
            if (!activeLessonId || !realDurationStr) return;

            // Update modal text
            const durationEl = document.getElementById('modal-lesson-duration');
            if (durationEl) {
                durationEl.innerText = '⏱️ Duration: ' + realDurationStr;
            }

            // Update card pill if not completed
            const badge = document.getElementById('card-status-badge-' + activeLessonId);
            if (badge && !badge.innerText.includes('Completed')) {
                badge.innerText = realDurationStr;
            }

            // Sync to backend via AJAX so cards remember the real duration
            const formData = new FormData();
            formData.append('action', 'update_lesson_duration');
            formData.append('lesson_id', activeLessonId);
            formData.append('duration', realDurationStr);

            fetch('tet_academy.php', {
                method: 'POST',
                body: formData
            }).catch(e => console.error('Duration sync error:', e));
        }

        function openLessonModal(lesson, isCompleted) {
            activeLessonId = lesson.id;
            activeLessonData = lesson;
            isCurrentLessonCompleted = !!isCompleted;

            document.getElementById('modal-lesson-title').innerText = lesson.title;
            document.getElementById('modal-category-badge').innerText = lesson.category;
            document.getElementById('modal-lesson-desc').innerText = lesson.description;
            document.getElementById('modal-lesson-duration').innerText = '⏱️ Duration: ' + (lesson.duration || '5 mins');

            // Format embed url with enablejsapi=1 & autoplay
            let baseFrame = document.getElementById('modal-youtube-frame');
            let url = lesson.youtube_url;
            const sep = url.indexOf('?') === -1 ? '?' : '&';
            url += sep + 'enablejsapi=1&autoplay=1&rel=0';
            baseFrame.src = url;

            // Initialize or connect YouTube Player
            if (window.YT && window.YT.Player) {
                try {
                    if (ytPlayer && typeof ytPlayer.destroy === 'function') {
                        ytPlayer.destroy();
                    }
                } catch(e) {}

                ytPlayer = new YT.Player('modal-youtube-frame', {
                    events: {
                        'onReady': function(event) {
                            try {
                                const durationSec = event.target.getDuration();
                                if (durationSec && durationSec > 0) {
                                    const formatted = formatSecondsToDuration(durationSec);
                                    syncVideoDuration(formatted);
                                }
                            } catch(err) {
                                console.error('Error reading duration on ready:', err);
                            }
                        },
                        'onStateChange': function(event) {
                            // YT.PlayerState.PLAYING = 1, BUFFERING = 3, CUED = 5
                            try {
                                const durationSec = event.target.getDuration();
                                if (durationSec && durationSec > 0) {
                                    const formatted = formatSecondsToDuration(durationSec);
                                    syncVideoDuration(formatted);
                                }
                            } catch(err) {
                                console.error('Error reading duration on state change:', err);
                            }
                        }
                    }
                });
            }

            updateModalButtonState();

            const modal = document.getElementById('lesson-modal');
            const box = document.getElementById('lesson-modal-box');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                box.classList.remove('scale-95');
                box.classList.add('scale-100');
            }, 10);
        }

        function closeLessonModal() {
            const modal = document.getElementById('lesson-modal');
            const box = document.getElementById('lesson-modal-box');
            
            box.classList.add('scale-95');
            box.classList.remove('scale-100');
            modal.classList.add('opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                if (ytPlayer && typeof ytPlayer.stopVideo === 'function') {
                    try { ytPlayer.stopVideo(); } catch(e) {}
                }
                document.getElementById('modal-youtube-frame').src = 'about:blank';
                activeLessonId = null;
                activeLessonData = null;
            }, 300);
        }

        function updateModalButtonState() {
            const btn = document.getElementById('modal-complete-btn');
            const txt = document.getElementById('modal-complete-text');
            if (isCurrentLessonCompleted) {
                btn.classList.remove('bg-brand-sage', 'hover:bg-brand-sageHover');
                btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                txt.innerText = 'Completed ✓ (Tap to Undo)';
            } else {
                btn.classList.add('bg-brand-sage', 'hover:bg-brand-sageHover');
                btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                txt.innerText = 'Mark as Completed';
            }
        }

        function toggleModalLessonCompletion() {
            if (!activeLessonId) return;
            toggleLessonProgress(activeLessonId);
        }

        function toggleLessonProgress(lessonId) {
            const currentBadge = document.getElementById('card-status-badge-' + lessonId);
            const isCurrentlyDone = currentBadge && currentBadge.innerText.includes('Completed');
            const targetStatus = isCurrentlyDone ? 0 : 1;

            const formData = new FormData();
            formData.append('action', 'toggle_lesson_completed');
            formData.append('lesson_id', lessonId);
            formData.append('status', targetStatus);

            fetch('tet_academy.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update Progress UI
                    document.getElementById('journey-percentage-text').innerText = data.percentage + '%';
                    document.getElementById('journey-progress-bar').style.width = data.percentage + '%';
                    
                    if (data.percentage === 100) {
                        document.getElementById('journey-completion-subtext').innerText = `🎉 Fantastic work! You have completed all ${data.total_count} lessons.`;
                    } else {
                        document.getElementById('journey-completion-subtext').innerText = `Keep going! You've completed ${data.completed_count} of ${data.total_count} video lessons.`;
                    }

                    // Update Card Pill & Quick button
                    const badge = document.getElementById('card-status-badge-' + lessonId);
                    const quickBtn = document.getElementById('quick-toggle-btn-' + lessonId);
                    const quickIcon = document.getElementById('quick-toggle-icon-' + lessonId);

                    if (data.completed) {
                        if (badge) {
                            badge.className = 'absolute top-3 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm bg-emerald-500 text-white';
                            badge.innerText = '✓ Completed';
                        }
                        if (quickBtn) {
                            quickBtn.className = 'p-2 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-600 text-xs transition-colors shrink-0';
                            quickIcon.className = 'fa-solid fa-circle-check text-emerald-600';
                        }
                    } else {
                        if (badge) {
                            badge.className = 'absolute top-3 right-3 px-2 py-0.5 rounded-full text-[10px] font-bold shadow-sm bg-black/60 text-white backdrop-blur-sm';
                            badge.innerText = (activeLessonData && activeLessonData.id == lessonId && activeLessonData.duration) ? activeLessonData.duration : '5 mins';
                        }
                        if (quickBtn) {
                            quickBtn.className = 'p-2 rounded-xl border border-gray-200 hover:border-brand-sage text-gray-400 hover:text-brand-sage text-xs transition-colors shrink-0';
                            quickIcon.className = 'fa-solid fa-check';
                        }
                    }

                    if (activeLessonId == lessonId) {
                        isCurrentLessonCompleted = data.completed;
                        updateModalButtonState();
                    }
                }
            })
            .catch(err => console.error('Lesson toggle error:', err));
        }
    </script>
</body>
</html>
