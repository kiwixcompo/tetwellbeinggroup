<?php
/**
 * Tet Wellbeing Group - Beta Testing System Overlay & Checklist Flow (includes/beta_testing_module.php)
 *
 * Implements:
 * 1. Category Selection Modal (First visit or manual trigger):
 *    - Clinicians & Counselors
 *    - Corporate HR Leaders
 *    - Informal & Family Caregivers
 *    - Peer Support & Volunteers
 *    - Stressed Professionals & Students
 * 2. Floating Persistent Exploration Tracker Badge:
 *    - Shows active testing category
 *    - Displays progress of required pages visited
 *    - Shows real-time button to open or review Checklist
 * 3. Final Checklist Judgment Modal:
 *    - Automatically prompts when required pages have been explored (or can be opened anytime)
 *    - Specific tailored checkboxes for each category
 *    - Star rating (1-5), Likert recommendation, and qualitative feedback box
 *    - Asynchronous submission to beta_testing_api.php
 */

$beta_session = $_SESSION['beta_tester'] ?? null;
$beta_active = !empty($beta_session['active']);
$beta_submitted = !empty($beta_session['submitted']);
$beta_cat_id = $beta_session['category_id'] ?? '';
$beta_cat_title = $beta_session['category_title'] ?? '';
$beta_visited = $beta_session['visited_pages'] ?? [];
$is_tester_login = !empty($_SESSION['is_beta_tester']);
$is_compulsory = $is_tester_login && empty($beta_cat_id);
$current_page_name = basename($_SERVER['PHP_SELF']);
?>

<!-- ========================================== -->
<!-- 1. CATEGORY SELECTION MODAL                -->
<!-- ========================================== -->
<div id="beta-category-modal" class="fixed inset-0 z-[9990] flex items-center justify-center p-4 bg-slate-900/85 backdrop-blur-md hidden transition-opacity duration-300 opacity-0 select-none">
    <div class="relative bg-white w-full max-w-2xl rounded-3xl p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[92vh] overflow-y-auto transform scale-95 transition-transform duration-300">
        
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 pb-5 border-b border-gray-100">
            <div class="flex items-center gap-3.5">
                <div class="h-12 w-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold text-xl shrink-0 shadow-sm">
                    <i class="fa-solid fa-flask-vial"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-coralLight text-brand-coral">
                            Private Beta Program
                        </span>
                        <span class="text-xs text-gray-400 font-medium">Tet Wellbeing Group</span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold font-outfit text-brand-slate mt-0.5">Welcome, Beta Tester!</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php if ($is_compulsory): ?>
                            <span class="text-brand-coral font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i> Selection is required:</span> Please choose your testing profile below to unlock your navigation and pages:
                        <?php else: ?>
                            Select the testing profile that best matches your professional background or personal role:
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if (!$is_compulsory): ?>
            <button type="button" onclick="closeBetaCategoryModal()" class="h-8 w-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-brand-slate flex items-center justify-center transition-colors shrink-0" title="Dismiss for now">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
            <?php endif; ?>
        </div>

        <!-- Category Cards Grid -->
        <div class="grid grid-cols-1 gap-3.5 py-6">
            
            <!-- Category 1 -->
            <button type="button" onclick="selectTesterCategory('clinician', 'Mental Health Clinicians & Counselors')" class="group text-left p-4.5 rounded-2xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 transition-all shadow-sm flex items-start gap-4">
                <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">1. Mental Health Clinicians & Counselors</h3>
                        <span class="text-[10px] font-bold text-purple-600 bg-purple-100/60 px-2 py-0.5 rounded-full shrink-0">Licensed / Accredited</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        Evaluates clinical safety, crisis intervention triggers, AI Companion boundaries, and Teletherapy intake workflows.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-gray-400">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Dashboard, Teletherapy, Assessments</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>

            <!-- Category 2 -->
            <button type="button" onclick="selectTesterCategory('hr_leader', 'Corporate HR Leaders & People Executives')" class="group text-left p-4.5 rounded-2xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 transition-all shadow-sm flex items-start gap-4">
                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base">
                    <i class="fa-solid fa-building-user"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">2. Corporate HR Leaders & People Executives</h3>
                        <span class="text-[10px] font-bold text-blue-600 bg-blue-100/60 px-2 py-0.5 rounded-full shrink-0">B2B / Culture</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        Assesses corporate wellness ROI, team credit allocation, aggregated data anonymity, and subscription transparency.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-gray-400">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Corporate Portal, Subscriptions, Workplace Safety</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>

            <!-- Category 3 -->
            <button type="button" onclick="selectTesterCategory('caregiver', 'Informal & Family Caregivers')" class="group text-left p-4.5 rounded-2xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 transition-all shadow-sm flex items-start gap-4">
                <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">3. Informal & Family Caregivers</h3>
                        <span class="text-[10px] font-bold text-amber-600 bg-amber-100/60 px-2 py-0.5 rounded-full shrink-0">Respite & Carers</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        Validates the Caregiver Hub, Zarit burden score, audio decompression, hands-free speech dictation, and daily micro-respite goals.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-gray-400">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Caregiver Hub, Community, Learning Center</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>

            <!-- Category 4 -->
            <button type="button" onclick="selectTesterCategory('peer_volunteer', 'Peer Support Facilitators & Volunteers')" class="group text-left p-4.5 rounded-2xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 transition-all shadow-sm flex items-start gap-4">
                <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">4. Peer Support Facilitators & Volunteers</h3>
                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-100/60 px-2 py-0.5 rounded-full shrink-0">Community</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        Evaluates the Befriender Support Network, volunteer application academy, virtual coffee meetups, and community forum safety.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-gray-400">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Befriender Network, Community Board, TET Circle</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>

            <!-- Category 5 -->
            <button type="button" onclick="selectTesterCategory('stressed_pro', 'Everyday Wellbeing & Stressed Professionals')" class="group text-left p-4.5 rounded-2xl border border-gray-200 hover:border-brand-sage hover:bg-brand-sageLight/30 transition-all shadow-sm flex items-start gap-4">
                <div class="h-10 w-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base">
                    <i class="fa-solid fa-spa"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors">5. Everyday Wellbeing, Students & Professionals</h3>
                        <span class="text-[10px] font-bold text-teal-600 bg-teal-100/60 px-2 py-0.5 rounded-full shrink-0">MindBloom</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                        Tests daily engagement loops: mood check-in wheel, ambient sound mixer, breathing pacers, VR relaxation, and Wellbeing Passport.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-gray-400">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Dashboard, MindBloom, Wellbeing Passport</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-300 group-hover:text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>

            <!-- Category 6: Full Platform Explorer -->
            <button type="button" onclick="selectTesterCategory('full_explorer', 'Comprehensive / Full Platform Explorer')" class="group text-left p-4.5 rounded-2xl border-2 border-brand-sage/60 bg-gradient-to-r from-brand-sageLight/40 to-white hover:border-brand-sage hover:bg-brand-sageLight/60 transition-all shadow-sm flex items-start gap-4 ring-1 ring-brand-sage/20">
                <div class="h-10 w-10 rounded-xl bg-brand-sage text-white flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform font-bold text-base shadow-sm">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold font-outfit text-brand-slate group-hover:text-brand-sage transition-colors flex items-center gap-1.5">
                            6. Comprehensive / Full Platform Explorer
                            <span class="text-[9px] font-bold text-white bg-brand-sage px-2 py-0.5 rounded-full uppercase tracking-wider">All Access</span>
                        </h3>
                    </div>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                        Explores the entire ecosystem without restrictions: all 4 pillars, clinician tools, corporate portal, caregiver hub, befriender network, and learning center.
                    </p>
                    <div class="flex items-center gap-3 mt-2 text-[10px] font-medium text-brand-slate">
                        <span><i class="fa-regular fa-compass mr-1 text-brand-sage"></i> Required Pages: <strong>Dashboard, Community, MindBloom, TET Academy</strong></span>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-brand-sage transition-colors text-sm mt-3 shrink-0"></i>
            </button>
        </div>

        <!-- Footer Info -->
        <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved text-brand-sage"></i> You will be guided through required pages with a discreet badge.
            </span>
            <?php if (!$is_compulsory): ?>
            <button type="button" onclick="closeBetaCategoryModal()" class="text-gray-400 hover:text-brand-slate text-xs font-semibold underline">
                Skip for now
            </button>
            <?php else: ?>
            <span class="text-[11px] font-bold text-brand-coral uppercase tracking-wider">
                <i class="fa-solid fa-lock mr-1"></i> Selection Compulsory
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- ========================================== -->
<!-- 2. FLOATING EXPLORATION TRACKER BADGE      -->
<!-- ========================================== -->
<div id="beta-tracker-widget" class="fixed bottom-6 left-6 z-[9000] transition-all duration-300">
    <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-card border border-brand-sage/30 p-3 sm:p-3.5 max-w-sm flex items-center gap-3 font-sans">
        
        <button type="button" onclick="openBetaChecklistModal()" class="h-10 w-10 rounded-xl bg-brand-sage text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0 hover:scale-105 transition-transform" title="Click to view your Checklist">
            <i class="fa-solid fa-list-check"></i>
        </button>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-brand-sageLight text-brand-sage font-outfit">
                    Beta Test Mode
                </span>
                <span id="tracker-category-name" class="text-[11px] font-bold text-brand-slate truncate max-w-[170px]">
                    <?php echo htmlspecialchars($beta_cat_title ?: 'Select Category'); ?>
                </span>
            </div>
            
            <div class="flex items-center justify-between gap-2 mt-1">
                <span id="tracker-progress-text" class="text-[10px] text-gray-500">
                    Visited: <strong id="tracker-visited-count">0</strong>/<strong id="tracker-required-count">3</strong> required
                </span>
                <button type="button" onclick="openBetaCategoryModal()" class="text-[10px] text-brand-coral hover:underline font-semibold shrink-0">
                    Switch
                </button>
            </div>
        </div>

        <button type="button" id="tracker-open-checklist-btn" onclick="openBetaChecklistModal()" class="px-2.5 py-1.5 rounded-xl bg-brand-slate hover:bg-brand-sage text-white text-[11px] font-bold transition-colors shrink-0 shadow-sm font-outfit">
            Checklist
        </button>
    </div>
</div>


<!-- ========================================== -->
<!-- 3. BETA CHECKLIST JUDGMENT MODAL           -->
<!-- ========================================== -->
<div id="beta-checklist-modal" class="fixed inset-0 z-[9995] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md hidden transition-opacity duration-300 opacity-0 select-none">
    <div class="relative bg-white w-full max-w-2xl rounded-3xl p-6 sm:p-8 shadow-2xl border border-gray-100 max-h-[92vh] overflow-y-auto transform scale-95 transition-transform duration-300">
        
        <!-- Modal Top -->
        <div class="flex items-start justify-between gap-4 pb-5 border-b border-gray-100">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-sageLight text-brand-sage font-outfit">
                        Beta Feedback Submission
                    </span>
                    <span id="checklist-category-pill" class="text-xs text-gray-500 font-medium"></span>
                </div>
                <h2 id="checklist-modal-title" class="text-xl sm:text-2xl font-bold font-outfit text-brand-slate mt-1">
                    Your Testing Judgment & Checklist
                </h2>
                <p id="checklist-modal-desc" class="text-xs text-gray-500 mt-1">
                    Please tick the checkboxes based on what you experienced across the pages you visited.
                </p>
            </div>
            <button type="button" onclick="closeBetaChecklistModal()" class="h-8 w-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-brand-slate flex items-center justify-center transition-colors shrink-0">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Submission Form -->
        <form id="beta-checklist-form" onsubmit="submitBetaChecklist(event)" class="py-6 space-y-6">
            <input type="hidden" name="action" value="submit_checklist">
            <input type="hidden" name="category_id" id="form-category-id">
            <input type="hidden" name="category_title" id="form-category-title">

            <!-- Dynamic Checkboxes Container -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider">
                        Tailored Verification Items
                    </label>
                    <span id="checklist-checked-counter" class="text-[11px] font-bold text-brand-sage">
                        0 / 0 Completed
                    </span>
                </div>
                
                <div id="checklist-items-container" class="space-y-2.5 bg-brand-bg/60 p-4 rounded-2xl border border-gray-100">
                    <!-- Injected dynamically via JS based on Category -->
                </div>
            </div>

            <!-- Overall Rating -->
            <div>
                <label class="block text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider mb-2">
                    Overall Experience Rating
                </label>
                <div class="flex items-center gap-2" id="star-rating-container">
                    <input type="hidden" name="overall_rating" id="form-overall-rating" value="5">
                    <button type="button" onclick="setRating(1)" class="star-btn text-2xl text-amber-400 hover:scale-110 transition-transform"><i class="fa-solid fa-star"></i></button>
                    <button type="button" onclick="setRating(2)" class="star-btn text-2xl text-amber-400 hover:scale-110 transition-transform"><i class="fa-solid fa-star"></i></button>
                    <button type="button" onclick="setRating(3)" class="star-btn text-2xl text-amber-400 hover:scale-110 transition-transform"><i class="fa-solid fa-star"></i></button>
                    <button type="button" onclick="setRating(4)" class="star-btn text-2xl text-amber-400 hover:scale-110 transition-transform"><i class="fa-solid fa-star"></i></button>
                    <button type="button" onclick="setRating(5)" class="star-btn text-2xl text-amber-400 hover:scale-110 transition-transform"><i class="fa-solid fa-star"></i></button>
                    <span id="rating-label" class="text-xs font-bold text-gray-600 ml-2">5 - Excellent & Very Intuitive</span>
                </div>
            </div>

            <!-- Recommendation Question -->
            <div>
                <label class="block text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider mb-2">
                    Would you recommend or champion this platform to peers in your space?
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="recommendation" value="yes" checked class="peer sr-only">
                        <div class="p-3 rounded-xl border border-gray-200 peer-checked:border-brand-sage peer-checked:bg-brand-sageLight/50 text-center text-xs font-bold text-brand-slate transition-all">
                            👍 Yes, definitely
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="recommendation" value="maybe" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-gray-200 peer-checked:border-brand-sage peer-checked:bg-brand-sageLight/50 text-center text-xs font-bold text-brand-slate transition-all">
                            🤔 Maybe, with tweaks
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="recommendation" value="no" class="peer sr-only">
                        <div class="p-3 rounded-xl border border-gray-200 peer-checked:border-brand-sage peer-checked:bg-brand-sageLight/50 text-center text-xs font-bold text-brand-slate transition-all">
                            👎 Not yet
                        </div>
                    </label>
                </div>
            </div>

            <!-- Written Judgment / Comments -->
            <div>
                <label class="block text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider mb-1.5">
                    Your Professional Judgment, Criticisms or Suggestions
                </label>
                <textarea name="feedback" rows="3" required placeholder="What felt smoothest? Did anything feel confusing, slow, or clinically/functionally missing?" class="w-full text-xs rounded-xl border border-gray-200 p-3 bg-white focus:border-brand-sage focus:outline-none resize-none leading-relaxed"></textarea>
            </div>

            <!-- Tester Contact Info (Optional for anonymous testing) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2 border-t border-gray-100">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase">Your Name / Handle</label>
                        <span class="text-[10px] text-gray-400 font-semibold">(Anonymous by default)</span>
                    </div>
                    <input type="text" name="tester_name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Anonymous Beta Tester'); ?>" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase">LinkedIn Profile / Email</label>
                        <span class="text-[10px] text-gray-400 font-semibold">(Optional)</span>
                    </div>
                    <input type="text" name="tester_email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? 'anonymous@beta.tetwellbeing.com'); ?>" placeholder="Optional — leave as is for 100% anonymous" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                </div>
            </div>

            <!-- Submit CTA -->
            <div class="pt-4 flex items-center justify-between gap-3">
                <button type="button" onclick="closeBetaChecklistModal()" class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold transition-all">
                    Continue Exploring
                </button>
                <button type="submit" id="submit-judgment-btn" class="px-6 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold shadow-md transition-all flex items-center gap-2 font-outfit active:scale-95">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Submit Beta Judgment</span>
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ========================================== -->
<!-- 4. JAVASCRIPT SYSTEM ORCHESTRATION         -->
<!-- ========================================== -->
<script>
const BETA_TESTING_DATA = {
    categories: {
        clinician: {
            title: "Mental Health Clinicians & Counselors",
            requiredPages: ["dashboard.php", "teletherapy_hub.php", "assessments.php"],
            items: [
                "Emergency red button trigger: Quick-exit behavior & instant crisis hotline access (999, 111, Shout, Samaritans) evaluated",
                "AI distress phrase safeguards: Tested phrases like 'I feel overwhelmed / I cannot cope' and observed crisis response prompts",
                "AI Companion clinical tone: Non-diagnostic, validating language without overstepping boundaries",
                "Teletherapy Services: Therapist credentials, specialities, hourly rates and booking intake flow",
                "Clinical Assessment scales: PHQ-9, GAD-7, Zarit burden score calculations and sensitivity"
            ]
        },
        hr_leader: {
            title: "Corporate HR Leaders & People Executives",
            requiredPages: ["corporate_portal.php", "subscription.php", "workplace_safety.php"],
            items: [
                "Corporate Portal KPIs: Reviewed Total Staff, Active Members, and Credit consumption meters",
                "Staff Management Roster: Tested inviting staff member and reviewing status badge changes",
                "Data Privacy & Anonymity: Verified that analytics show only aggregated wellbeing themes without exposing personal private notes",
                "Workplace Safety & Surveys: Tested department safety indicators and anonymous feedback loop",
                "Subscription & Billing Tiers: Verified clear designation of Corporate vs Professional tier features and package entitlements"
            ]
        },
        caregiver: {
            title: "Informal & Family Caregivers",
            requiredPages: ["caregiver_hub.php", "community.php", "tet_academy.php"],
            items: [
                "Zarit Burnout Self-Assessment: Completed tracker and verified personalized recommendations",
                "Speech-to-Text Input: Tested microphone dictation into reflections and resilience plans",
                "Daily Micro-Goals: Checked off caregiving micro-respite habits and verified progress update",
                "Audio Decompression: Played calming breathwork / rain tracks in Caregiver Hub",
                "Caregiver Academy: Reviewed bite-sized resilience modules and video takeaways"
            ]
        },
        peer_volunteer: {
            title: "Peer Support Facilitators & Volunteers",
            requiredPages: ["befriender_network.php", "community.php", "tet_circle.php"],
            items: [
                "Sub-Tab Navigation: Tested switching between Match Me, My Check-ins, Coffee & Conversation, and Academy",
                "Volunteer Matching Filters: Filtered by language and lived experiences (Caregiving, Bereavement, Veteran)",
                "Virtual Coffee & Icebreaker deck: Simulated session check-in and conversation starter prompt cards",
                "Become a Befriender Academy: Reviewed the volunteer application process",
                "Community Circles: Tested anonymous posting and empathy reactions (I Hear You, You're Not Alone)"
            ]
        },
        stressed_pro: {
            title: "Everyday Wellbeing, Students & Professionals",
            requiredPages: ["dashboard.php", "mindbloom.php", "wellbeing_passport.php"],
            items: [
                "Daily Mood Check-In: Logged emotion on the mood wheel and verified adaptive quote & recommendations",
                "MindBloom Ambient Mixer: Adjusted multi-track soundscapes (rain, forest, waves, white noise)",
                "Breathing Exercise Pacers: Tested Box Breathing / 4-7-8 visual pacing animation",
                "VR Resilience Immersion: Launched relaxation visualizer and checked full-screen performance",
                "Wellbeing Passport: Verified 7-day challenge cards and completed activity streaks"
            ]
        },
        full_explorer: {
            title: "Comprehensive / Full Platform Explorer",
            requiredPages: ["dashboard.php", "community.php", "mindbloom.php", "tet_academy.php"],
            items: [
                "4-Pillar Global Architecture: Smooth seamless navigation between My TET, Community & Care, MindBloom, and Learning Center",
                "Interactive Floating AI Companion: Tested natural dialogue, context awareness, and safety guidelines across views",
                "Crisis Safety Features: Verified Quick Exit red emergency button, helpline dialogs, and instant reassurance triggers",
                "Community & Caregiver Respite: Checked peer circle participation, befriender matching, and respite tracking",
                "MindBloom Sensory Immersion: Tested audio soundscape mixer, breathing pacers, and mental wellbeing exercises",
                "Overall Platform Cohesion & Readiness: System performance, ease of use, and professional clinical atmosphere"
            ]
        }
    }
};

let currentTesterSession = <?php echo json_encode($beta_session ?: ['active' => false, 'category_id' => '', 'category_title' => '', 'visited_pages' => []]); ?>;
let currentPageName = <?php echo json_encode($current_page_name); ?>;
const isCompulsory = <?php echo $is_compulsory ? 'true' : 'false'; ?>;

document.addEventListener('DOMContentLoaded', () => {
    initBetaTestingSystem();
});

function initBetaTestingSystem() {
    // If compulsory (logged in as beta tester without a category), force modal immediately and ignore dismissal
    if (isCompulsory || (!currentTesterSession || !currentTesterSession.category_id)) {
        if (isCompulsory) {
            // Force modal open immediately without escape or dismissal
            setTimeout(() => {
                openBetaCategoryModal();
            }, 300);
        } else {
            const hasDismissed = sessionStorage.getItem('tet_beta_modal_dismissed');
            if (!hasDismissed) {
                setTimeout(() => {
                    openBetaCategoryModal();
                }, 1200);
            }
        }
    } else {
        // Automatically log current page visit
        logCurrentPageVisit();
    }

    updateTrackerWidgetUI();
}

function openBetaCategoryModal() {
    const modal = document.getElementById('beta-category-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('.transform').classList.remove('scale-95');
        modal.querySelector('.transform').classList.add('scale-100');
    }, 10);
}

function closeBetaCategoryModal() {
    if (isCompulsory && (!currentTesterSession || !currentTesterSession.category_id)) {
        // Cannot dismiss when compulsory
        return;
    }
    sessionStorage.setItem('tet_beta_modal_dismissed', '1');
    const modal = document.getElementById('beta-category-modal');
    if (!modal) return;
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.add('scale-95');
    modal.querySelector('.transform').classList.remove('scale-100');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function selectTesterCategory(catId, catTitle) {
    const formData = new FormData();
    formData.append('action', 'select_category');
    formData.append('category_id', catId);
    formData.append('category_title', catTitle);

    fetch('beta_testing_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentTesterSession = {
                active: true,
                category_id: catId,
                category_title: catTitle,
                visited_pages: [currentPageName]
            };
            closeBetaCategoryModal();
            // Toast notification
            showBetaToast(`Profile activated: ${catTitle}. Opening your workspace...`);
            
            // Navigate directly to the category's primary landing page to prevent blank page gating
            const targetUrl = data.redirect_url || 'dashboard.php';
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 600);
        }
    })
    .catch(err => console.error('Error activating tester category:', err));
}

function logCurrentPageVisit() {
    if (!currentTesterSession || !currentTesterSession.category_id) return;

    const formData = new FormData();
    formData.append('action', 'log_visit');
    formData.append('page', currentPageName);

    fetch('beta_testing_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentTesterSession.visited_pages = data.visited_pages || [];
            updateTrackerWidgetUI();

            // Check if all required pages for this category are now completed
            checkIfRequiredPagesExplored();
        }
    })
    .catch(err => console.error('Error logging visit:', err));
}

function checkIfRequiredPagesExplored() {
    if (!currentTesterSession || !currentTesterSession.category_id) return;
    const catData = BETA_TESTING_DATA.categories[currentTesterSession.category_id];
    if (!catData) return;

    const required = catData.requiredPages;
    const visited = currentTesterSession.visited_pages || [];

    const allVisited = required.every(p => visited.includes(p));

    if (allVisited && !currentTesterSession.submitted) {
        const hasAutoPrompted = sessionStorage.getItem('tet_beta_prompted_' + currentTesterSession.category_id);
        if (!hasAutoPrompted) {
            sessionStorage.setItem('tet_beta_prompted_' + currentTesterSession.category_id, '1');
            setTimeout(() => {
                showBetaToast('🎉 You have explored the required pages! Opening your judgment checklist...', 4000);
                setTimeout(() => {
                    openBetaChecklistModal(true);
                }, 1000);
            }, 800);
        }
    }
}

function updateTrackerWidgetUI() {
    const widget = document.getElementById('beta-tracker-widget');
    if (!widget) return;

    if (!currentTesterSession || !currentTesterSession.category_id) {
        document.getElementById('tracker-category-name').innerText = 'Select Profile';
        document.getElementById('tracker-visited-count').innerText = '0';
        document.getElementById('tracker-required-count').innerText = '0';
        return;
    }

    const catData = BETA_TESTING_DATA.categories[currentTesterSession.category_id];
    if (!catData) return;

    document.getElementById('tracker-category-name').innerText = catData.title;

    const required = catData.requiredPages;
    const visited = currentTesterSession.visited_pages || [];
    const countVisited = required.filter(p => visited.includes(p)).length;

    document.getElementById('tracker-visited-count').innerText = countVisited;
    document.getElementById('tracker-required-count').innerText = required.length;

    const openBtn = document.getElementById('tracker-open-checklist-btn');
    if (countVisited >= required.length) {
        openBtn.className = 'px-3 py-1.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white text-[11px] font-bold transition-colors shrink-0 shadow-sm font-outfit animate-pulse';
        openBtn.innerText = 'Submit Review ✓';
    } else {
        openBtn.className = 'px-2.5 py-1.5 rounded-xl bg-brand-slate hover:bg-brand-sage text-white text-[11px] font-bold transition-colors shrink-0 shadow-sm font-outfit';
        openBtn.innerText = 'Checklist';
    }
}

function openBetaChecklistModal(isAutoPrompted = false) {
    if (!currentTesterSession || !currentTesterSession.category_id) {
        openBetaCategoryModal();
        return;
    }

    const catData = BETA_TESTING_DATA.categories[currentTesterSession.category_id];
    if (!catData) return;

    document.getElementById('checklist-category-pill').innerText = catData.title;
    document.getElementById('form-category-id').value = currentTesterSession.category_id;
    document.getElementById('form-category-title').value = catData.title;

    if (isAutoPrompted) {
        document.getElementById('checklist-modal-title').innerText = "🎉 Required Pages Explored! Share Your Judgment";
    } else {
        document.getElementById('checklist-modal-title').innerText = "Your Testing Judgment & Checklist";
    }

    // Populate checklist checkboxes
    const container = document.getElementById('checklist-items-container');
    container.innerHTML = '';

    catData.items.forEach((itemText, idx) => {
        const itemDiv = document.createElement('label');
        itemDiv.className = 'flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 hover:border-brand-sage transition-colors cursor-pointer select-none';
        itemDiv.innerHTML = `
            <input type="checkbox" name="checklist[${idx}]" value="${itemText}" onchange="updateCheckedCount()" class="beta-item-cb mt-0.5 h-4 w-4 rounded text-brand-sage focus:ring-brand-sage border-gray-300">
            <span class="text-xs text-brand-slate leading-relaxed flex-1">${itemText}</span>
        `;
        container.appendChild(itemDiv);
    });

    updateCheckedCount();

    const modal = document.getElementById('beta-checklist-modal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('.transform').classList.remove('scale-95');
        modal.querySelector('.transform').classList.add('scale-100');
    }, 10);
}

function closeBetaChecklistModal() {
    const modal = document.getElementById('beta-checklist-modal');
    modal.classList.add('opacity-0');
    modal.querySelector('.transform').classList.add('scale-95');
    modal.querySelector('.transform').classList.remove('scale-100');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function updateCheckedCount() {
    const cbs = document.querySelectorAll('.beta-item-cb');
    const checked = Array.from(cbs).filter(cb => cb.checked).length;
    document.getElementById('checklist-checked-counter').innerText = `${checked} / ${cbs.length} Completed`;
}

function setRating(rating) {
    document.getElementById('form-overall-rating').value = rating;
    const starBtns = document.querySelectorAll('.star-btn');
    const labels = [
        "1 - Major Improvements Needed",
        "2 - Below Expectations",
        "3 - Functional / Average",
        "4 - Very Good & Capable",
        "5 - Excellent & Very Intuitive"
    ];

    starBtns.forEach((btn, idx) => {
        if (idx < rating) {
            btn.classList.add('text-amber-400');
            btn.classList.remove('text-gray-300');
        } else {
            btn.classList.remove('text-amber-400');
            btn.classList.add('text-gray-300');
        }
    });

    document.getElementById('rating-label').innerText = labels[rating - 1];
}

function submitBetaChecklist(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-judgment-btn');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    const form = document.getElementById('beta-checklist-form');
    const formData = new FormData(form);

    fetch('beta_testing_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origText;
        if (data.success) {
            closeBetaChecklistModal();
            showBetaToast('✅ Judgment recorded! Thank you for supporting Tet Wellbeing Group.');
            if (currentTesterSession) {
                currentTesterSession.submitted = true;
            }
            updateTrackerWidgetUI();
        } else {
            alert('Submission error: ' + (data.error || 'Please try again'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origText;
        console.error('Error submitting feedback:', err);
    });
}

function showBetaToast(message, duration = 3500) {
    let toast = document.getElementById('beta-global-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'beta-global-toast';
        toast.className = 'fixed top-5 left-1/2 -translate-x-1/2 z-[10005] bg-brand-slate text-white px-5 py-3 rounded-2xl shadow-2xl text-xs font-bold transition-all duration-300 opacity-0 pointer-events-none flex items-center gap-2 border border-white/10 font-outfit';
        document.body.appendChild(toast);
    }

    toast.innerHTML = `<i class="fa-solid fa-circle-check text-brand-sage"></i> ${message}`;
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0', 'pointer-events-none');
    }, duration);
}
</script>
