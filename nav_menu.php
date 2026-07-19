<?php
/**
 * Tet Wellbeing Group - Shared Responsive Navigation Menu (nav_menu.php)
 * Implements a primary tab list with a sleek "More Hubs" dropdown overflow menu.
 * Fully responsive: desktop utilizes the dropdown, mobile falls back to smooth horizontal scrolling.
 */
$current_page = basename($_SERVER['PHP_SELF']);
$is_dark = ($current_page === 'streaming_hub.php');

// Define tabs to group under the overflow "More Hubs" dropdown
$dropdown_pages = [
    'predictive_hub.php' => 'Digital Twin',
    'vr_resilience.php' => 'VR Centre',
    'workplace_safety.php' => 'Workplace Safety',
    'streaming_hub.php' => 'Streaming Hub',
    'research_hub.php' => 'Research Centre',
    'subscription.php' => 'Subscription & Billing',
    'corporate_portal.php' => 'Corporate Portal',
    'ambient_mixer.php' => 'Ambient Mixer',
    'breathing_exercises.php' => 'Breathing Tool'
];
$is_more_active = array_key_exists($current_page, $dropdown_pages);

$border_color = $is_dark ? 'border-white/5' : 'border-[#EBE8E0]';
$text_inactive_hover = $is_dark ? 'hover:text-white' : 'hover:text-brand-slate';
$dropdown_bg = $is_dark ? 'bg-[#1E293B] border-white/5 shadow-2xl' : 'bg-white border-gray-100 shadow-xl';
$dropdown_text = $is_dark ? 'text-gray-300' : 'text-gray-600';

$bg_active = $is_dark ? 'bg-white/10 text-white' : 'bg-brand-sage text-white shadow-md';
$bg_inactive = $is_dark ? 'text-gray-400 hover:bg-white/5' : 'text-gray-500 hover:bg-gray-100';

function getNavClass($page, $current, $bg_active, $bg_inactive) {
    return $current === $page ? "px-4 py-2.5 rounded-full font-bold transition-all " . $bg_active : "px-4 py-2.5 rounded-full font-medium transition-all " . $bg_inactive;
}
?>

<!-- APP NAVIGATION TABS (Modern Pill Style) -->
<div class="mb-8 relative z-30">
    <div class="flex items-center gap-2 overflow-x-auto md:overflow-visible whitespace-nowrap pb-2 scrollbar-hide text-sm font-outfit">
        <!-- Primary Tabs -->
        <a href="dashboard.php" class="<?php echo getNavClass('dashboard.php', $current_page, $bg_active, $bg_inactive); ?>">
            Dashboard
        </a>
        <a href="befriender_network.php" class="<?php echo getNavClass('befriender_network.php', $current_page, $bg_active, $bg_inactive); ?>">
            <span class="mr-1">🤝</span> Befrienders
        </a>
        <a href="caregiver_hub.php" class="<?php echo getNavClass('caregiver_hub.php', $current_page, $bg_active, $bg_inactive); ?>">
            Caregivers
        </a>
        <a href="community_hub.php" class="<?php echo getNavClass('community_hub.php', $current_page, $bg_active, $bg_inactive); ?>">
            Community
        </a>
        <a href="teletherapy_hub.php" class="<?php echo getNavClass('teletherapy_hub.php', $current_page, $bg_active, $bg_inactive); ?>">
            Teletherapy
        </a>
        <a href="ai_companion.php" class="<?php echo getNavClass('ai_companion.php', $current_page, $bg_active, $bg_inactive); ?>">
            AI Companion
        </a>

        <!-- "More Hubs" Dropdown (Always available) -->
        <div class="inline-block relative group select-none ml-2">
            <button class="flex items-center gap-1 <?php echo $is_more_active && $current_page !== 'befriender_network.php' ? "px-4 py-2.5 rounded-full font-bold " . $bg_active : "px-4 py-2.5 rounded-full font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 bg-white"; ?> transition-all focus:outline-none shadow-sm">
                <span>More Hubs</span>
                <svg class="h-4 w-4 transform group-hover:rotate-180 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <!-- Dropdown elements list -->
            <div class="absolute right-0 mt-2 w-56 rounded-2xl <?php echo $dropdown_bg; ?> border <?php echo $dropdown_text; ?> opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 py-2 shadow-2xl">
                <?php foreach ($dropdown_pages as $page => $title): ?>
                    <a href="<?php echo $page; ?>" class="block px-5 py-3 text-sm <?php echo $current_page === $page ? 'text-brand-sage font-bold ' . ($is_dark ? 'bg-white/5' : 'bg-brand-sageLight/50') : ($is_dark ? 'text-gray-300 hover:bg-white/5 hover:text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-slate'); ?> transition-colors font-outfit border-b border-gray-50 last:border-0">
                        <?php echo $title; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
