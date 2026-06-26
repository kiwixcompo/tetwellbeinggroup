<?php
/**
 * Tet Wellbeing Group - Shared Responsive Navigation Menu (nav_menu.php)
 * Implements a primary tab list with a sleek "More Hubs" dropdown overflow menu.
 * Fully responsive: desktop utilizes the dropdown, mobile falls back to smooth horizontal scrolling.
 */
$current_page = basename($_SERVER['PHP_SELF']);
$is_dark = ($current_page === 'streaming_hub.php');

// Define tabs to group under the overflow "More Hubs" dropdown on desktop
$dropdown_pages = [
    'predictive_hub.php' => 'Digital Twin',
    'vr_resilience.php' => 'VR Centre',
    'workplace_safety.php' => 'Workplace Safety',
    'streaming_hub.php' => 'Streaming Hub',
    'research_hub.php' => 'Research Centre',
    'subscription.php' => 'Subscription & Billing',
    'corporate_portal.php' => 'Corporate Portal'
];
$is_more_active = array_key_exists($current_page, $dropdown_pages);

$border_color = $is_dark ? 'border-white/5' : 'border-[#EBE8E0]';
$text_inactive_hover = $is_dark ? 'hover:text-white' : 'hover:text-brand-slate hover:border-gray-300';
$dropdown_bg = $is_dark ? 'bg-[#1E293B] border-white/5 shadow-2xl' : 'bg-white border-gray-100 shadow-xl';
$dropdown_text = $is_dark ? 'text-gray-300' : 'text-gray-600';
?>

<!-- APP NAVIGATION TABS -->
<div class="flex items-center gap-6 border-b <?php echo $border_color; ?> mb-8 text-sm font-semibold overflow-x-auto md:overflow-visible whitespace-nowrap pb-1">
    <!-- Primary Tabs (Always visible on mobile & desktop) -->
    <a href="dashboard.php" class="border-b-2 <?php echo $current_page === 'dashboard.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">My Dashboard</a>
    <a href="caregiver_hub.php" class="border-b-2 <?php echo $current_page === 'caregiver_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Caregiver Hub</a>
    <a href="community_hub.php" class="border-b-2 <?php echo $current_page === 'community_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Community Hub</a>
    <a href="teletherapy_hub.php" class="border-b-2 <?php echo $current_page === 'teletherapy_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Teletherapy Hub</a>
    <a href="ai_companion.php" class="border-b-2 <?php echo $current_page === 'ai_companion.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">AI Companion</a>

    <!-- Mobile-Only Navigation items (Visible only on mobile scroll containers) -->
    <a href="predictive_hub.php" class="md:hidden border-b-2 <?php echo $current_page === 'predictive_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Digital Twin</a>
    <a href="vr_resilience.php" class="md:hidden border-b-2 <?php echo $current_page === 'vr_resilience.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">VR Centre</a>
    <a href="workplace_safety.php" class="md:hidden border-b-2 <?php echo $current_page === 'workplace_safety.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Workplace Safety</a>
    <a href="streaming_hub.php" class="md:hidden border-b-2 <?php echo $current_page === 'streaming_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Streaming Hub</a>
    <a href="research_hub.php" class="md:hidden border-b-2 <?php echo $current_page === 'research_hub.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Research Centre</a>
    <a href="subscription.php" class="md:hidden border-b-2 <?php echo $current_page === 'subscription.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Billing</a>
    <a href="corporate_portal.php" class="md:hidden border-b-2 <?php echo $current_page === 'corporate_portal.php' ? 'border-brand-sage text-brand-sage' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit">Corporate</a>

    <!-- "More Hubs" Dropdown (Visible only on desktop md and up) -->
    <div class="hidden md:inline-block relative group select-none">
        <button class="flex items-center gap-1 border-b-2 <?php echo $is_more_active ? 'border-brand-sage text-brand-sage font-bold' : 'border-transparent text-gray-400 ' . $text_inactive_hover; ?> pb-3 px-1 transition-all font-outfit text-sm font-semibold focus:outline-none">
            <span>More Hubs</span>
            <svg class="h-4 w-4 transform group-hover:rotate-180 transition-transform duration-200 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <!-- Dropdown elements list -->
        <div class="absolute right-0 mt-0 w-56 rounded-2xl <?php echo $dropdown_bg; ?> border <?php echo $dropdown_text; ?> opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 py-2">
            <?php foreach ($dropdown_pages as $page => $title): ?>
                <a href="<?php echo $page; ?>" class="block px-4 py-2.5 text-sm <?php echo $current_page === $page ? 'text-brand-sage font-bold ' . ($is_dark ? 'bg-white/5' : 'bg-brand-sageLight/50') : ($is_dark ? 'text-gray-300 hover:bg-white/5 hover:text-white' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-slate'); ?> transition-colors font-outfit">
                    <?php echo $title; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
