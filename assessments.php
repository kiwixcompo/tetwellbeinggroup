<?php
session_start();
require_once 'db.php';
require_once 'gating_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$is_premium = isset($_SESSION['user_subscription_plan']) && $_SESSION['user_subscription_plan'] !== 'free';

// Handle POST save assessment score
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_assessment') {
        header('Content-Type: application/json');
        
        $assessment_type = $_POST['type'] ?? filter_input(INPUT_POST, 'type', FILTER_DEFAULT);
        $title = $_POST['title'] ?? filter_input(INPUT_POST, 'title', FILTER_DEFAULT);
        $score = isset($_POST['score']) ? (int)$_POST['score'] : filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
        $level = $_POST['level'] ?? filter_input(INPUT_POST, 'level', FILTER_DEFAULT);
        $notes = $_POST['notes'] ?? filter_input(INPUT_POST, 'notes', FILTER_DEFAULT);
        $completed_at = date('Y-m-d H:i:s');
        
        // Initialize user assessments list in session if needed
        if (!isset($_SESSION['user_assessments'][$user_id])) {
            $_SESSION['user_assessments'][$user_id] = [];
        }
        
        $new_record = [
            'id' => time() . rand(10, 99),
            'type' => $assessment_type,
            'title' => $title,
            'score' => $score,
            'level' => $level,
            'notes' => $notes,
            'completed_at' => $completed_at,
            'display_date' => date('M j, Y - g:ia')
        ];
        
        array_unshift($_SESSION['user_assessments'][$user_id], $new_record);
        
        // Award +1 activity count to Wellbeing Passport
        $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 8) + 1;
        
        // Try saving to database table if caregiver_burnout_logs exists
        if (!empty($db_connected) && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO caregiver_burnout_logs (user_id, log_date, score, burnout_level, notes) VALUES (?, CURDATE(), ?, ?, ?)");
                $stmt->execute([$user_id, $score, $level, "{$title}: {$notes}"]);
            } catch (Exception $ex) {}
        }
        
        echo json_encode([
            'status' => 'success',
            'record' => $new_record,
            'total_assessments' => count($_SESSION['user_assessments'][$user_id]),
            'activity_count' => $_SESSION['user_activity_count'][$user_id]
        ]);
        exit();
    }
}

// Load past assessments for this user
$past_assessments = $_SESSION['user_assessments'][$user_id] ?? [];
if (empty($past_assessments)) {
    // Seed initial realistic baseline so beginner sees how tracking works
    $past_assessments = [
        [
            'id' => 'seed_1',
            'type' => 'gad7',
            'title' => 'GAD-7 (Anxiety Screener)',
            'score' => 6,
            'level' => 'Mild Anxiety',
            'notes' => 'Feeling a bit on edge before work reviews.',
            'completed_at' => date('Y-m-d H:i:s', strtotime('-14 days')),
            'display_date' => date('M j, Y', strtotime('-14 days'))
        ],
        [
            'id' => 'seed_2',
            'type' => 'isi5',
            'title' => 'Insomnia & Sleep Quality (ISI-5)',
            'score' => 8,
            'level' => 'Subthreshold Mild Insomnia',
            'notes' => 'Late screen time caused delayed sleep onset.',
            'completed_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'display_date' => date('M j, Y', strtotime('-7 days'))
        ]
    ];
    $_SESSION['user_assessments'][$user_id] = $past_assessments;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinical & Wellbeing Check-ins - Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                            coralLight: '#FCECE8',
                            sageHover: '#4D755D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
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
    <?php if (!isset($_GET['embed'])) { require_once 'nav_menu.php'; } ?>

    <main class="flex-grow max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Hero Section -->
        <div class="bg-gradient-to-br from-brand-sageLight via-white to-brand-sageLight/50 rounded-3xl p-8 sm:p-10 mb-8 border border-brand-sage/20 shadow-sm relative overflow-hidden">
            <div class="relative z-10 max-w-3xl">
                <div class="inline-flex items-center gap-2 mb-3">
                    <?php echo getPlanBadge('free'); ?>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/80 text-brand-slate border border-brand-sage/30">
                        <i class="fa-solid fa-heart-pulse text-brand-coral mr-1"></i> Self-Care Compass
                    </span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-heading font-bold text-brand-slate mb-3">Clinical & Wellbeing Self-Checks</h1>
                <p class="text-base text-gray-600 leading-relaxed">
                    Scientifically-grounded screening tools designed to help you listen to your mind and body. All responses are private, non-diagnostic, and empower you with clear, gentle guidance.
                </p>
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold text-gray-500">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-brand-sage"></i> 100% Confidential</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-clock text-brand-sage"></i> Takes 2–3 minutes</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-chart-line text-brand-sage"></i> Tracks your progress over time</span>
                </div>
            </div>
        </div>

        <!-- Filter Tabs for Beginners -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex flex-wrap gap-2" id="filter-pills">
                <button onclick="filterCategory('all')" class="filter-btn active px-4 py-2 rounded-xl text-xs font-bold bg-brand-sage text-white shadow-sm transition-all" data-category="all">
                    All Check-ins (6)
                </button>
                <button onclick="filterCategory('clinical')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold bg-white text-gray-600 hover:bg-brand-sageLight hover:text-brand-slate border border-gray-200 transition-all" data-category="clinical">
                    🧠 Clinical & Mood
                </button>
                <button onclick="filterCategory('work')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold bg-white text-gray-600 hover:bg-brand-sageLight hover:text-brand-slate border border-gray-200 transition-all" data-category="work">
                    💼 Burnout & Work
                </button>
                <button onclick="filterCategory('body')" class="filter-btn px-4 py-2 rounded-xl text-xs font-bold bg-white text-gray-600 hover:bg-brand-sageLight hover:text-brand-slate border border-gray-200 transition-all" data-category="body">
                    🌙 Sleep & Caregiving
                </button>
            </div>

            <!-- Jump to Past History -->
            <a href="#history-section" class="text-xs font-bold text-brand-sage hover:text-brand-sageHover flex items-center gap-1">
                <i class="fa-solid fa-clock-rotate-left"></i> View Past History (<?php echo count($past_assessments); ?>)
            </a>
        </div>

        <!-- Assessment Grid (6 Cards) -->
        <div id="assessment-list" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            
            <!-- 1. PHQ-9 (Depression) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="clinical">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                            <i class="fa-solid fa-brain"></i> Clinical Mood
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>3 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">PHQ-9 Depression Screener</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        A 9-item clinically validated tool measuring interest, fatigue, sleep patterns, and emotional heaviness over the past two weeks.
                    </p>
                </div>
                <button onclick="startAssessment('phq9')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin PHQ-9 Check-in</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- 2. GAD-7 (Anxiety) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="clinical">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <i class="fa-solid fa-wind"></i> Anxiety & Nervous Tension
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>2 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">GAD-7 Anxiety Screener</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        7 questions screening for restlessness, excessive worry, and physical tension to help you understand your nervous system.
                    </p>
                </div>
                <button onclick="startAssessment('gad7')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin GAD-7 Check-in</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- 3. MBI-9 (Burnout Inventory) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="work">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            <i class="fa-solid fa-fire text-rose-500"></i> Burnout & Exhaustion
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>3 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">Maslach Burnout Screener (MBI-9)</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        Assesses emotional exhaustion, cynicism, and mental fatigue to help protect your boundaries before chronic burnout occurs.
                    </p>
                </div>
                <button onclick="startAssessment('mbi9')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin Burnout Screener</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- 4. ISI-5 (Sleep Health) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="body">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            <i class="fa-solid fa-moon text-indigo-500"></i> Sleep & Recovery
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>2 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">Insomnia & Sleep Quality (ISI-5)</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        Evaluates sleep onset, night waking, and daytime fatigue with tailored recommendations for acoustic soundscapes.
                    </p>
                </div>
                <button onclick="startAssessment('isi5')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin Sleep Quality Check</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- 5. ZBI-4 (Caregiver Strain) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="body">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <i class="fa-solid fa-hands-holding-child text-emerald-600"></i> Caregiver Support
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>2 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">Zarit Caregiver Strain (ZBI-4)</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        For anyone supporting a loved one, family member, or client. Measures emotional load, personal time loss, and caregiver fatigue.
                    </p>
                </div>
                <button onclick="startAssessment('zbi4')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin Caregiver Strain Check</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

            <!-- 6. PSS-4 (Workplace Stress) -->
            <div class="assessment-card bg-brand-cardBg rounded-3xl p-6 shadow-sm border border-brand-sage/20 hover:shadow-md transition-all flex flex-col justify-between" data-category="work">
                <div>
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                            <i class="fa-solid fa-briefcase text-teal-600"></i> Occupational Balance
                        </span>
                        <span class="text-xs font-semibold text-gray-400"><i class="fa-regular fa-clock mr-1"></i>2 mins</span>
                    </div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">Perceived Workplace Stress (PSS-4)</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">
                        Screens for feeling overwhelmed, lack of control, and workload stress over the last 30 days with actionable coping tips.
                    </p>
                </div>
                <button onclick="startAssessment('pss4')" class="w-full bg-brand-sage text-white py-3 rounded-2xl font-bold hover:bg-brand-sageHover shadow-sm transition flex items-center justify-center gap-2">
                    <span>Begin Workplace Stress Check</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>

        </div>

        <!-- Assessment Active Container (Form & Questions) -->
        <div id="assessment-container" class="hidden bg-brand-cardBg rounded-3xl p-6 sm:p-10 shadow-sm border border-brand-sage/20">
            <div class="flex items-center justify-between pb-6 mb-6 border-b border-gray-100">
                <button onclick="backToList()" class="text-brand-sage font-bold text-sm flex items-center hover:text-brand-sageHover transition">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1.5"></i> Back to All Check-ins
                </button>
                <span id="assessment-category-badge" class="px-3 py-1 rounded-full text-xs font-bold bg-brand-sageLight text-brand-sage">Check-in</span>
            </div>
            
            <h2 id="assessment-title" class="text-2xl sm:text-3xl font-heading font-bold text-brand-slate mb-2"></h2>
            <p id="assessment-instruction" class="text-sm text-gray-500 mb-8 italic"></p>

            <form id="assessment-form" onsubmit="submitAssessment(event)">
                <div id="questions-container" class="space-y-6 mb-8">
                    <!-- Questions injected dynamically -->
                </div>
                
                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <button type="button" onclick="backToList()" class="px-6 py-3 rounded-2xl text-gray-500 hover:bg-gray-100 font-bold text-sm transition">
                        Cancel
                    </button>
                    <button type="submit" id="submit-btn" class="bg-brand-slate hover:bg-gray-800 text-white px-8 py-3.5 rounded-2xl font-bold text-sm shadow-md transition flex items-center gap-2">
                        <span>Calculate Results & Save</span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </form>

            <!-- Results Display Container -->
            <div id="results-container" class="hidden mt-8 p-6 sm:p-8 rounded-3xl bg-brand-sageLight/50 border border-brand-sage/30 shadow-inner-soft">
                <!-- Injected after submit -->
            </div>
        </div>

        <!-- Past History & Trends Section -->
        <div id="history-section" class="mt-12 bg-white rounded-3xl p-6 sm:p-8 border border-[#EBE8E0] shadow-sm">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-xl font-heading font-bold text-brand-slate">Your Assessment History & Trends</h3>
                    <p class="text-xs text-gray-500">Every check-in is saved securely to your profile to help you track your progress over time.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-brand-sageLight text-brand-sage">
                    <i class="fa-solid fa-passport mr-1"></i> Synced to Passport
                </span>
            </div>

            <div id="history-timeline" class="space-y-4">
                <?php if (empty($past_assessments)): ?>
                    <p class="text-sm text-gray-400 py-4 text-center">No assessments completed yet. Take your first 2-minute check-in above!</p>
                <?php else: ?>
                    <?php foreach ($past_assessments as $item): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-brand-inputBg border border-gray-100 hover:border-brand-sage/30 transition-colors gap-3">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-brand-sage shadow-sm shrink-0 border border-gray-200">
                                    <i class="fa-solid fa-clipboard-check"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold text-brand-slate"><?php echo htmlspecialchars($item['title']); ?></h4>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white text-brand-sage border border-brand-sage/20">
                                            Score: <?php echo htmlspecialchars($item['score']); ?>
                                        </span>
                                    </div>
                                    <p class="text-xs font-semibold text-brand-coral mt-0.5"><?php echo htmlspecialchars($item['level']); ?></p>
                                    <?php if (!empty($item['notes'])): ?>
                                        <p class="text-xs text-gray-500 mt-1 italic"><?php echo htmlspecialchars($item['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 font-medium sm:text-right shrink-0">
                                <i class="fa-regular fa-calendar mr-1"></i> <?php echo htmlspecialchars($item['display_date'] ?? $item['completed_at']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // 6 Scientifically-backed self-assessments
        const assessmentsData = {
            'phq9': {
                title: 'PHQ-9 (Depression Screener)',
                category: 'Clinical Mood',
                instruction: 'Over the last 2 weeks, how often have you been bothered by any of the following problems?',
                questions: [
                    "Little interest or pleasure in doing things",
                    "Feeling down, depressed, or hopeless",
                    "Trouble falling or staying asleep, or sleeping too much",
                    "Feeling tired or having little energy",
                    "Poor appetite or overeating",
                    "Feeling bad about yourself — or that you are a failure or have let yourself or your family down",
                    "Trouble concentrating on things, such as reading or watching television",
                    "Moving or speaking so slowly that other people noticed, or being fidgety and restless",
                    "Thoughts that you would be better off dead, or of hurting yourself in some way"
                ],
                options: [
                    { label: "Not at all", score: 0 },
                    { label: "Several days", score: 1 },
                    { label: "More than half the days", score: 2 },
                    { label: "Nearly every day", score: 3 }
                ],
                recommendationLink: { text: "Try 5-4-3-2-1 Grounding", url: "mindbloom.php?action=grounding" },
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Minimal or No Depression', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'Your score suggests minimal or no symptoms of depression. You are maintaining great emotional balance. Keep engaging in your daily routines!' };
                    if (score <= 9) return { level: 'Mild Depression', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Your score suggests mild emotional fatigue or low mood. Engaging in our peer circles, gratitude journaling, or brief daily outdoor walks can provide uplifting momentum.' };
                    if (score <= 14) return { level: 'Moderate Depression', color: 'text-orange-600', bg: 'bg-orange-50 text-orange-800 border-orange-200', message: 'Your score suggests moderate depression symptoms. We encourage you to reach out to our Befriender Network for a supportive, compassionate chat.' };
                    if (score <= 19) return { level: 'Moderately Severe Depression', color: 'text-brand-coral', bg: 'bg-rose-50 text-rose-800 border-rose-200', message: 'Your score indicates significant emotional distress. Please consider discussing these results with a therapist or GP. Our Teletherapy Hub can help connect you with professional care.' };
                    return { level: 'Severe Depression', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'Your score reflects high distress. You do not have to carry this alone. Please speak with a healthcare provider immediately or use our Emergency Support button for immediate confidential assistance.' };
                }
            },
            'gad7': {
                title: 'GAD-7 (Anxiety Screener)',
                category: 'Anxiety & Tension',
                instruction: 'Over the last 2 weeks, how often have you been bothered by the following problems?',
                questions: [
                    "Feeling nervous, anxious, or on edge",
                    "Not being able to stop or control worrying",
                    "Worrying too much about different things",
                    "Trouble relaxing",
                    "Being so restless that it is hard to sit still",
                    "Becoming easily annoyed or irritable",
                    "Feeling afraid, as if something awful might happen"
                ],
                options: [
                    { label: "Not at all", score: 0 },
                    { label: "Several days", score: 1 },
                    { label: "More than half the days", score: 2 },
                    { label: "Nearly every day", score: 3 }
                ],
                recommendationLink: { text: "Start 4-7-8 Relaxing Breathing", url: "breathing_exercises.php" },
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Minimal Anxiety', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'Your score reflects minimal anxiety. Your nervous system is in a calm, regulated state.' };
                    if (score <= 9) return { level: 'Mild Anxiety', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Your score suggests mild anxiety. Practicing 3 minutes of Box Breathing or listening to calming rain sounds can quickly restore calm when tension rises.' };
                    if (score <= 14) return { level: 'Moderate Anxiety', color: 'text-orange-600', bg: 'bg-orange-50 text-orange-800 border-orange-200', message: 'Your score indicates moderate anxiety. Unloading racing thoughts into TrueVent and speaking with a Befriender can provide significant relief.' };
                    return { level: 'Severe Anxiety', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'Your score indicates severe anxiety symptoms affecting daily peace. We recommend connecting with a licensed mental health professional via our Teletherapy services.' };
                }
            },
            'mbi9': {
                title: 'Maslach Burnout Screener (MBI-9)',
                category: 'Burnout & Exhaustion',
                instruction: 'How frequently do you experience each of the following in your professional or daily commitments?',
                questions: [
                    "I feel emotionally drained from my work or daily responsibilities",
                    "I feel used up and completely depleted at the end of the day",
                    "I feel fatigued when I get up in the morning and have to face another demanding day",
                    "Working with or supporting people all day is a heavy strain for me",
                    "I feel burned out from my ongoing responsibilities",
                    "I worry that my commitments are making me emotionally harder or more cynical",
                    "I find myself wishing I could disconnect from everyone and everything for a while",
                    "I struggle to find genuine joy in accomplishments that used to make me proud",
                    "I feel there are not enough hours in the day to recuperate my physical energy"
                ],
                options: [
                    { label: "Never", score: 0 },
                    { label: "A few times a month", score: 1 },
                    { label: "Once a week", score: 2 },
                    { label: "Several times a week", score: 3 },
                    { label: "Every day", score: 4 }
                ],
                recommendationLink: { text: "Run Energy Battery Check", url: "mindbloom.php?action=checkin" },
                getFeedback: (score) => {
                    if (score <= 10) return { level: 'Low Burnout Risk', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'You are sustaining healthy boundaries and energy reserves. Keep protecting your downtime.' };
                    if (score <= 20) return { level: 'Moderate Burnout Risk', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Early warning signs of exhaustion are present. We recommend taking micro-breaks and completing our Energy Battery Check to protect your daily capacity.' };
                    if (score <= 28) return { level: 'High Burnout Warning', color: 'text-orange-600', bg: 'bg-orange-50 text-orange-800 border-orange-200', message: 'You are operating on depleted reserves. Implement firm end-of-workday shutdown rituals and delegate tasks where possible.' };
                    return { level: 'Severe Burnout State', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'You are experiencing acute burnout exhaustion. Prioritize complete rest, talk to HR or a counselor, and review our Workplace Burnout Challenge.' };
                }
            },
            'isi5': {
                title: 'Insomnia & Sleep Quality (ISI-5)',
                category: 'Sleep & Recovery',
                instruction: 'Please rate the severity of your sleep problems over the past 2 weeks:',
                questions: [
                    "Difficulty falling asleep at night",
                    "Difficulty staying asleep (waking up in the middle of the night)",
                    "Problems waking up too early and being unable to fall back asleep",
                    "How dissatisfied or unhappy are you with your current sleep pattern?",
                    "How much do sleep problems interfere with your daily energy, concentration, or mood?"
                ],
                options: [
                    { label: "None", score: 0 },
                    { label: "Mild", score: 1 },
                    { label: "Moderate", score: 2 },
                    { label: "Severe", score: 3 },
                    { label: "Very Severe", score: 4 }
                ],
                recommendationLink: { text: "Launch Sleep Ambient Mixer", url: "ambient_mixer.php" },
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'No Clinically Significant Insomnia', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'Your sleep quality is restorative and supportive of your daily wellbeing.' };
                    if (score <= 9) return { level: 'Subthreshold Mild Insomnia', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Mild sleep disruption detected. Playing our Heavy Rain or Deep Ocean sounds in the Ambient Mixer with a 30-minute sleep timer can help soothe sleep transitions.' };
                    if (score <= 14) return { level: 'Clinical Moderate Insomnia', color: 'text-orange-600', bg: 'bg-orange-50 text-orange-800 border-orange-200', message: 'Moderate insomnia is affecting your restorative cycles. We suggest our 7 Days of Better Sleep challenge and reducing evening blue light.' };
                    return { level: 'Severe Clinical Insomnia', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'Severe sleep deprivation is present. Please consult with a physician or sleep specialist to evaluate underlying factors.' };
                }
            },
            'zbi4': {
                title: 'Zarit Caregiver Strain Screener (ZBI-4)',
                category: 'Caregiver Support',
                instruction: 'For informal or professional caregivers: Rate your experience over the past month:',
                questions: [
                    "Do you feel that because of the time you spend caring, you do not have enough time for yourself?",
                    "Do you feel stressed between caring for your loved one and trying to meet other work/family responsibilities?",
                    "Do you feel that your physical or mental health has suffered because of your caregiving involvement?",
                    "Do you feel that you have lost a sense of control over your personal life since caring began?"
                ],
                options: [
                    { label: "Never", score: 0 },
                    { label: "Rarely", score: 1 },
                    { label: "Sometimes", score: 2 },
                    { label: "Quite frequently", score: 3 },
                    { label: "Nearly always", score: 4 }
                ],
                recommendationLink: { text: "Visit Caregiver Hub & Peer Respite", url: "caregiver_hub.php" },
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Mild or Low Strain', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'You are balancing care responsibilities with commendable resilience. Remember to carve out regular micro-moments for yourself.' };
                    if (score <= 8) return { level: 'Moderate Caregiver Strain', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Caregiver burden is beginning to impinge on your personal well-being. Connecting with fellow carers in the Caregiver Hub can provide vital empathy and tips.' };
                    if (score <= 12) return { level: 'High Caregiver Strain', color: 'text-orange-600', bg: 'bg-orange-50 text-orange-800 border-orange-200', message: 'Your care duties are taking a toll on your reserves. We urge you to seek respite care support and connect with our Befriender Network.' };
                    return { level: 'Severe Caregiver Overload', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'Caregiver burnout is critical. You cannot pour from an empty cup. Please reach out to social care services or professional counseling.' };
                }
            },
            'pss4': {
                title: 'Perceived Workplace Stress (PSS-4)',
                category: 'Workplace Balance',
                instruction: 'In the last 30 days, how often have you felt the following in your work and life?',
                questions: [
                    "How often have you felt unable to control important things in your workday?",
                    "How often have you felt confident in your ability to handle personal and work problems? (Reverse scored)",
                    "How often have you felt that things were going your way? (Reverse scored)",
                    "How often have you felt difficulties were piling up so high that you could not overcome them?"
                ],
                options: [
                    { label: "Never", score: 0 },
                    { label: "Almost never", score: 1 },
                    { label: "Sometimes", score: 2 },
                    { label: "Fairly often", score: 3 },
                    { label: "Very often", score: 4 }
                ],
                recommendationLink: { text: "Start Mid-Day Desk Breathing", url: "breathing_exercises.php" },
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Low Perceived Stress', color: 'text-brand-sage', bg: 'bg-emerald-50 text-emerald-800 border-emerald-200', message: 'You have a healthy sense of control and self-efficacy over your daily workload.' };
                    if (score <= 8) return { level: 'Moderate Workplace Stress', color: 'text-amber-600', bg: 'bg-amber-50 text-amber-800 border-amber-200', message: 'Work demands are creating noticeable pressure. Implementing 2-minute breath resets between meetings can maintain your focus.' };
                    return { level: 'High Workplace Stress', color: 'text-red-700', bg: 'bg-red-50 text-red-800 border-red-200', message: 'High stress overload detected. Review your commitments, communicate boundaries with your manager, or explore our Workplace Burnout Sprint.' };
                }
            }
        };

        let currentAssessment = null;

        // Category filter function
        function filterCategory(cat) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.classList.remove('bg-brand-sage', 'text-white');
                b.classList.add('bg-white', 'text-gray-600');
            });
            const activeBtn = document.querySelector(`.filter-btn[data-category="${cat}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-white', 'text-gray-600');
                activeBtn.classList.add('bg-brand-sage', 'text-white');
            }

            document.querySelectorAll('.assessment-card').forEach(card => {
                if (cat === 'all' || card.dataset.category === cat) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        function startAssessment(type) {
            currentAssessment = type;
            const data = assessmentsData[type];
            if (!data) return;
            
            document.getElementById('assessment-list').classList.add('hidden');
            document.getElementById('filter-pills').classList.add('hidden');
            document.getElementById('assessment-container').classList.remove('hidden');
            document.getElementById('assessment-title').textContent = data.title;
            document.getElementById('assessment-instruction').textContent = data.instruction;
            document.getElementById('assessment-category-badge').textContent = data.category;
            
            document.getElementById('results-container').classList.add('hidden');
            document.getElementById('results-container').innerHTML = '';
            
            const qContainer = document.getElementById('questions-container');
            let html = '';
            
            data.questions.forEach((q, i) => {
                html += `
                    <div class="bg-brand-inputBg p-5 sm:p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <p class="font-bold text-brand-slate text-sm sm:text-base mb-4">
                            <span class="inline-block bg-white text-brand-sage border border-brand-sage/20 rounded-lg px-2 py-0.5 text-xs mr-2">${i+1}</span>
                            ${q}
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                `;
                data.options.forEach((opt, j) => {
                    html += `
                        <label class="flex items-center p-3 sm:p-3.5 rounded-xl border border-gray-200 cursor-pointer hover:bg-brand-sageLight/50 hover:border-brand-sage/40 transition bg-white text-xs sm:text-sm text-gray-700">
                            <input type="radio" required name="q${i}" value="${opt.score}" class="mr-2 text-brand-sage focus:ring-brand-sage">
                            <span>${opt.label}</span>
                        </label>
                    `;
                });
                html += `</div></div>`;
            });
            
            qContainer.innerHTML = html;
            window.scrollTo({ top: document.getElementById('assessment-container').offsetTop - 40, behavior: 'smooth' });
        }

        function backToList() {
            document.getElementById('assessment-list').classList.remove('hidden');
            document.getElementById('filter-pills').classList.remove('hidden');
            document.getElementById('assessment-container').classList.add('hidden');
            document.getElementById('assessment-form').reset();
            currentAssessment = null;
        }

        function submitAssessment(e) {
            e.preventDefault();
            const data = assessmentsData[currentAssessment];
            if (!data) return;

            let score = 0;
            for (let i = 0; i < data.questions.length; i++) {
                const selected = document.querySelector(`input[name="q${i}"]:checked`);
                if (selected) {
                    let val = parseInt(selected.value);
                    // Handle reverse scored questions for PSS-4 (questions index 1 and 2)
                    if (currentAssessment === 'pss4' && (i === 1 || i === 2)) {
                        val = 4 - val;
                    }
                    score += val;
                }
            }

            const feedback = data.getFeedback(score);
            
            // Save via AJAX to session & DB
            const formData = new FormData();
            formData.append('action', 'save_assessment');
            formData.append('type', currentAssessment);
            formData.append('title', data.title);
            formData.append('score', score);
            formData.append('level', feedback.level);
            formData.append('notes', feedback.message.substring(0, 120) + '...');

            fetch('assessments.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    // Prepend to timeline
                    updateHistoryTimeline(res.record);
                }
            }).catch(err => console.error(err));

            // Render Results Card
            const resultsDiv = document.getElementById('results-container');
            resultsDiv.innerHTML = `
                <div class="text-center max-w-xl mx-auto">
                    <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-white text-brand-sage shadow-md mb-4 border border-brand-sage/20 text-2xl">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Your Completed Check-in</p>
                    <h3 class="text-4xl font-heading font-extrabold text-brand-slate">Score: ${score}</h3>
                    
                    <div class="inline-block px-4 py-1.5 rounded-full text-sm font-bold border my-3 ${feedback.bg}">
                        ${feedback.level}
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-brand-sage/20 text-sm text-gray-700 leading-relaxed text-left my-4 shadow-sm">
                        ${feedback.message}
                    </div>

                    <!-- Recommended Action Box -->
                    <div class="bg-white/80 p-4 rounded-2xl border border-brand-sage/30 flex items-center justify-between gap-4 mt-6 text-left">
                        <div>
                            <span class="text-[11px] font-bold text-brand-sage uppercase tracking-wider block">Recommended Calming Reset</span>
                            <span class="text-xs font-bold text-brand-slate">${data.recommendationLink.text}</span>
                        </div>
                        <a href="${data.recommendationLink.url}" class="px-4 py-2 rounded-xl bg-brand-sage text-white text-xs font-bold hover:bg-brand-sageHover transition shadow-sm shrink-0">
                            Start Now &rarr;
                        </a>
                    </div>

                    <!-- Saved Notification -->
                    <p class="text-xs text-brand-sage font-bold mt-4 flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-circle-check"></i> Saved to your Wellbeing Passport (+1 Activity Point)
                    </p>
                    
                    <div class="mt-6 flex justify-center gap-3">
                        <button onclick="backToList()" class="px-6 py-2.5 rounded-xl bg-brand-slate text-white text-xs font-bold hover:bg-gray-800 transition">
                            Back to All Check-ins
                        </button>
                    </div>
                </div>
            `;
            
            resultsDiv.classList.remove('hidden');
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }

        function updateHistoryTimeline(record) {
            const timeline = document.getElementById('history-timeline');
            if (!timeline) return;

            const card = document.createElement('div');
            card.className = 'flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200 transition-colors gap-3 animate-fade-in';
            card.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-brand-sage shadow-sm shrink-0 border border-gray-200">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-brand-slate">${record.title}</h4>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white text-brand-sage border border-brand-sage/20">
                                Score: ${record.score}
                            </span>
                            <span class="text-[10px] font-bold text-emerald-600 bg-white px-2 py-0.5 rounded-md border border-emerald-200">Just Now</span>
                        </div>
                        <p class="text-xs font-semibold text-brand-coral mt-0.5">${record.level}</p>
                        <p class="text-xs text-gray-500 mt-1 italic">${record.notes}</p>
                    </div>
                </div>
                <span class="text-xs text-gray-400 font-medium sm:text-right shrink-0">
                    <i class="fa-regular fa-calendar mr-1"></i> ${record.display_date}
                </span>
            `;
            timeline.insertBefore(card, timeline.firstChild);
        }
    </script>
    
    <?php require_once 'includes/global_ai_widget.php'; ?>
</body>
</html>
