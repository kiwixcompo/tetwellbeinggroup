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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Self Assessments - Tet Wellbeing Group</title>
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

    <main class="flex-grow max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        <!-- Hero Section -->
        <div class="bg-brand-sageLight rounded-3xl p-8 sm:p-12 mb-10 text-center relative overflow-hidden border border-brand-sage/20 shadow-sm">
            <div class="relative z-10 max-w-2xl mx-auto">
                <div class="inline-flex items-center gap-2 mb-2">
                    <?php echo getPlanBadge('free'); ?>
                </div>
                <h1 class="text-4xl sm:text-5xl font-heading font-bold text-brand-slate mb-4">Self Assessments</h1>
                <p class="text-lg text-brand-slate opacity-80 max-w-xl mx-auto">
                    Clinically validated tools to help you better understand your mental health.
                </p>
            </div>
        </div>

        <div id="assessment-list" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- PHQ-9 Card -->
            <div class="bg-brand-cardBg rounded-2xl p-6 shadow-sm border border-brand-sageLight hover:shadow-md transition">
                <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">PHQ-9 (Depression)</h3>
                <p class="text-sm text-brand-slate opacity-80 mb-6">A brief 9-item questionnaire that helps identify and measure the severity of depression symptoms over the last 2 weeks.</p>
                <button onclick="startAssessment('phq9')" class="w-full bg-brand-sage text-white py-2.5 rounded-xl font-medium hover:bg-brand-sageHover transition">
                    Start Assessment
                </button>
            </div>

            <!-- GAD-7 Card -->
            <div class="bg-brand-cardBg rounded-2xl p-6 shadow-sm border border-brand-sageLight hover:shadow-md transition">
                <h3 class="text-xl font-heading font-bold text-brand-slate mb-2">GAD-7 (Anxiety)</h3>
                <p class="text-sm text-brand-slate opacity-80 mb-6">A 7-item questionnaire designed to screen for and measure the severity of generalized anxiety disorder symptoms.</p>
                <button onclick="startAssessment('gad7')" class="w-full bg-brand-sage text-white py-2.5 rounded-xl font-medium hover:bg-brand-sageHover transition">
                    Start Assessment
                </button>
            </div>
        </div>

        <!-- Assessment Container -->
        <div id="assessment-container" class="hidden bg-brand-cardBg rounded-3xl p-8 shadow-sm border border-brand-sageLight">
            <button onclick="backToList()" class="text-brand-sage font-medium mb-6 flex items-center hover:text-brand-sageHover transition">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Assessments
            </button>
            
            <h2 id="assessment-title" class="text-2xl font-heading font-bold text-brand-slate mb-2"></h2>
            <p class="text-sm text-gray-500 mb-8 italic">Over the last 2 weeks, how often have you been bothered by the following problems?</p>

            <form id="assessment-form" onsubmit="submitAssessment(event)">
                <div id="questions-container" class="space-y-6 mb-8">
                    <!-- Questions injected here -->
                </div>
                
                <button type="submit" class="w-full bg-brand-slate text-white py-3 rounded-xl font-bold hover:bg-opacity-90 transition">
                    Get Results
                </button>
            </form>

            <div id="results-container" class="hidden mt-8 p-6 rounded-2xl bg-brand-sageLight border border-brand-sage/20">
                <!-- Results injected here -->
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        const assessmentsData = {
            'phq9': {
                title: 'PHQ-9 (Depression Assessment)',
                questions: [
                    "Little interest or pleasure in doing things",
                    "Feeling down, depressed, or hopeless",
                    "Trouble falling or staying asleep, or sleeping too much",
                    "Feeling tired or having little energy",
                    "Poor appetite or overeating",
                    "Feeling bad about yourself — or that you are a failure or have let yourself or your family down",
                    "Trouble concentrating on things, such as reading the newspaper or watching television",
                    "Moving or speaking so slowly that other people could have noticed? Or the opposite — being so fidgety or restless that you have been moving around a lot more than usual",
                    "Thoughts that you would be better off dead or of hurting yourself in some way"
                ],
                options: [
                    { label: "Not at all", score: 0 },
                    { label: "Several days", score: 1 },
                    { label: "More than half the days", score: 2 },
                    { label: "Nearly every day", score: 3 }
                ],
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Minimal depression', color: 'text-brand-sage', message: 'Your score suggests minimal or no depression. Keep practicing good self-care!' };
                    if (score <= 9) return { level: 'Mild depression', color: 'text-yellow-600', message: 'Your score suggests mild depression. Consider monitoring your symptoms and engaging in our Community groups.' };
                    if (score <= 14) return { level: 'Moderate depression', color: 'text-orange-500', message: 'Your score suggests moderate depression. We highly recommend connecting with a Befriender or checking our Academy resources.' };
                    if (score <= 19) return { level: 'Moderately severe depression', color: 'text-brand-coral', message: 'Your score suggests moderately severe depression. Please consider discussing these results with a healthcare professional.' };
                    return { level: 'Severe depression', color: 'text-red-600', message: 'Your score suggests severe depression. Please reach out to a professional immediately. Use our Emergency Support button if you need immediate help.' };
                }
            },
            'gad7': {
                title: 'GAD-7 (Anxiety Assessment)',
                questions: [
                    "Feeling nervous, anxious or on edge",
                    "Not being able to stop or control worrying",
                    "Worrying too much about different things",
                    "Trouble relaxing",
                    "Being so restless that it is hard to sit still",
                    "Becoming easily annoyed or irritable",
                    "Feeling afraid as if something awful might happen"
                ],
                options: [
                    { label: "Not at all", score: 0 },
                    { label: "Several days", score: 1 },
                    { label: "More than half the days", score: 2 },
                    { label: "Nearly every day", score: 3 }
                ],
                getFeedback: (score) => {
                    if (score <= 4) return { level: 'Minimal anxiety', color: 'text-brand-sage', message: 'Your score suggests minimal anxiety.' };
                    if (score <= 9) return { level: 'Mild anxiety', color: 'text-yellow-600', message: 'Your score suggests mild anxiety. Breathing exercises in the hub may help you relax.' };
                    if (score <= 14) return { level: 'Moderate anxiety', color: 'text-orange-500', message: 'Your score suggests moderate anxiety. A chat with a Befriender might help you process these feelings.' };
                    return { level: 'Severe anxiety', color: 'text-red-600', message: 'Your score suggests severe anxiety. We recommend discussing this with a healthcare professional.' };
                }
            }
        };

        let currentAssessment = null;

        function startAssessment(type) {
            currentAssessment = type;
            const data = assessmentsData[type];
            
            document.getElementById('assessment-list').classList.add('hidden');
            document.getElementById('assessment-container').classList.remove('hidden');
            document.getElementById('assessment-title').textContent = data.title;
            document.getElementById('results-container').classList.add('hidden');
            document.getElementById('results-container').innerHTML = '';
            
            const qContainer = document.getElementById('questions-container');
            let html = '';
            
            data.questions.forEach((q, i) => {
                html += `
                    <div class="bg-brand-inputBg p-5 rounded-xl border border-gray-100">
                        <p class="font-medium text-brand-slate mb-4">${i+1}. ${q}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                `;
                data.options.forEach((opt, j) => {
                    html += `
                        <label class="flex items-center p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-brand-sageLight hover:border-brand-sage/30 transition bg-white">
                            <input type="radio" required name="q${i}" value="${opt.score}" class="mr-2 text-brand-sage focus:ring-brand-sage">
                            <span class="text-sm text-gray-600">${opt.label}</span>
                        </label>
                    `;
                });
                html += `</div></div>`;
            });
            
            qContainer.innerHTML = html;
            window.scrollTo(0, 0);
        }

        function backToList() {
            document.getElementById('assessment-list').classList.remove('hidden');
            document.getElementById('assessment-container').classList.add('hidden');
            document.getElementById('assessment-form').reset();
            currentAssessment = null;
        }

        function submitAssessment(e) {
            e.preventDefault();
            const data = assessmentsData[currentAssessment];
            let score = 0;
            
            for(let i=0; i<data.questions.length; i++) {
                const selected = document.querySelector(`input[name="q${i}"]:checked`);
                if(selected) {
                    score += parseInt(selected.value);
                }
            }
            
            const feedback = data.getFeedback(score);
            const resultsDiv = document.getElementById('results-container');
            
            resultsDiv.innerHTML = `
                <div class="text-center mb-4">
                    <h3 class="text-3xl font-heading font-bold text-brand-slate">Score: ${score}</h3>
                    <p class="text-lg font-bold ${feedback.color} mt-2">${feedback.level}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-brand-sage/10 text-sm text-gray-600 leading-relaxed text-center">
                    ${feedback.message}
                </div>
                <div class="mt-6 bg-brand-coralLight/30 border border-brand-coral/20 rounded-xl p-4 text-center">
                    <p class="text-xs font-semibold text-brand-slate mb-2">
                        <i class="fa-solid fa-circle-info text-brand-coral mr-1"></i>
                        Clinical Disclaimer: This tool is for informational purposes only and does not provide a clinical diagnosis.
                    </p>
                    <p class="text-xs text-gray-600">
                        If you need support, please connect with our <a href="community.php" class="text-brand-sage font-bold hover:underline">Befriender Network</a> or explore <a href="teletherapy_hub.php" class="text-brand-sage font-bold hover:underline">Teletherapy Services</a> for professional help.
                    </p>
                </div>
            `;
            
            resultsDiv.classList.remove('hidden');
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    
    <?php require_once 'includes/global_ai_widget.php'; ?>
</body>
</html>
