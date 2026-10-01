<?php
/**
 * Tet Wellbeing Group - Global Progressive Disclosure Navigation (nav_menu.php)
 * Phase 1: Streamlined 4-Pillar Global Navbar
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Determine active pillar based on deep links as well
$active_pillar = 'dashboard';
if (in_array($current_page, ['dashboard.php', 'wellbeing_passport.php', 'subscription.php'])) {
    $active_pillar = 'dashboard';
} elseif (in_array($current_page, ['mindbloom.php', 'truevent.php', 'assessments.php', 'vr_resilience.php', 'ambient_mixer.php', 'breathing_exercises.php'])) {
    $active_pillar = 'mindbloom';
} elseif (in_array($current_page, ['community.php', 'caregiver_hub.php', 'teletherapy_hub.php', 'befriender_network.php'])) {
    $active_pillar = 'community';
} elseif (in_array($current_page, ['tet_academy.php', 'streaming_hub.php', 'research_hub.php'])) {
    $active_pillar = 'academy';
}

$user_initial = 'U';
$user_name = 'User';
if (isset($_SESSION['user_name'])) {
    $user_name = $_SESSION['user_name'];
    $user_initial = strtoupper(substr($user_name, 0, 1));
}

// Check role for HR links
$user_role = $_SESSION['user_role'] ?? 'user';
?>
<!-- PHASE 1: GLOBAL NAVIGATION BAR -->
<header class="sticky top-0 z-50 w-full border-b border-gray-100 bg-white/90 backdrop-blur-lg">
    <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        
        <!-- Left: Brand Logo -->
        <a href="dashboard.php" class="flex shrink-0 items-center transition-transform hover:scale-[1.02] active:scale-95">
            <img src="logo.svg" alt="Tet Wellbeing" class="h-10 w-auto">
            <span class="ml-2 font-bold font-outfit text-xl text-brand-slate tracking-tight hidden lg:block">TET Wellbeing</span>
        </a>

        <!-- Center: 4-Pillar Navigation -->
        <nav class="hidden md:flex items-center h-full gap-8">
            <a href="dashboard.php" class="relative flex items-center h-full text-sm font-semibold transition-colors <?php echo $active_pillar === 'dashboard' ? 'text-brand-sage' : 'text-gray-500 hover:text-brand-slate'; ?> group">
                My TET
                <span class="absolute bottom-0 left-0 w-full h-[3px] rounded-t-md bg-brand-sage transition-transform duration-300 <?php echo $active_pillar === 'dashboard' ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'; ?>"></span>
            </a>
            
            <a href="community.php" class="relative flex items-center h-full text-sm font-semibold transition-colors <?php echo $active_pillar === 'community' ? 'text-brand-sage' : 'text-gray-500 hover:text-brand-slate'; ?> group">
                Community & Care
                <span class="absolute bottom-0 left-0 w-full h-[3px] rounded-t-md bg-brand-sage transition-transform duration-300 <?php echo $active_pillar === 'community' ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'; ?>"></span>
            </a>

            <a href="mindbloom.php" class="relative flex items-center h-full text-sm font-semibold transition-colors <?php echo $active_pillar === 'mindbloom' ? 'text-brand-sage' : 'text-gray-500 hover:text-brand-slate'; ?> group">
                MindBloom
                <span class="absolute bottom-0 left-0 w-full h-[3px] rounded-t-md bg-brand-sage transition-transform duration-300 <?php echo $active_pillar === 'mindbloom' ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'; ?>"></span>
            </a>
            
            <a href="tet_academy.php" class="relative flex items-center h-full text-sm font-semibold transition-colors <?php echo $active_pillar === 'academy' ? 'text-brand-sage' : 'text-gray-500 hover:text-brand-slate'; ?> group">
                Learning Center
                <span class="absolute bottom-0 left-0 w-full h-[3px] rounded-t-md bg-brand-sage transition-transform duration-300 <?php echo $active_pillar === 'academy' ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'; ?>"></span>
            </a>
        </nav>

        <!-- Right: Profile & Settings -->
        <div class="flex shrink-0 items-center gap-3 sm:gap-4">
            <!-- Preview Mode Badge -->
            <span class="hidden xl:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[11px] font-bold font-outfit shadow-sm" title="All features are currently unlocked for testing. Badges show the required production plan.">
                <i class="fa-solid fa-unlock text-emerald-500 text-[10px]"></i> All Features Unlocked (Preview)
            </span>

            <!-- On-Demand Walkthrough / Section Guide Trigger -->
            <button onclick="startSectionTour()" class="flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-brand-sageLight text-brand-sage hover:bg-brand-sage hover:text-white text-xs font-bold transition-all shadow-sm active:scale-95 group" title="Interactive Section Guide">
                <i class="fa-solid fa-circle-question text-sm group-hover:rotate-12 transition-transform"></i>
                <span class="hidden sm:inline font-outfit">Show Me Around</span>
            </button>

            <!-- Mobile Menu Toggle (Visible only on mobile) -->
            <button class="md:hidden p-2 text-gray-500 hover:text-brand-slate" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>

            <!-- User Avatar & Dropdown -->
            <div class="relative group cursor-pointer">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-sage text-white font-bold text-sm shadow-md transition-transform group-hover:scale-105">
                    <?php echo $user_initial; ?>
                </div>
                
                <!-- Dropdown Menu -->
                <div class="absolute right-0 top-full pt-2 w-56 origin-top-right opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto z-50">
                    <div class="rounded-2xl bg-white p-2 shadow-2xl border border-gray-100 flex flex-col gap-1">
                        <div class="px-3 py-2 border-b border-gray-100 mb-1">
                            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Signed in as</p>
                            <p class="text-sm font-bold text-brand-slate truncate"><?php echo htmlspecialchars($user_name); ?></p>
                        </div>

                        <button onclick="startSectionTour()" class="flex items-center gap-2 px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-semibold text-left w-full">
                            <i class="fa-solid fa-compass w-4 text-center"></i> Show Me Around
                        </button>
                        
                        <a href="subscription.php" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                            <i class="fa-solid fa-file-invoice-dollar w-4 text-center"></i> Subscription & Billing
                        </a>
                        
                        <?php if ($user_role === 'hr' || $user_role === 'admin'): ?>
                        <div class="my-1 border-t border-gray-100"></div>
                        <p class="px-3 py-1 mt-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Admin Tools</p>
                        <a href="corporate_portal.php" class="flex items-center gap-2 px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">
                            <i class="fa-solid fa-building w-4 text-center"></i> Corporate Portal
                        </a>
                        <a href="workplace_safety.php" class="flex items-center gap-2 px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">
                            <i class="fa-solid fa-hard-hat w-4 text-center"></i> Workplace Safety
                        </a>
                        <?php endif; ?>
                        
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="logout.php" class="flex items-center gap-2 px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-bold">
                            <i class="fa-solid fa-right-from-bracket w-4 text-center"></i> Log Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Dropdown Menu -->
<div id="mobile-menu" class="hidden md:hidden bg-white border-b border-gray-100 absolute w-full z-40 shadow-lg pb-4">
    <div class="flex flex-col px-4 pt-2 pb-4 space-y-1">
        <button onclick="startSectionTour(); document.getElementById('mobile-menu').classList.add('hidden');" class="flex items-center gap-2 text-left px-4 py-3 rounded-xl text-base font-semibold bg-brand-sageLight text-brand-sage">
            <i class="fa-solid fa-compass"></i> Show Me Around Tour
        </button>
        <a href="dashboard.php" class="block px-4 py-3 rounded-xl text-base font-semibold <?php echo $active_pillar === 'dashboard' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-600 hover:bg-gray-50'; ?>">My TET</a>
        <a href="community.php" class="block px-4 py-3 rounded-xl text-base font-semibold <?php echo $active_pillar === 'community' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-600 hover:bg-gray-50'; ?>">Community & Care</a>
        <a href="mindbloom.php" class="block px-4 py-3 rounded-xl text-base font-semibold <?php echo $active_pillar === 'mindbloom' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-600 hover:bg-gray-50'; ?>">MindBloom</a>
        <a href="tet_academy.php" class="block px-4 py-3 rounded-xl text-base font-semibold <?php echo $active_pillar === 'academy' ? 'bg-brand-sageLight text-brand-sage' : 'text-gray-600 hover:bg-gray-50'; ?>">Learning Center</a>
    </div>
</div>

<?php 
// Include the global section guide overlay & script
require_once __DIR__ . '/includes/section_guide.php'; 
// Include the global voice-to-text input module
require_once __DIR__ . '/includes/voice_to_text.php';
?>
