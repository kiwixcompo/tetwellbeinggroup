<?php
/**
 * Tet Wellbeing Group - Access Gating Helper & Plan Highlighting
 * Configured in Preview / Access-All Mode: All features are accessible, with clear visual badges
 * indicating which subscription plan is required for each feature in production.
 */

// Preview Mode constant: true = all features unlocked with plan badges
if (!defined('TET_PREVIEW_MODE_UNLOCKED')) {
    define('TET_PREVIEW_MODE_UNLOCKED', true);
}

function isGuest() {
    return !isset($_SESSION['user_id']);
}

function getCurrentPlanSlug() {
    if (isGuest()) return 'guest';
    
    // Check session first
    if (isset($_SESSION['user_subscription_plan'])) {
        return $_SESSION['user_subscription_plan'];
    }
    
    // Fallback lookup
    global $pdo, $db_connected;
    $user_id = $_SESSION['user_id'];
    
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT subscription_plan FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $plan = $stmt->fetchColumn();
            if ($plan) {
                $_SESSION['user_subscription_plan'] = $plan;
                return $plan;
            }
        } catch (PDOException $e) {}
    }
    
    if (isset($_SESSION['mock_users'])) {
        foreach ($_SESSION['mock_users'] as $email => $u) {
            if ($u['id'] == $user_id) {
                $plan = $u['subscription_plan'] ?? 'free';
                $_SESSION['user_subscription_plan'] = $plan;
                return $plan;
            }
        }
    }
    
    return 'free';
}

function getPlanLevel($plan_slug) {
    $levels = [
        'guest' => 0,
        'free' => 1,
        'basic' => 2,
        'premium' => 3,
        'corporate' => 4
    ];
    return $levels[$plan_slug] ?? 1;
}

function hasPlan($required_plan) {
    // In Preview Mode, allow access to all features
    if (TET_PREVIEW_MODE_UNLOCKED) {
        return true;
    }
    $current = getCurrentPlanSlug();
    return getPlanLevel($current) >= getPlanLevel($required_plan);
}

/**
 * Returns a styled HTML badge indicating the required plan for a feature
 */
function getPlanBadge($required_plan, $extra_classes = '') {
    $p = strtolower($required_plan);
    switch ($p) {
        case 'free':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-sm font-outfit ' . $extra_classes . '" title="Production Access: Free Account"><i class="fa-solid fa-seedling text-[9px]"></i> Free Plan</span>';
        case 'basic':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-sky-50 text-sky-700 border border-sky-200/80 shadow-sm font-outfit ' . $extra_classes . '" title="Production Access: Basic Plan (£9.99/mo)"><i class="fa-solid fa-star text-[9px] text-sky-500"></i> Basic (£9.99/mo)</span>';
        case 'premium':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200/80 shadow-sm font-outfit ' . $extra_classes . '" title="Production Access: Premium Plan (£29.99/mo)"><i class="fa-solid fa-crown text-[9px] text-amber-500"></i> Premium (£29.99/mo)</span>';
        case 'corporate':
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200/80 shadow-sm font-outfit ' . $extra_classes . '" title="Production Access: Corporate Plan (£199/mo)"><i class="fa-solid fa-building text-[9px] text-amber-600"></i> Corporate (£199/mo)</span>';
        case 'guest':
        default:
            return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-gray-50 text-gray-600 border border-gray-200 shadow-sm font-outfit ' . $extra_classes . '" title="Production Access: Open to All"><i class="fa-solid fa-globe text-[9px]"></i> Guest Access</span>';
    }
}

function renderPlanBadge($required_plan, $extra_classes = '') {
    echo getPlanBadge($required_plan, $extra_classes);
}

function renderUpgradeOverlay($feature_name, $required_plan) {
    // If preview mode is enabled, do not block the UI with an opaque overlay
    if (TET_PREVIEW_MODE_UNLOCKED) {
        return;
    }

    $cta_text = "Upgrade to " . ucfirst($required_plan);
    $cta_url = "subscription.php";
    
    if (isGuest()) {
        $cta_text = "Create Free Account";
        $cta_url = "register.php";
    }
    
    $icon = "fa-lock";
    if ($required_plan === 'premium') $icon = "fa-bolt";
    if ($required_plan === 'corporate') $icon = "fa-building";
    
    echo '<div class="absolute inset-0 z-50 flex items-center justify-center p-4 bg-white/60 backdrop-blur-md rounded-3xl">';
    echo '<div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl border border-gray-100 transform transition-all">';
    echo '<div class="h-16 w-16 bg-brand-sageLight text-brand-sage rounded-full flex items-center justify-center mx-auto mb-4">';
    echo '<i class="fa-solid ' . $icon . ' text-2xl"></i>';
    echo '</div>';
    echo '<h3 class="text-xl font-bold font-outfit text-brand-slate mb-2">Unlock ' . htmlspecialchars($feature_name) . '</h3>';
    echo '<p class="text-sm text-gray-500 mb-6">This feature requires ' . (isGuest() ? 'an account' : 'the ' . ucfirst($required_plan) . ' plan') . ' to access. Upgrade now to unlock your full wellbeing potential.</p>';
    if (isset($_GET['embed'])) {
        echo '<a href="' . $cta_url . '" target="_parent" class="block w-full py-3 bg-brand-sage text-white font-bold rounded-2xl shadow-md hover:bg-brand-sageHover transition-colors">' . $cta_text . '</a>';
    } else {
        echo '<a href="' . $cta_url . '" class="block w-full py-3 bg-brand-sage text-white font-bold rounded-2xl shadow-md hover:bg-brand-sageHover transition-colors">' . $cta_text . '</a>';
    }
    
    if (!isGuest()) {
        echo '<a href="subscription.php" target="' . (isset($_GET['embed']) ? '_parent' : '_self') . '" class="block w-full mt-3 py-2 text-sm text-gray-500 font-semibold hover:text-brand-slate transition-colors">Compare Plans</a>';
    }
    
    echo '</div>';
    echo '</div>';
}
