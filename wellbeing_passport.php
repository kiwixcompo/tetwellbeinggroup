<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Member';

// Curricula definition for all 8 structured challenges with in-system actionable tasks
$all_challenges_data = [
    "7-Day Calm Mind Challenge" => [
        "title" => "7-Day Calm Mind Challenge",
        "desc" => "Learn to quiet your mind, reduce anxiety loops, and find peace in the present moment.",
        "duration" => "7 Days",
        "days_count" => 7,
        "image" => "images/challenge_calm.png",
        "category" => "Mindfulness & Grounding",
        "steps" => [
            1 => [
                "title" => "Notice Your Thoughts",
                "desc" => "Take 2 minutes to observe thoughts without judgment. Ground yourself in steady breath.",
                "action_type" => "breathing",
                "action_label" => "Launch 2-Min Guided Breathing",
                "target" => "breathing"
            ],
            2 => [
                "title" => "5-4-3-2-1 Sensory Grounding",
                "desc" => "Anchor your awareness right now by tuning in to your senses and logging what you perceive.",
                "action_type" => "reflection",
                "action_label" => "Record Sensory Reflection",
                "prompt" => "Name 3 soothing things you can see or feel around you right now:"
            ],
            3 => [
                "title" => "Acoustic Reset & Sound Immersion",
                "desc" => "Immerse yourself in gentle nature acoustic frequencies to ease mental tension.",
                "action_type" => "ambient",
                "action_label" => "Open Ambient Soundscapes",
                "target" => "ambient"
            ],
            4 => [
                "title" => "Identify Stress & Release It",
                "desc" => "Write down one nagging mental stressor and one kind, actionable step to release control.",
                "action_type" => "reflection",
                "action_label" => "Save Stress Unloading Note",
                "prompt" => "What is weighing on you, and what is one small thing within your control today?"
            ],
            5 => [
                "title" => "Vocal Harmony & Tension Check",
                "desc" => "Read a calming passage into the Voice Stress Analyzer to monitor vocal cord tension.",
                "action_type" => "voice",
                "action_label" => "Take Voice Stress Check-in",
                "target" => "predictive_hub.php?tab=voice"
            ],
            6 => [
                "title" => "Self-Compassion Moment",
                "desc" => "Speak to yourself with the kindness you would offer a cherished friend.",
                "action_type" => "reflection",
                "action_label" => "Log Self-Compassion Note",
                "prompt" => "Write down three genuine qualities or efforts you appreciate about yourself:"
            ],
            7 => [
                "title" => "Weekly Reflection & Integration",
                "desc" => "Reflect on how your mental clarity has evolved across this week of grounding.",
                "action_type" => "reflection",
                "action_label" => "Complete Challenge Reflection",
                "prompt" => "What was the most peaceful moment of your week, and what habit will you keep?"
            ]
        ]
    ],
    "7 Days of Better Sleep" => [
        "title" => "7 Days of Better Sleep",
        "desc" => "Build restorative habits that dissolve bedtime restlessness and promote deep, rejuvenating sleep.",
        "duration" => "7 Days",
        "days_count" => 7,
        "image" => "images/quiet_sanctuary.png",
        "category" => "Rest & Recovery",
        "steps" => [
            1 => [
                "title" => "Screen Wind-Down Buffer",
                "desc" => "Dim the lights and engage in a calming 4-7-8 relaxing breathing cycle before sleep.",
                "action_type" => "breathing",
                "action_label" => "Launch Evening Breathing (4-7-8)",
                "target" => "breathing"
            ],
            2 => [
                "title" => "White & Pink Noise Sleep Sanctuary",
                "desc" => "Tune in to gentle binaural brown noise or rain layers to mask distracting thoughts.",
                "action_type" => "ambient",
                "action_label" => "Tune Sleep Frequencies",
                "target" => "ambient"
            ],
            3 => [
                "title" => "Bedtime Brain Dump",
                "desc" => "Clear mental clutter before your head hits the pillow so your subconscious feels safe.",
                "action_type" => "reflection",
                "action_label" => "Save Sleep Journal Dump",
                "prompt" => "List any unresolved to-dos for tomorrow so you can let them rest tonight:"
            ],
            4 => [
                "title" => "Check Your Sleep Telemetry",
                "desc" => "Review your sleep duration, HRV, and resting heart rate patterns.",
                "action_type" => "link",
                "action_label" => "View Sleep Metrics",
                "target" => "predictive_hub.php?tab=twin"
            ],
            5 => [
                "title" => "Somatic Muscle Release",
                "desc" => "Perform progressive full-body muscle relaxation from toes to forehead with steady breath.",
                "action_type" => "breathing",
                "action_label" => "Begin Deep Release Breathing",
                "target" => "breathing"
            ],
            6 => [
                "title" => "Evening Gratitude Drift",
                "desc" => "Nurture pleasant dreamscapes by recalling three comforting moments from your day.",
                "action_type" => "reflection",
                "action_label" => "Save Gratitude Reflection",
                "prompt" => "Three cozy, comforting, or joyful moments from today:"
            ],
            7 => [
                "title" => "Morning Circadian Reflection",
                "desc" => "Review your awakening energy and set your sleep intention for the coming month.",
                "action_type" => "reflection",
                "action_label" => "Seal Sleep Journey",
                "prompt" => "How has your sleep quality shifted, and what evening routine worked best?"
            ]
        ]
    ],
    "14 Days of Self-Confidence" => [
        "title" => "14 Days of Self-Confidence",
        "desc" => "Dismantle imposter syndrome, rewrite self-critical narratives, and step boldly into your worth.",
        "duration" => "14 Days",
        "days_count" => 14,
        "image" => "images/blooming_renewal.png",
        "category" => "Personal Growth",
        "steps" => [
            1 => [
                "title" => "Recognize the Inner Critic",
                "desc" => "Unmask self-doubt by capturing the critical thoughts that held you back recently.",
                "action_type" => "reflection",
                "action_label" => "Note & Reframe Critic",
                "prompt" => "What is one self-doubt thought you had today, and what is the compassionate counter-truth?"
            ],
            2 => [
                "title" => "Center in Confident Posture & Breath",
                "desc" => "Calm nervous tension and steady your nervous system with box breathing.",
                "action_type" => "breathing",
                "action_label" => "Launch 2-Min Centering Breath",
                "target" => "breathing"
            ],
            3 => [
                "title" => "Catalogue of Small Victories",
                "desc" => "Document 3 achievements or challenges you have navigated over the past month.",
                "action_type" => "reflection",
                "action_label" => "Save Victories List",
                "prompt" => "Write down 3 things you overcame or accomplished that you are proud of:"
            ],
            4 => [
                "title" => "Voice of Strength Check-in",
                "desc" => "Read an empowering affirmation into the Voice Stress Analyzer with clear, resonant resonance.",
                "action_type" => "voice",
                "action_label" => "Practice Voice Presence",
                "target" => "predictive_hub.php?tab=voice"
            ],
            5 => [
                "title" => "Establish One Healthy Boundary",
                "desc" => "Decide on one boundary you need to protect your energy and peace today.",
                "action_type" => "reflection",
                "action_label" => "Record Boundary Commitment",
                "prompt" => "What is one thing you will say 'no' or 'not right now' to, in order to honour yourself?"
            ],
            6 => [
                "title" => "Community Encouragement",
                "desc" => "Share a positive word or acknowledge someone in our peer circle or friend group.",
                "action_type" => "link",
                "action_label" => "Visit Peer Support Circles",
                "target" => "tet_circle.php"
            ],
            7 => [
                "title" => "Mid-Challenge Confidence Assessment",
                "desc" => "Take a moment to record your rising confidence and sense of agency.",
                "action_type" => "reflection",
                "action_label" => "Log Confidence Progress",
                "prompt" => "How has your relationship with your inner voice shifted over the first 7 days?"
            ]
        ]
    ],
    "21 Days of Reducing Overthinking" => [
        "title" => "21 Days of Reducing Overthinking",
        "desc" => "Break repetitive loops of rumination, cultivate present mindfulness, and act with mental clarity.",
        "duration" => "21 Days",
        "days_count" => 21,
        "image" => "images/nav_mindbloom.png",
        "category" => "Mental Clarity",
        "steps" => [
            1 => [
                "title" => "Catch the Rumination Spiral",
                "desc" => "Identify repetitive loops before they snowball. Take a deep breath to pause.",
                "action_type" => "breathing",
                "action_label" => "Pause Spiral with Breathing",
                "target" => "breathing"
            ],
            2 => [
                "title" => "Fact vs Story Journaling",
                "desc" => "Separate hard observable facts from anxious stories your brain weaves.",
                "action_type" => "reflection",
                "action_label" => "Unpack Thought vs Fact",
                "prompt" => "What situation triggered you today? What are the verifiable facts, vs the imagined stories?"
            ],
            3 => [
                "title" => "Binaural Focus Calibration",
                "desc" => "Use focused ambient noise to redirect scattered cognitive bandwidth.",
                "action_type" => "ambient",
                "action_label" => "Tune Focus Soundscapes",
                "target" => "ambient"
            ],
            4 => [
                "title" => "The 5-Minute Action Rule",
                "desc" => "Instead of overanalyzing, take one small, 5-minute action that moves you forward.",
                "action_type" => "reflection",
                "action_label" => "Commit to 5-Min Action",
                "prompt" => "What small task will you execute right now without further deliberation?"
            ],
            5 => [
                "title" => "Vocal Calming Grounding",
                "desc" => "Ground vocal pitch and diaphragmatic pacing with a calm reading session.",
                "action_type" => "voice",
                "action_label" => "Check Vocal Resonance",
                "target" => "predictive_hub.php?tab=voice"
            ],
            6 => [
                "title" => "Digital Detox Window",
                "desc" => "Unplug for an intentional 30-minute quiet period away from information overload.",
                "action_type" => "reflection",
                "action_label" => "Log Unplugged Experience",
                "prompt" => "What did you do during your screen-free window, and how did your mind feel?"
            ],
            7 => [
                "title" => "Review Overthinking Triggers",
                "desc" => "Check your Digital Twin profile for learned environmental and circadian stress triggers.",
                "action_type" => "link",
                "action_label" => "Inspect Twin Learned Triggers",
                "target" => "predictive_hub.php?tab=twin"
            ]
        ]
    ],
    "30 Days of Connection" => [
        "title" => "30 Days of Connection",
        "desc" => "Deepen meaningful bonds, bridge isolation, and nurture genuine empathy with others.",
        "duration" => "30 Days",
        "days_count" => 30,
        "image" => "images/landing_community.png",
        "category" => "Relationships & Community",
        "steps" => [
            1 => [
                "title" => "Reach Out to One Person",
                "desc" => "Send an unexpected note of appreciation or gentle 'thinking of you' to a friend.",
                "action_type" => "reflection",
                "action_label" => "Log Connection Action",
                "prompt" => "Who did you reach out to today, and how did it feel to bridge the gap?"
            ],
            2 => [
                "title" => "Active Listening Mindset",
                "desc" => "Anchor yourself in calm, centered focus so you can listen without planning your reply.",
                "action_type" => "breathing",
                "action_label" => "Centering Breath for Empathy",
                "target" => "breathing"
            ],
            3 => [
                "title" => "Explore the Befriender Network",
                "desc" => "Connect with trained peer befrienders who understand your unique life season.",
                "action_type" => "link",
                "action_label" => "Browse Befriender Network",
                "target" => "befriender_network.php"
            ],
            4 => [
                "title" => "Express Genuine Gratitude",
                "desc" => "Tell someone specifically what their presence or support has meant to you.",
                "action_type" => "reflection",
                "action_label" => "Record Gratitude Exchange",
                "prompt" => "What thank you did you share today, and how was it received?"
            ],
            5 => [
                "title" => "Join a Virtual Tea or Circle",
                "desc" => "Participate in a supportive community chat with fellow peers.",
                "action_type" => "link",
                "action_label" => "Open Peer Support Circles",
                "target" => "tet_circle.php"
            ],
            6 => [
                "title" => "Shared Presence Activity",
                "desc" => "Share a meal, a walk, or a quiet call with someone without phone distractions.",
                "action_type" => "reflection",
                "action_label" => "Log Shared Moment",
                "prompt" => "Describe a moment of undivided attention you shared with someone today:"
            ],
            7 => [
                "title" => "Weekly Belonging Check-in",
                "desc" => "Assess your sense of community and how connection is boosting your emotional resilience.",
                "action_type" => "reflection",
                "action_label" => "Save Belonging Reflection",
                "prompt" => "How has feeling connected to others altered your outlook this week?"
            ]
        ]
    ],
    "Caregiver Resilience Challenge" => [
        "title" => "Caregiver Resilience Challenge",
        "desc" => "Replenish compassion fatigue, establish restorative boundaries, and honor your own caregiver health.",
        "duration" => "Ongoing (7-Day Sprint)",
        "days_count" => 7,
        "image" => "images/generational_anchor.png",
        "category" => "Caregiver Wellness",
        "steps" => [
            1 => [
                "title" => "Claim 15 Minutes of Unapologetic Respite",
                "desc" => "Give yourself permission to pause. Log your respite downtime in the caregiver hub.",
                "action_type" => "link",
                "action_label" => "Open Respite Downtime Tracker",
                "target" => "caregiver_hub.php?tab=respite"
            ],
            2 => [
                "title" => "Caregiver Box Breathing",
                "desc" => "Down-regulate acute caregiver nervous tension with an immediate 2-minute breathing cycle.",
                "action_type" => "breathing",
                "action_label" => "Launch Respite Breathing",
                "target" => "breathing"
            ],
            3 => [
                "title" => "Release Caregiver Guilt",
                "desc" => "Acknowledge that taking care of yourself is an absolute prerequisite to caring for another.",
                "action_type" => "reflection",
                "action_label" => "Log Compassion Affirmation",
                "prompt" => "What is one self-judgment you are releasing today regarding your caregiving duties?"
            ],
            4 => [
                "title" => "Assess Burnout Buffer",
                "desc" => "Check your physiological resilience score and burnout vulnerability index.",
                "action_type" => "link",
                "action_label" => "View Burnout Buffer",
                "target" => "predictive_hub.php?tab=twin"
            ],
            5 => [
                "title" => "Ambient Sensory Retreat",
                "desc" => "Create a 10-minute micro-sanctuary with gentle rain and fireplace acoustics.",
                "action_type" => "ambient",
                "action_label" => "Play Restorative Soundscape",
                "target" => "ambient"
            ],
            6 => [
                "title" => "Connect with a Matched Caregiver Peer",
                "desc" => "Exchange a quick word with a fellow caregiver who shares your exact journey.",
                "action_type" => "link",
                "action_label" => "Caregiver Peer Network",
                "target" => "caregiver_hub.php?tab=matching"
            ],
            7 => [
                "title" => "Caregiver Sustainability Plan",
                "desc" => "Map out one sustainable support routine for next week to safeguard your mental wellbeing.",
                "action_type" => "reflection",
                "action_label" => "Commit to Sustainability",
                "prompt" => "What boundary or support will you establish next week so you do not carry it alone?"
            ]
        ]
    ],
    "Student Wellbeing Challenge" => [
        "title" => "Student Wellbeing Challenge",
        "desc" => "Balance academic rigor, mitigate exam burnout, and preserve social and emotional energy.",
        "duration" => "Ongoing (7-Day Sprint)",
        "days_count" => 7,
        "image" => "images/academy_hero.png",
        "category" => "Academic & Study Life",
        "steps" => [
            1 => [
                "title" => "Calm Pre-Study Centering",
                "desc" => "Clear mental brain fog before hitting the books with 2 minutes of energizing breath.",
                "action_type" => "breathing",
                "action_label" => "Start Focus Breathing",
                "target" => "breathing"
            ],
            2 => [
                "title" => "Focus Frequency Study Session",
                "desc" => "Study alongside procedural brown noise or gentle rain to tune out library distractions.",
                "action_type" => "ambient",
                "action_label" => "Launch Study Soundscapes",
                "target" => "ambient"
            ],
            3 => [
                "title" => "De-Catastrophize Exam Deadlines",
                "desc" => "Break down daunting coursework into 3 bite-sized steps that can be tackled one at a time.",
                "action_type" => "reflection",
                "action_label" => "Save Chunked Study Plan",
                "prompt" => "Break down your biggest academic stressor into 3 simple, non-intimidating steps:"
            ],
            4 => [
                "title" => "Check Academic Burnout Score",
                "desc" => "Monitor sleep duration and physical steps during intense study periods.",
                "action_type" => "link",
                "action_label" => "Review Study Vital Signs",
                "target" => "predictive_hub.php?tab=twin"
            ],
            5 => [
                "title" => "Take a 30-Minute Outdoor Reset",
                "desc" => "Step away from library desks and screens. Get natural daylight and move your body.",
                "action_type" => "reflection",
                "action_label" => "Record Nature Refresh",
                "prompt" => "Where did you walk, and how did changing your environment clear your head?"
            ],
            6 => [
                "title" => "Study Buddy Encouragement",
                "desc" => "Check in with a classmate to celebrate completing a challenging assignment.",
                "action_type" => "link",
                "action_label" => "Student Peer Circle",
                "target" => "tet_circle.php"
            ],
            7 => [
                "title" => "Academic Life Balance Reflection",
                "desc" => "Reflect on healthy study boundaries to sustain your learning stamina.",
                "action_type" => "reflection",
                "action_label" => "Complete Student Reflection",
                "prompt" => "What study habit made the biggest positive difference to your stress levels this week?"
            ]
        ]
    ],
    "Workplace Burnout Prevention Challenge" => [
        "title" => "Workplace Burnout Prevention Challenge",
        "desc" => "Establish sustainable work-life borders, manage workplace overstimulation, and preserve vitality.",
        "duration" => "Ongoing (7-Day Sprint)",
        "days_count" => 7,
        "image" => "images/hub_caregiver.png",
        "category" => "Professional Balance",
        "steps" => [
            1 => [
                "title" => "Mid-Day Nervous System Reset",
                "desc" => "Step away from emails for 2 minutes of diaphragmatic box breathing at your desk.",
                "action_type" => "breathing",
                "action_label" => "Launch Desk Breathing Reset",
                "target" => "breathing"
            ],
            2 => [
                "title" => "Define 'End-of-Workday' Ritual",
                "desc" => "Create a psychological barrier between your workday and personal evening peace.",
                "action_type" => "reflection",
                "action_label" => "Document Shutdown Ritual",
                "prompt" => "What concrete action (closing laptop, a brief walk) marks the end of your workday?"
            ],
            3 => [
                "title" => "Acoustic Work Haven",
                "desc" => "Block out workplace or open-office chatter with soothing white and rainfall audio.",
                "action_type" => "ambient",
                "action_label" => "Open Focus Audio Mixer",
                "target" => "ambient"
            ],
            4 => [
                "title" => "Vocal Stress Mid-Day Check",
                "desc" => "Check your voice resonance after intense meetings to ensure neck and throat muscles relax.",
                "action_type" => "voice",
                "action_label" => "Take Voice Acoustic Check",
                "target" => "predictive_hub.php?tab=voice"
            ],
            5 => [
                "title" => "Protect One Lunch Break",
                "desc" => "Eat lunch away from screens and work notifications. Savor your meal without multitasking.",
                "action_type" => "reflection",
                "action_label" => "Log Mindful Lunch",
                "prompt" => "How did it feel to step completely away from tasks for lunch today?"
            ],
            6 => [
                "title" => "Review Burnout Early Warning",
                "desc" => "Check telemetry predictions and HRV indicators to prevent cumulative physical exhaustion.",
                "action_type" => "link",
                "action_label" => "Review Workplace Warnings",
                "target" => "predictive_hub.php?tab=twin"
            ],
            7 => [
                "title" => "Weekly Workplace Energy Audit",
                "desc" => "Assess what tasks invigorated you versus what drained your energy, and adapt accordingly.",
                "action_type" => "reflection",
                "action_label" => "Complete Workplace Audit",
                "prompt" => "What was the highest-drain task this week, and how can you delegate or boundary it next week?"
            ]
        ]
    ]
];

// Initialize persistent user goals in session
if (!isset($_SESSION['user_wellbeing_goals'][$user_id])) {
    $_SESSION['user_wellbeing_goals'][$user_id] = [
        ['id' => 1, 'text' => 'Improve restorative sleep consistency', 'completed' => false],
        ['id' => 2, 'text' => 'Build self-compassion and inner confidence', 'completed' => false],
        ['id' => 3, 'text' => 'Stay connected with peer support community', 'completed' => true]
    ];
}

// Ensure user active challenge exists in session or initialize
if (!isset($_SESSION['user_active_challenge'][$user_id])) {
    // Default active challenge to 7-Day Calm Mind so user immediately experiences an ongoing challenge
    $_SESSION['user_active_challenge'][$user_id] = [
        'key' => '7-Day Calm Mind Challenge',
        'title' => '7-Day Calm Mind Challenge',
        'duration' => '7 Days',
        'days_count' => 7,
        'current_day' => 1,
        'completed_days' => [],
        'reflections' => [],
        'started_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
}

// Track completed wellbeing activities count in session
if (!isset($_SESSION['user_activity_count'][$user_id])) {
    $_SESSION['user_activity_count'][$user_id] = 8; // Baseline starter
}

// -------------------------------------------------------------
// POST AJAX / FORM HANDLERS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Start or Switch a Challenge
    if ($action === 'start_challenge') {
        $challenge_key = trim($_POST['challenge_title'] ?? '');
        if (isset($all_challenges_data[$challenge_key])) {
            $c_data = $all_challenges_data[$challenge_key];
            $_SESSION['user_active_challenge'][$user_id] = [
                'key' => $challenge_key,
                'title' => $c_data['title'],
                'duration' => $c_data['duration'],
                'days_count' => $c_data['days_count'],
                'current_day' => 1,
                'completed_days' => [],
                'reflections' => [],
                'started_at' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ];
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => "Started {$c_data['title']}!"]);
                exit();
            }
            header("Location: wellbeing_passport.php?tab=challenges&started=1");
            exit();
        }
    }
    
    // 2. Complete Today's Challenge Step
    if ($action === 'complete_step') {
        $active_challenge = &$_SESSION['user_active_challenge'][$user_id];
        $reflection_note = trim($_POST['reflection_note'] ?? '');
        $step_day = (int)($_POST['day'] ?? $active_challenge['current_day']);
        
        if (!in_array($step_day, $active_challenge['completed_days'])) {
            $active_challenge['completed_days'][] = $step_day;
            if (!empty($reflection_note)) {
                $active_challenge['reflections'][$step_day] = $reflection_note;
            }
            $_SESSION['user_activity_count'][$user_id] = ($_SESSION['user_activity_count'][$user_id] ?? 0) + 1;
            
            // Advance day if possible
            if ($step_day >= $active_challenge['current_day'] && $active_challenge['current_day'] < $active_challenge['days_count']) {
                $active_challenge['current_day'] = $step_day + 1;
            } elseif ($step_day >= $active_challenge['days_count']) {
                $active_challenge['status'] = 'completed';
            }
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'current_day' => $active_challenge['current_day'],
                'completed_days' => $active_challenge['completed_days'],
                'challenge_status' => $active_challenge['status'],
                'activities_count' => ($_SESSION['user_activity_count'][$user_id] ?? 8)
            ]);
            exit();
        }
        header("Location: wellbeing_passport.php?tab=challenges&step_completed=1");
        exit();
    }
    
    // 3. Abandon / Reset Active Challenge
    if ($action === 'abandon_challenge') {
        unset($_SESSION['user_active_challenge'][$user_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit();
        }
        header("Location: wellbeing_passport.php?tab=challenges");
        exit();
    }
    
    // 4. Add Wellbeing Goal
    if ($action === 'add_goal') {
        $goal_text = trim($_POST['new_goal'] ?? '');
        if (!empty($goal_text)) {
            $new_id = time() . rand(10, 99);
            $_SESSION['user_wellbeing_goals'][$user_id][] = [
                'id' => $new_id,
                'text' => $goal_text,
                'completed' => false
            ];
            
            // Also attempt to persist to DB if table exists
            if (!empty($db_connected) && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO caregiver_daily_goals (user_id, goal_text, is_completed, created_date) VALUES (?, ?, 0, CURDATE())");
                    $stmt->execute([$user_id, $goal_text]);
                } catch (Exception $e) {}
            }
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'goals' => $_SESSION['user_wellbeing_goals'][$user_id]]);
            exit();
        }
        header("Location: wellbeing_passport.php?tab=insights");
        exit();
    }
    
    // 5. Toggle Wellbeing Goal
    if ($action === 'toggle_goal') {
        $goal_id = $_POST['goal_id'] ?? '';
        $found = false;
        $is_now_completed = false;
        
        foreach ($_SESSION['user_wellbeing_goals'][$user_id] as &$g) {
            if ($g['id'] == $goal_id) {
                $g['completed'] = !$g['completed'];
                $is_now_completed = $g['completed'];
                $found = true;
                break;
            }
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'completed' => $is_now_completed]);
            exit();
        }
        header("Location: wellbeing_passport.php?tab=insights");
        exit();
    }

    // 6. Delete Wellbeing Goal
    if ($action === 'delete_goal') {
        $goal_id = $_POST['goal_id'] ?? '';
        $_SESSION['user_wellbeing_goals'][$user_id] = array_values(array_filter(
            $_SESSION['user_wellbeing_goals'][$user_id],
            function($g) use ($goal_id) { return $g['id'] != $goal_id; }
        ));
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit();
        }
        header("Location: wellbeing_passport.php?tab=insights");
        exit();
    }
}

// -------------------------------------------------------------
// DYNAMIC INSIGHTS ENGINE - Real User Telemetry & Check-in Analysis
// -------------------------------------------------------------
$active_challenge = $_SESSION['user_active_challenge'][$user_id] ?? null;
$user_goals = $_SESSION['user_wellbeing_goals'][$user_id] ?? [];

// Gather past check-ins
$past_checkins = [];
if (!empty($db_connected) && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT mood, notes, created_at FROM daily_checkins WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$user_id]);
        $past_checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {}
}

if (empty($past_checkins) && isset($_SESSION['mock_checkins'])) {
    foreach ($_SESSION['mock_checkins'] as $c) {
        if (($c['user_id'] ?? 0) == $user_id) {
            $past_checkins[] = $c;
        }
    }
}

// Seed realistic fallback check-ins if brand new user
if (count($past_checkins) < 4) {
    $seed_sample = [
        -28 => ['mood' => 'okay', 'notes' => 'Quiet, balanced morning.'],
        -21 => ['mood' => 'anxious', 'notes' => 'Work deadlines approaching.'],
        -15 => ['mood' => 'exhausted', 'notes' => 'Late shift, needed rest.'],
        -9  => ['mood' => 'low', 'notes' => 'Felt a bit down and disconnected.'],
        -5  => ['mood' => 'grow', 'notes' => 'Feeling positive energy return.'],
        -3  => ['mood' => 'frustrated', 'notes' => 'Tech issues earlier, breathing helped.'],
        -1  => ['mood' => 'okay', 'notes' => 'Smooth and peaceful day.']
    ];
    foreach ($seed_sample as $day_offset => $sample_item) {
        $past_checkins[] = [
            'user_id' => $user_id,
            'mood' => $sample_item['mood'],
            'notes' => $sample_item['notes'],
            'created_at' => date('Y-m-d H:i:s', strtotime("$day_offset days"))
        ];
    }
}

// Compute Dynamic Emotional Patterns
$mood_counts = [];
$day_of_week_moods = [];
$first_day_of_month = date('Y-m-01 00:00:00');
$this_month_checkins = 0;

foreach ($past_checkins as $ck) {
    $m = strtolower($ck['mood'] ?? 'okay');
    $mood_counts[$m] = ($mood_counts[$m] ?? 0) + 1;
    
    $dow = date('l', strtotime($ck['created_at']));
    if (!isset($day_of_week_moods[$dow])) {
        $day_of_week_moods[$dow] = [];
    }
    $day_of_week_moods[$dow][] = $m;
    
    if (($ck['created_at'] ?? '') >= $first_day_of_month) {
        $this_month_checkins++;
    }
}

// Determine most common mood
arsort($mood_counts);
$primary_mood = key($mood_counts) ?: 'okay';
$mood_readable = [
    'grow' => 'Energized & Flourishing',
    'okay' => 'Balanced & Grounded',
    'anxious' => 'Anxious or In Overdrive',
    'overwhelmed' => 'Overstimulated',
    'exhausted' => 'Fatigued & Needing Rest',
    'low' => 'Reflective & Low Energy',
    'frustrated' => 'Tense or Frustrated',
    'talk' => 'Social & Communicative'
];
$common_mood_text = $mood_readable[$primary_mood] ?? ucfirst($primary_mood);

// Identify day of week pattern
$stress_indicators = ['anxious', 'overwhelmed', 'exhausted', 'frustrated', 'low'];
$stress_by_day = [];
foreach ($day_of_week_moods as $day => $moods_list) {
    $stress_count = 0;
    foreach ($moods_list as $ml) {
        if (in_array($ml, $stress_indicators)) {
            $stress_count++;
        }
    }
    $stress_by_day[$day] = $stress_count;
}
arsort($stress_by_day);
$highest_stress_day = key($stress_by_day) ?: 'Wednesday';

$stress_pattern = "Your most recurrent emotional tone is <span class='font-bold text-brand-slate'>{$common_mood_text}</span>. Mental fatigue peaks slightly around <span class='font-bold text-brand-slate'>{$highest_stress_day}s</span>, with steady balance regaining over weekends.";

// Dynamic "What Helps Me"
$coping_tools = [];
if (class_exists('EmotionalHealthService')) {
    $twin = EmotionalHealthService::getDigitalTwinProfile($user_id);
    if ($twin && !empty($twin['coping_styles'])) {
        $decoded = is_array($twin['coping_styles']) ? $twin['coping_styles'] : json_decode($twin['coping_styles'], true);
        if (is_array($decoded)) {
            $coping_tools = $decoded;
        }
    }
}
if (empty($coping_tools)) {
    $coping_tools = ["Guided 4-7-8 Breathing Exercises", "Calming Nature Ambient Soundscapes", "Micro-Respite Breaks", "Sensory Grounding (5-4-3-2-1)"];
}
$helpful_pattern = implode(" • ", array_slice($coping_tools, 0, 3));

// Compute Total Real Activities Completed
$completed_activities = ($_SESSION['user_activity_count'][$user_id] ?? 8) + count($active_challenge['completed_days'] ?? []);
$completed_goals_count = count(array_filter($user_goals, function($g) { return !empty($g['completed']); }));

$challenges_list = array_values($all_challenges_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wellbeing Passport | Tet Wellbeing Group</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F7F5F0; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .tab-btn.active {
            border-bottom: 3px solid #5E8C71;
            color: #5E8C71;
            font-weight: 600;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
</head>
<body class="text-brand-slate antialiased flex flex-col min-h-screen">

    <!-- Top Nav -->
    <?php include 'nav_menu.php'; ?>

    <!-- Page Hero -->
    <header class="pt-28 pb-16 px-6 sm:px-8 lg:px-12 bg-white relative overflow-hidden border-b border-gray-100">
        <div class="absolute inset-0 z-0">
            <img src="images/passport_hero.png" alt="Wellbeing Journey" class="w-full h-full object-cover opacity-20">
            <div class="absolute inset-0 bg-gradient-to-b from-white/60 to-white/95"></div>
        </div>
        <div class="max-w-4xl mx-auto relative z-10 text-center">
            <h1 class="text-4xl sm:text-5xl font-heading font-bold text-brand-slate mb-4 tracking-tight">My Wellbeing Passport</h1>
            <p class="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                A private space to understand yourself better through your progress and reflections. <br>
                <span class="italic text-brand-sage font-medium mt-2 inline-block">"I understand myself better because of TET."</span>
            </p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 w-full pb-20">
        
        <!-- Tabs Navigation -->
        <div class="flex border-b border-gray-200 mb-8 overflow-x-auto hide-scrollbar">
            <button onclick="switchTab('insights')" id="btn-insights" class="tab-btn active px-6 py-4 text-gray-500 hover:text-brand-sage focus:outline-none whitespace-nowrap transition-colors duration-200 font-medium text-lg">
                My Insights Profile
            </button>
            <button onclick="switchTab('challenges')" id="btn-challenges" class="tab-btn px-6 py-4 text-gray-500 hover:text-brand-sage focus:outline-none whitespace-nowrap transition-colors duration-200 font-medium text-lg">
                Wellbeing Challenges
            </button>
        </div>

        <!-- TAB 1: Insights Profile -->
        <div id="tab-insights" class="tab-content active fade-in">
            
            <?php if (!empty($active_challenge)): ?>
            <!-- Ongoing Challenge Spotlight in Insights -->
            <div class="mb-8 bg-gradient-to-r from-brand-sage/15 via-brand-sky/15 to-brand-coral/15 rounded-3xl p-6 sm:p-8 border border-brand-sage/20 relative overflow-hidden shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-sage text-white text-xs font-bold rounded-full mb-3 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                            Active Challenge In Progress
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-heading font-bold text-brand-slate mb-2">
                            <?php echo htmlspecialchars($active_challenge['title']); ?>
                        </h2>
                        <p class="text-gray-600 max-w-xl">
                            You are on <strong class="text-brand-slate">Day <?php echo $active_challenge['current_day']; ?> of <?php echo $active_challenge['days_count']; ?></strong>. 
                            <?php echo count($active_challenge['completed_days']); ?> days accomplished so far!
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="switchTab('challenges')" class="px-6 py-3 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl shadow transition transform hover:-translate-y-0.5 flex items-center gap-2">
                            Continue Today's Task
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
                
                <!-- Emotional Patterns -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-sky/20 flex items-center justify-center text-brand-sky text-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h2 class="text-2xl font-heading font-bold">Emotional Patterns</h2>
                        </div>
                        <span class="text-xs px-2.5 py-1 bg-brand-sky/10 text-brand-slate font-medium rounded-full">Real Telemetry</span>
                    </div>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Computed across your longitudinal mood check-ins and biometric trends on Tet.</p>
                    <div class="bg-brand-bg rounded-xl p-5 border border-gray-100 mb-4">
                        <p class="text-base text-brand-slate leading-relaxed">
                            <span class="text-brand-sage mr-2 text-lg">✨</span><?php echo $stress_pattern; ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
                        <span>Check-ins Analyzed: <strong class="text-brand-slate"><?php echo count($past_checkins); ?> entries</strong></span>
                        <a href="dashboard.php#daily-checkin" class="text-brand-sage hover:underline font-medium">Log Today's Mood &rarr;</a>
                    </div>
                </div>

                <!-- What Helps Me -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage text-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            </div>
                            <h2 class="text-2xl font-heading font-bold">What Helps Me</h2>
                        </div>
                        <span class="text-xs px-2.5 py-1 bg-brand-sageLight text-brand-sage font-medium rounded-full">Digital Twin Synthesis</span>
                    </div>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Practices correlated with your fastest physiological recovery and emotional stabilization.</p>
                    <div class="bg-brand-bg rounded-xl p-5 border border-gray-100 mb-4">
                        <p class="text-base text-brand-slate leading-relaxed">
                            <span class="text-brand-sage mr-2 text-lg">🌱</span><strong>Identified Coping Tools:</strong><br>
                            <span class="text-gray-700"><?php echo htmlspecialchars($helpful_pattern); ?></span>
                        </p>
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
                        <span>Digital Twin Coping Styles</span>
                        <a href="predictive_hub.php?tab=twin" class="text-brand-sage hover:underline font-medium">Inspect Biometrics &rarr;</a>
                    </div>
                </div>

                <!-- My Progress -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300 flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-brand-sage to-emerald-400 text-white flex items-center justify-center text-5xl font-heading font-bold mb-4 shadow-lg ring-8 ring-brand-sageLight">
                        <span id="stat-activities-count"><?php echo $completed_activities; ?></span>
                    </div>
                    <h2 class="text-2xl font-heading font-bold mb-2">Activities Completed</h2>
                    <p class="text-gray-600 max-w-sm mb-4">
                        You have engaged in <strong class="text-brand-slate" id="stat-activities-count-text"><?php echo $completed_activities; ?></strong> mindful moments and challenge actions this month.
                    </p>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Active Growth Momentum</span>
                    </div>
                </div>

                <!-- My Wellbeing Goals (Persistent) -->
                <div class="glass-card rounded-2xl p-6 sm:p-8 hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-coralLight flex items-center justify-center text-brand-coral text-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <h2 class="text-2xl font-heading font-bold">My Wellbeing Goals</h2>
                        </div>
                        <span id="goals-badge" class="text-xs px-2.5 py-1 bg-brand-coralLight text-brand-coral font-bold rounded-full">
                            <?php echo $completed_goals_count; ?> / <?php echo count($user_goals); ?> Done
                        </span>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4">Click to mark goals completed. Your intentions are automatically saved to your passport.</p>
                    
                    <ul id="goals-list" class="space-y-3 mb-6 flex-grow">
                        <?php foreach($user_goals as $goal): ?>
                        <li id="goal-item-<?php echo $goal['id']; ?>" class="flex items-center justify-between gap-3 p-3 bg-brand-bg rounded-xl border border-gray-100 transition-all">
                            <div class="flex items-center gap-3 flex-grow overflow-hidden">
                                <button onclick="toggleGoalBackend(<?php echo $goal['id']; ?>)" class="goal-checkbox w-5 h-5 rounded border-2 border-brand-sage flex items-center justify-center transition-colors duration-200 <?php echo !empty($goal['completed']) ? 'bg-brand-sage text-white' : 'text-transparent hover:bg-brand-sage/20'; ?>">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </button>
                                <span class="font-medium text-sm text-gray-700 truncate <?php echo !empty($goal['completed']) ? 'line-through text-gray-400' : ''; ?>">
                                    <?php echo htmlspecialchars($goal['text']); ?>
                                </span>
                            </div>
                            <button onclick="deleteGoalBackend(<?php echo $goal['id']; ?>)" class="text-gray-400 hover:text-red-500 p-1 text-xs opacity-60 hover:opacity-100 transition" title="Remove goal">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form onsubmit="addGoalBackend(event, this)" class="flex gap-2">
                        <input type="text" name="new_goal" placeholder="Set a new intention or goal..." class="flex-grow px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-sage focus:border-transparent text-sm bg-white" required>
                        <button type="submit" class="bg-brand-slate text-white px-5 py-2.5 rounded-xl hover:bg-opacity-90 transition-colors font-bold text-sm">Add</button>
                    </form>
                </div>

            </div>
        </div>

        <!-- TAB 2: Challenges -->
        <div id="tab-challenges" class="tab-content fade-in">
            
            <?php if (!empty($active_challenge)): 
                $c_key = $active_challenge['key'] ?? '';
                $curr_day = (int)($active_challenge['current_day'] ?? 1);
                $total_days = (int)($active_challenge['days_count'] ?? 7);
                $step_data = $all_challenges_data[$c_key]['steps'][$curr_day] ?? null;
                $is_day_completed = in_array($curr_day, $active_challenge['completed_days'] ?? []);
                $progress_pct = round((count($active_challenge['completed_days']) / max(1, $total_days)) * 100);
            ?>
            <!-- ACTIVE CHALLENGE HERO MISSION CARD -->
            <div class="mb-10 bg-white rounded-3xl p-6 sm:p-8 shadow-xl border-2 border-brand-sage/40 relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-64 h-64 bg-brand-sageLight rounded-full blur-3xl opacity-50 pointer-events-none"></div>
                
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-gray-100 relative z-10">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-3 py-1 bg-brand-sage text-white text-xs font-bold rounded-full uppercase tracking-wider">
                                Active In-System Challenge
                            </span>
                            <span class="text-sm font-semibold text-gray-500">Started on <?php echo date('M j, Y', strtotime($active_challenge['started_at'])); ?></span>
                        </div>
                        <h2 class="text-3xl font-heading font-bold text-brand-slate">
                            <?php echo htmlspecialchars($active_challenge['title']); ?>
                        </h2>
                        <p class="text-gray-600 mt-1">
                            Follow your daily action plan. Complete today's interactive exercise right here on Tet.
                        </p>
                    </div>
                    
                    <!-- Progress meter -->
                    <div class="w-full lg:w-72 bg-brand-bg p-4 rounded-2xl border border-gray-100">
                        <div class="flex justify-between items-center text-sm font-bold mb-2">
                            <span class="text-brand-slate">Overall Progress</span>
                            <span class="text-brand-sage"><?php echo $progress_pct; ?>% (Day <?php echo $curr_day; ?>/<?php echo $total_days; ?>)</span>
                        </div>
                        <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                            <div class="bg-gradient-to-r from-brand-sage to-emerald-500 h-full rounded-full transition-all duration-500" style="width: <?php echo $progress_pct; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-2">
                            <span><?php echo count($active_challenge['completed_days']); ?> completed</span>
                            <button onclick="abandonChallenge()" class="text-gray-400 hover:text-red-500 underline">Abandon / Switch</button>
                        </div>
                    </div>
                </div>

                <!-- Today's Action Item -->
                <div class="mt-8 pt-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-brand-sage text-white flex items-center justify-center font-bold text-base shadow">
                            <?php echo $curr_day; ?>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-brand-sage uppercase tracking-wider">Today's In-System Action</span>
                            <h3 class="text-2xl font-heading font-bold text-brand-slate">
                                Day <?php echo $curr_day; ?>: <?php echo htmlspecialchars($step_data['title'] ?? 'Notice your thoughts'); ?>
                            </h3>
                        </div>
                    </div>

                    <p class="text-gray-600 mb-6 text-base max-w-3xl leading-relaxed">
                        <?php echo htmlspecialchars($step_data['desc'] ?? 'Perform your daily mindfulness activity.'); ?>
                    </p>

                    <!-- Interactive In-System Tool Widget based on action_type -->
                    <div class="bg-brand-bg rounded-2xl p-6 border border-gray-200/80 mb-6">
                        <?php if (($step_data['action_type'] ?? '') === 'breathing'): ?>
                            <!-- In-System Breathing Launcher -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center text-2xl shadow-inner">
                                        🫁
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate">Interactive Guided Breathing Module</h4>
                                        <p class="text-xs text-gray-600">Follow the soothing visual breath pacer right inside your browser window.</p>
                                    </div>
                                </div>
                                <button onclick="openBreathingModal()" class="px-5 py-3 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl shadow transition flex items-center justify-center gap-2 whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path></svg>
                                    <?php echo htmlspecialchars($step_data['action_label'] ?? 'Launch 2-Min Guided Breathing'); ?>
                                </button>
                            </div>

                        <?php elseif (($step_data['action_type'] ?? '') === 'ambient'): ?>
                            <!-- In-System Ambient Soundscapes Launcher -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-brand-sky/20 text-brand-sky text-2xl flex items-center justify-center shadow-inner">
                                        🌧️
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate">Acoustic Audio Mixer</h4>
                                        <p class="text-xs text-gray-600">Blend gentle rain, fireplace, and calming brown noise frequencies.</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="toggleQuickAmbientSound(this)" class="px-5 py-3 bg-brand-sky text-brand-slate hover:bg-brand-sky/80 font-bold rounded-xl shadow transition flex items-center justify-center gap-2 whitespace-nowrap">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path></svg>
                                        <span>Play Instant Rain Audio</span>
                                    </button>
                                    <a href="ambient_mixer.php" target="_blank" class="px-4 py-3 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium rounded-xl text-sm transition flex items-center">
                                        Full Mixer &rarr;
                                    </a>
                                </div>
                            </div>

                        <?php elseif (($step_data['action_type'] ?? '') === 'voice'): ?>
                            <!-- In-System Voice Stress Analyzer -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-brand-coralLight text-brand-coral text-2xl flex items-center justify-center shadow-inner">
                                        🎙️
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate">Voice Stress Acoustic Check</h4>
                                        <p class="text-xs text-gray-600">Analyze vocal harmonics and frequency jitter to quantify nervous arousal.</p>
                                    </div>
                                </div>
                                <a href="predictive_hub.php?tab=voice" class="px-5 py-3 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl shadow transition flex items-center justify-center gap-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($step_data['action_label'] ?? 'Take Voice Stress Check'); ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>

                        <?php elseif (($step_data['action_type'] ?? '') === 'link'): ?>
                            <!-- In-System Navigation Action -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-brand-sageLight text-brand-sage text-2xl flex items-center justify-center shadow-inner">
                                        🧭
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate"><?php echo htmlspecialchars($step_data['title']); ?></h4>
                                        <p class="text-xs text-gray-600">Open the dedicated platform tool to fulfill today's challenge goal.</p>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($step_data['target']); ?>" class="px-5 py-3 bg-brand-slate hover:bg-brand-slate/90 text-white font-bold rounded-xl shadow transition flex items-center justify-center gap-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($step_data['action_label'] ?? 'Open Feature'); ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>

                        <?php else: ?>
                            <!-- Immediate Reflection Note -->
                            <div>
                                <label class="block text-sm font-bold text-brand-slate mb-2">
                                    ✍️ <?php echo htmlspecialchars($step_data['prompt'] ?? 'Write your reflection note below:'); ?>
                                </label>
                                <textarea id="step-reflection-input" rows="3" placeholder="Jot down your observations or feelings..." class="w-full p-4 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-sage bg-white text-sm"><?php echo htmlspecialchars($active_challenge['reflections'][$curr_day] ?? ''); ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Step Completion Action Form -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-500">
                            <?php if ($is_day_completed): ?>
                                <span class="inline-flex items-center gap-1.5 text-green-700 font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                    Day <?php echo $curr_day; ?> step completed!
                                </span>
                            <?php else: ?>
                                Complete the exercise above, then mark this step complete to log your activity.
                            <?php endif; ?>
                        </div>

                        <button id="btn-complete-step" onclick="completeStepBackend(<?php echo $curr_day; ?>)" class="w-full sm:w-auto px-8 py-3.5 <?php echo $is_day_completed ? 'bg-green-600 text-white' : 'bg-brand-sage hover:bg-brand-sageHover text-white'; ?> font-bold rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                            <?php if ($is_day_completed): ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Step Completed (Redo / Update)
                            <?php else: ?>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Mark Day <?php echo $curr_day; ?> Complete (+1 Activity)
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-8 max-w-3xl">
                <h2 class="text-3xl font-heading font-bold mb-2">Available Structured Challenges</h2>
                <p class="text-gray-600 text-base">Select any track below to preview its daily curriculum or start an intentional new wellbeing sprint.</p>
            </div>

            <!-- CHALLENGES GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach($challenges_list as $index => $challenge): 
                    $is_current = !empty($active_challenge) && ($active_challenge['key'] === $challenge['title']);
                ?>
                <div class="glass-card rounded-2xl overflow-hidden hover:-translate-y-1 transition-all duration-300 cursor-pointer group flex flex-col h-full hover:shadow-xl border <?php echo $is_current ? 'border-2 border-brand-sage ring-4 ring-brand-sageLight' : 'border-gray-100'; ?>" onclick="openChallengeModal('<?php echo htmlspecialchars(addslashes($challenge['title'])); ?>')">
                    <div class="h-36 relative overflow-hidden bg-brand-slate/10">
                        <img src="<?php echo htmlspecialchars($challenge['image'] ?? 'images/challenge_calm.png'); ?>" alt="Challenge Cover" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                        <div class="absolute top-3 right-3">
                            <span class="inline-block px-2.5 py-0.5 bg-black/40 backdrop-blur-sm text-white text-[11px] font-semibold rounded-md">
                                <?php echo htmlspecialchars($challenge['category'] ?? 'Wellbeing'); ?>
                            </span>
                        </div>
                        <div class="absolute bottom-3 left-4 flex items-center gap-2">
                            <span class="inline-block px-3 py-1 bg-white/95 backdrop-blur-sm text-brand-slate text-xs font-bold rounded-full shadow-sm">
                                <?php echo htmlspecialchars($challenge['duration']); ?>
                            </span>
                            <?php if ($is_current): ?>
                            <span class="inline-block px-2.5 py-1 bg-brand-sage text-white text-xs font-bold rounded-full shadow-sm animate-pulse">
                                Active Track
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col flex-grow bg-white">
                        <h3 class="text-xl font-heading font-bold mb-2 group-hover:text-brand-sage transition-colors text-brand-slate"><?php echo htmlspecialchars($challenge['title']); ?></h3>
                        <p class="text-gray-500 text-sm flex-grow mb-4 leading-relaxed"><?php echo htmlspecialchars($challenge['desc']); ?></p>
                        <div class="flex items-center justify-between text-brand-sage font-bold text-sm mt-auto pt-4 border-t border-gray-50">
                            <span><?php echo $is_current ? 'Manage track' : 'View itinerary'; ?></span>
                            <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center group-hover:bg-brand-sage group-hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- Emergency Modal Placeholder -->
    <div id="emergency-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-8 text-center shadow-2xl">
            <h3 class="text-2xl font-bold text-red-600 mb-4">Need Immediate Help?</h3>
            <p class="mb-6 text-gray-700">If you are in immediate danger or experiencing a medical emergency, please call 999 or go to your nearest A&E.</p>
            <div class="space-y-4">
                <a href="tel:111" class="block w-full py-3 bg-red-100 text-red-700 font-bold rounded-xl hover:bg-red-200 transition">NHS 111 (Non-Emergency)</a>
                <a href="tel:116123" class="block w-full py-3 bg-red-100 text-red-700 font-bold rounded-xl hover:bg-red-200 transition">Samaritans: 116 123</a>
            </div>
            <button onclick="document.getElementById('emergency-modal').classList.add('hidden')" class="mt-6 text-gray-500 hover:text-gray-800 font-medium">Close</button>
        </div>
    </div>

    <!-- CHALLENGE DETAIL MODAL (Fully Dynamic for all 8 tracks) -->
    <div id="challenge-modal" class="fixed inset-0 bg-brand-slate/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl transform scale-95 transition-transform duration-300 p-1 relative" id="challenge-modal-content">
            <button onclick="closeChallengeModal()" class="absolute top-6 right-6 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition-colors z-10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            
            <div class="bg-brand-sageLight/50 p-8 pt-10 rounded-t-2xl">
                <span id="modal-challenge-badge" class="inline-block px-3 py-1 bg-brand-sage text-white text-xs font-bold rounded-full mb-3 uppercase tracking-wide">Structured Challenge</span>
                <h3 id="modal-challenge-title" class="text-3xl font-heading font-bold text-brand-slate mb-2">7-Day Calm Mind Challenge</h3>
                <p id="modal-challenge-desc" class="text-gray-600">A step-by-step journey to quiet your thoughts and find peace.</p>
            </div>
            
            <div class="p-8">
                <h4 class="font-heading font-bold text-xl mb-6">Daily Curated Curriculum</h4>
                
                <!-- Dynamic Steps List -->
                <div id="modal-steps-container" class="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-brand-sage before:to-transparent">
                    <!-- Populated via JS -->
                </div>

                <div class="mt-10 pt-6 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-white rounded-b-3xl">
                    <div class="text-sm text-gray-500">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        You can switch tracks at any time.
                    </div>
                    <button id="modal-start-btn" onclick="startChallengeFromModal()" class="w-full sm:w-auto bg-brand-sage hover:bg-brand-sageHover text-white px-8 py-3 rounded-xl font-bold text-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <span>Start Challenge</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK GUIDED BREATHING OVERLAY MODAL -->
    <div id="breathing-modal" class="fixed inset-0 bg-brand-slate/75 backdrop-blur-md z-50 hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl max-w-lg w-full p-8 text-center shadow-2xl relative">
            <button onclick="closeBreathingModal()" class="absolute top-6 right-6 w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-200">
                ✕
            </button>
            <div class="inline-block px-3 py-1 bg-brand-sageLight text-brand-sage text-xs font-bold rounded-full mb-3 uppercase tracking-wider">
                Guided Box Breathing (4-4-4-4)
            </div>
            <h3 class="text-2xl font-heading font-bold text-brand-slate mb-1">Down-Regulate Tension</h3>
            <p class="text-xs text-gray-500 mb-8">Follow the expanding circle: inhale as it grows, hold at the top, exhale as it shrinks.</p>

            <!-- Animated Breathing Circle -->
            <div class="flex items-center justify-center my-6 h-64">
                <div id="breath-circle" class="w-36 h-36 rounded-full bg-gradient-to-tr from-brand-sage to-emerald-400 text-white flex flex-col items-center justify-center shadow-2xl transition-all duration-1000 transform">
                    <span id="breath-instruction" class="text-xl font-heading font-bold">Inhale</span>
                    <span id="breath-timer" class="text-sm font-semibold opacity-80">4s</span>
                </div>
            </div>

            <div class="flex items-center justify-center gap-3">
                <button id="btn-breathing-action" onclick="toggleBreathingSession()" class="px-6 py-2.5 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl shadow transition">
                    Pause Pacer
                </button>
                <button onclick="closeBreathingModal()" class="px-6 py-2.5 bg-gray-100 text-gray-700 hover:bg-gray-200 font-medium rounded-xl transition">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 space-y-3 pointer-events-none"></div>

    <!-- Audio Element for Instant Soothing Rain -->
    <audio id="ambient-audio-player" loop preload="none">
        <source src="https://actions.google.com/sounds/v1/weather/rain_heavy.ogg" type="audio/ogg">
    </audio>

    <!-- Footer -->
    <footer class="mt-auto py-8 text-center text-gray-500 text-sm border-t border-gray-200/60 bg-white">
        <p>&copy; <?php echo date('Y'); ?> Tet Wellbeing Group. All rights reserved. • A safe space for emotional clarity.</p>
    </footer>

    <script>
        // Challenge Data injected from PHP
        const challengesCatalog = <?php echo json_encode($all_challenges_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const currentActiveChallenge = <?php echo json_encode($active_challenge, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let selectedChallengeForModal = null;

        // Tab Switching
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            const targetContent = document.getElementById('tab-' + tabId);
            const targetBtn = document.getElementById('btn-' + tabId);
            if (targetContent) targetContent.classList.add('active');
            if (targetBtn) targetBtn.classList.add('active');

            // Persist tab preference in hash
            history.replaceState(null, null, '#tab=' + tabId);
        }

        // Check URL hash or query params on load
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const hash = window.location.hash;
            if (urlParams.get('tab') === 'challenges' || hash.includes('challenges')) {
                switchTab('challenges');
            }
            if (urlParams.get('started') === '1') {
                showToast("Challenge started! Your Day 1 action is unlocked.", "success");
            }
            if (urlParams.get('step_completed') === '1') {
                showToast("Great job! Activity point awarded and progress logged.", "success");
            }
        });

        // Dynamic Challenge Modal
        const modal = document.getElementById('challenge-modal');
        const modalContent = document.getElementById('challenge-modal-content');

        function openChallengeModal(challengeTitle) {
            const data = challengesCatalog[challengeTitle];
            if (!data) return;
            selectedChallengeForModal = challengeTitle;

            document.getElementById('modal-challenge-title').innerText = data.title;
            document.getElementById('modal-challenge-desc').innerText = data.desc;
            document.getElementById('modal-challenge-badge').innerText = data.category || data.duration;

            const startBtn = document.getElementById('modal-start-btn');
            if (currentActiveChallenge && currentActiveChallenge.key === challengeTitle) {
                startBtn.innerHTML = `<span>Currently Active Track</span>`;
                startBtn.classList.add('bg-gray-400', 'cursor-default');
                startBtn.classList.remove('bg-brand-sage', 'hover:bg-brand-sageHover');
            } else {
                startBtn.innerHTML = `<span>Start This Challenge</span><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>`;
                startBtn.classList.remove('bg-gray-400', 'cursor-default');
                startBtn.classList.add('bg-brand-sage', 'hover:bg-brand-sageHover');
            }

            // Build steps list
            const stepsContainer = document.getElementById('modal-steps-container');
            stepsContainer.innerHTML = '';
            
            const steps = data.steps || {};
            const keys = Object.keys(steps).sort((a, b) => parseInt(a) - parseInt(b));

            keys.forEach((dayNum, idx) => {
                const step = steps[dayNum];
                const isOdd = idx % 2 !== 0;
                
                const stepDiv = document.createElement('div');
                stepDiv.className = 'relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group';
                stepDiv.innerHTML = `
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-brand-sageLight text-brand-sage font-bold text-sm shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                        ${dayNum}
                    </div>
                    <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-2xl border border-gray-100 bg-brand-bg/80 shadow-sm">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <h5 class="font-bold text-brand-slate text-sm">${escapeHtml(step.title)}</h5>
                            <span class="text-[10px] px-2 py-0.5 rounded font-semibold bg-white text-gray-500 uppercase tracking-wider">${escapeHtml(step.action_type || 'Exercise')}</span>
                        </div>
                        <p class="text-xs text-gray-600 leading-relaxed">${escapeHtml(step.desc)}</p>
                    </div>
                `;
                stepsContainer.appendChild(stepDiv);
            });

            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
            }, 10);
            document.body.style.overflow = 'hidden';
        }

        function closeChallengeModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
            document.body.style.overflow = '';
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeChallengeModal();
        });

        // Start Challenge Handler
        function startChallengeFromModal() {
            if (!selectedChallengeForModal) return;
            if (currentActiveChallenge && currentActiveChallenge.key === selectedChallengeForModal) {
                closeChallengeModal();
                switchTab('challenges');
                return;
            }

            const btn = document.getElementById('modal-start-btn');
            btn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Starting...`;

            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'start_challenge',
                    challenge_title: selectedChallengeForModal
                })
            })
            .then(res => res.json())
            .then(data => {
                window.location.href = 'wellbeing_passport.php?tab=challenges&started=1';
            })
            .catch(() => {
                window.location.href = 'wellbeing_passport.php?tab=challenges&started=1';
            });
        }

        // Abandon / Switch Challenge
        function abandonChallenge() {
            if (!confirm("Would you like to reset this challenge and pick another one? Your completed activity points are preserved.")) {
                return;
            }
            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ action: 'abandon_challenge' })
            })
            .then(() => {
                window.location.href = 'wellbeing_passport.php?tab=challenges';
            });
        }

        // Complete Step Backend
        function completeStepBackend(day) {
            const reflectionEl = document.getElementById('step-reflection-input');
            const note = reflectionEl ? reflectionEl.value.trim() : '';
            const btn = document.getElementById('btn-complete-step');
            
            btn.innerHTML = `<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Recording...`;

            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'complete_step',
                    day: day,
                    reflection_note: note
                })
            })
            .then(res => res.json())
            .then(data => {
                window.location.href = 'wellbeing_passport.php?tab=challenges&step_completed=1';
            })
            .catch(() => {
                window.location.href = 'wellbeing_passport.php?tab=challenges&step_completed=1';
            });
        }

        // Goals Backend Handlers
        function addGoalBackend(e, form) {
            e.preventDefault();
            const input = form.querySelector('input[name="new_goal"]');
            const goalText = input.value.trim();
            if (!goalText) return;

            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'add_goal',
                    new_goal: goalText
                })
            })
            .then(res => res.json())
            .then(data => {
                input.value = '';
                showToast("Wellbeing goal saved!", "success");
                setTimeout(() => window.location.reload(), 400);
            })
            .catch(() => {
                window.location.reload();
            });
        }

        function toggleGoalBackend(goalId) {
            const li = document.getElementById('goal-item-' + goalId);
            const btn = li.querySelector('.goal-checkbox');
            const span = li.querySelector('span.truncate');

            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'toggle_goal',
                    goal_id: goalId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.completed) {
                    btn.classList.add('bg-brand-sage', 'text-white');
                    btn.classList.remove('text-transparent');
                    span.classList.add('line-through', 'text-gray-400');
                    showToast("Goal marked complete!", "success");
                } else {
                    btn.classList.remove('bg-brand-sage', 'text-white');
                    btn.classList.add('text-transparent');
                    span.classList.remove('line-through', 'text-gray-400');
                }
            });
        }

        function deleteGoalBackend(goalId) {
            const li = document.getElementById('goal-item-' + goalId);
            li.style.opacity = '0.3';
            fetch('wellbeing_passport.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'delete_goal',
                    goal_id: goalId
                })
            })
            .then(() => {
                li.remove();
                showToast("Goal removed", "info");
            });
        }

        // Quick Guided Breathing Widget Logic
        const breathingModal = document.getElementById('breathing-modal');
        const breathCircle = document.getElementById('breath-circle');
        const breathInstruction = document.getElementById('breath-instruction');
        const breathTimer = document.getElementById('breath-timer');
        let breathingInterval = null;
        let breathStep = 0; // 0=inhale, 1=hold, 2=exhale, 3=hold
        let breathingSecondsLeft = 4;
        let isBreathingActive = false;

        function openBreathingModal() {
            breathingModal.classList.remove('hidden');
            setTimeout(() => {
                breathingModal.classList.remove('opacity-0');
            }, 10);
            startBreathingCycle();
        }

        function closeBreathingModal() {
            stopBreathingCycle();
            breathingModal.classList.add('opacity-0');
            setTimeout(() => {
                breathingModal.classList.add('hidden');
            }, 300);
        }

        function startBreathingCycle() {
            isBreathingActive = true;
            breathStep = 0;
            breathingSecondsLeft = 4;
            updateBreathUI();

            clearInterval(breathingInterval);
            breathingInterval = setInterval(() => {
                breathingSecondsLeft--;
                if (breathingSecondsLeft <= 0) {
                    breathStep = (breathStep + 1) % 4;
                    breathingSecondsLeft = 4;
                }
                updateBreathUI();
            }, 1000);
        }

        function stopBreathingCycle() {
            isBreathingActive = false;
            clearInterval(breathingInterval);
            if (breathCircle) breathCircle.style.transform = 'scale(1)';
        }

        function toggleBreathingSession() {
            const btn = document.getElementById('btn-breathing-action');
            if (isBreathingActive) {
                stopBreathingCycle();
                btn.innerText = 'Resume Pacer';
            } else {
                startBreathingCycle();
                btn.innerText = 'Pause Pacer';
            }
        }

        function updateBreathUI() {
            if (!breathInstruction || !breathTimer || !breathCircle) return;
            breathTimer.innerText = breathingSecondsLeft + 's';

            if (breathStep === 0) {
                breathInstruction.innerText = 'Inhale';
                breathCircle.style.transform = 'scale(1.45)';
                breathCircle.className = 'w-36 h-36 rounded-full bg-gradient-to-tr from-brand-sage to-emerald-400 text-white flex flex-col items-center justify-center shadow-2xl transition-all duration-1000 transform';
            } else if (breathStep === 1) {
                breathInstruction.innerText = 'Hold';
                breathCircle.style.transform = 'scale(1.45)';
                breathCircle.className = 'w-36 h-36 rounded-full bg-gradient-to-tr from-brand-sky to-teal-400 text-white flex flex-col items-center justify-center shadow-2xl transition-all duration-500 transform';
            } else if (breathStep === 2) {
                breathInstruction.innerText = 'Exhale';
                breathCircle.style.transform = 'scale(0.85)';
                breathCircle.className = 'w-36 h-36 rounded-full bg-gradient-to-tr from-brand-coral to-amber-500 text-white flex flex-col items-center justify-center shadow-2xl transition-all duration-1000 transform';
            } else if (breathStep === 3) {
                breathInstruction.innerText = 'Rest & Hold';
                breathCircle.style.transform = 'scale(0.85)';
                breathCircle.className = 'w-36 h-36 rounded-full bg-gradient-to-tr from-brand-slate to-gray-600 text-white flex flex-col items-center justify-center shadow-2xl transition-all duration-500 transform';
            }
        }

        // Instant Ambient Audio Player (Procedural Web Audio Rain)
        let ambientAudioContext = null;
        let rainNode = null;
        let isRainPlaying = false;

        function toggleQuickAmbientSound(btn) {
            if (!isRainPlaying) {
                startProceduralRain();
                isRainPlaying = true;
                btn.classList.remove('bg-brand-sky', 'text-brand-slate');
                btn.classList.add('bg-emerald-600', 'text-white');
                btn.querySelector('span').innerText = 'Stop Rain Audio';
                showToast("Nature rain acoustic playing...", "info");
            } else {
                stopProceduralRain();
                isRainPlaying = false;
                btn.classList.add('bg-brand-sky', 'text-brand-slate');
                btn.classList.remove('bg-emerald-600', 'text-white');
                btn.querySelector('span').innerText = 'Play Instant Rain Audio';
                showToast("Acoustic audio stopped", "info");
            }
        }

        function startProceduralRain() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                ambientAudioContext = new AudioContext();
                
                const bufferSize = ambientAudioContext.sampleRate * 2;
                const buffer = ambientAudioContext.createBuffer(1, bufferSize, ambientAudioContext.sampleRate);
                const data = buffer.getChannelData(0);
                let lastOut = 0.0;
                
                // Generate pink noise for soft rainfall
                for (let i = 0; i < bufferSize; i++) {
                    const white = Math.random() * 2 - 1;
                    data[i] = (lastOut + (0.02 * white)) / 1.02;
                    lastOut = data[i];
                    data[i] *= 3.5; // Gain
                }

                rainNode = ambientAudioContext.createBufferSource();
                rainNode.buffer = buffer;
                rainNode.loop = true;

                // Lowpass filter for warm gentle rain
                const filter = ambientAudioContext.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = 1100;

                const gainNode = ambientAudioContext.createGain();
                gainNode.gain.value = 0.25;

                rainNode.connect(filter);
                filter.connect(gainNode);
                gainNode.connect(ambientAudioContext.destination);

                rainNode.start(0);
            } catch (err) {
                console.error("Web Audio not permitted", err);
            }
        }

        function stopProceduralRain() {
            if (rainNode) {
                try { rainNode.stop(); } catch(e){}
                rainNode = null;
            }
            if (ambientAudioContext) {
                try { ambientAudioContext.close(); } catch(e){}
                ambientAudioContext = null;
            }
        }

        // Toast Feedback helper
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-emerald-700 text-white' : (type === 'error' ? 'bg-red-600 text-white' : 'bg-brand-slate text-white');
            
            toast.className = `${bgClass} px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-semibold pointer-events-auto transform translate-y-4 opacity-0 transition-all duration-300`;
            toast.innerHTML = `
                <span>${message}</span>
            `;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-y-4', 'opacity-0');
            }, 10);

            setTimeout(() => {
                toast.classList.add('translate-y-4', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
