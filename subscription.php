<?php
/**
 * Tet Wellbeing Group - Subscription Billing & Plan Management (subscription.php)
 * Handles plan display, upgrade flow (session + DB), and invoice history.
 */
require_once 'db.php';

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_name    = $_SESSION['user_name'] ?? 'Member';
$user_email   = $_SESSION['user_email'] ?? '';
$user_role    = $_SESSION['user_role'] ?? 'client';
$user_initial = strtoupper(substr($user_name, 0, 1));

// ── Load Current Subscription Plan ───────────────────────────────────────────
$current_plan = 'free';

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT subscription_plan FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['subscription_plan'])) {
            $current_plan = $row['subscription_plan'];
        }
    } catch (PDOException $ex) {}
}

if ($current_plan === 'free' && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $current_plan = $u['subscription_plan'] ?? 'free';
            break;
        }
    }
}

// ── Load Subscription Plans ───────────────────────────────────────────────────
$plans = [];

if ($db_connected && $pdo) {
    try {
        $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY price_monthly ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {}
}

if (empty($plans) && isset($_SESSION['mock_subscription_plans'])) {
    $plans = $_SESSION['mock_subscription_plans'];
}

if (empty($plans)) {
    $plans = [
        ['slug' => 'free',         'name' => 'Free Starter',       'price_monthly' => 0.00,   'features' => 'AI Companion,Community Hub,1 Journal Entry/day,3 Streaming Tracks'],
        ['slug' => 'professional', 'name' => 'Professional',       'price_monthly' => 29.00,  'features' => 'Everything in Free,Unlimited Journal Entries,Full Streaming Library,Teletherapy Booking,Predictive Health Alerts,Digital Twin Access,Priority Support'],
        ['slug' => 'corporate',    'name' => 'Corporate Wellness', 'price_monthly' => 199.00, 'features' => 'Everything in Professional,Corporate HR Portal,Bulk Session Credits (50),Staff Roster Management,Org Wellbeing Analytics,Dedicated Account Manager,White-label Reports'],
    ];
}

// ── Load User Invoices ────────────────────────────────────────────────────────
$invoices = [];

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM subscription_invoices WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {}
}

if (empty($invoices) && isset($_SESSION['mock_subscription_invoices'])) {
    foreach ($_SESSION['mock_subscription_invoices'] as $inv) {
        if ((int)$inv['user_id'] === (int)$user_id) {
            $invoices[] = $inv;
        }
    }
    usort($invoices, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// ── Handle POST: upgrade_plan ─────────────────────────────────────────────────
$upgrade_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upgrade_plan') {
    $new_plan_slug = trim($_POST['plan_slug'] ?? '');
    $valid_slugs   = array_column($plans, 'slug');

    if (!in_array($new_plan_slug, $valid_slugs)) {
        $upgrade_error = 'Invalid plan selected.';
    } elseif ($new_plan_slug === $current_plan) {
        $upgrade_error = 'You are already on this plan.';
    } else {
        $new_plan_amount = 0.00;
        foreach ($plans as $p) {
            if ($p['slug'] === $new_plan_slug) {
                $new_plan_amount = (float)$p['price_monthly'];
                break;
            }
        }

        $invoice_id = count($_SESSION['mock_subscription_invoices'] ?? []) + 100;
        $now = date('Y-m-d H:i:s');

        // Update session mock_users
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$u) {
                if ($u['id'] == $user_id) {
                    $u['subscription_plan'] = $new_plan_slug;
                    break;
                }
            }
            unset($u);
        }

        // Append invoice to session mock
        if (!isset($_SESSION['mock_subscription_invoices'])) {
            $_SESSION['mock_subscription_invoices'] = [];
        }
        $_SESSION['mock_subscription_invoices'][] = [
            'id'         => $invoice_id,
            'user_id'    => $user_id,
            'plan_slug'  => $new_plan_slug,
            'amount'     => $new_plan_amount,
            'status'     => 'paid',
            'created_at' => $now,
        ];

        // DB updates
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET subscription_plan = ? WHERE id = ?");
                $stmt->execute([$new_plan_slug, $user_id]);
            } catch (PDOException $ex) {}
            try {
                $stmt = $pdo->prepare("INSERT INTO subscription_invoices (user_id, plan_slug, amount, status, created_at) VALUES (?, ?, ?, 'paid', NOW())");
                $stmt->execute([$user_id, $new_plan_slug, $new_plan_amount]);
            } catch (PDOException $ex) {}
        }

        header('Location: subscription.php?upgraded=1');
        exit;
    }
}

// ── Build indexed plan map ────────────────────────────────────────────────────
$plan_map = [];
foreach ($plans as $p) {
    $plan_map[$p['slug']] = $p;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription &amp; Billing — Tet Wellbeing Group</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg:         '#F7F5F0',
                            sage:       '#5E8C71',
                            slate:      '#264653',
                            sky:        '#8ECAE6',
                            coral:      '#E76F51',
                            sageHover:  '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg:     '#FFFFFF',
                            inputBg:    '#FAF9F6',
                            sageLight:  '#E8EFEA',
                            coralLight: '#FCEBE6',
                        }
                    },
                    fontFamily: {
                        sans:   ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    borderRadius: {
                        '2xl': '1rem',
                        '3xl': '1.5rem',
                    },
                    boxShadow: {
                        soft:   '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        card:   '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        active: '0 12px 24px -6px rgba(94, 140, 113, 0.15)',
                        gold:   '0 8px 30px -4px rgba(212, 175, 55, 0.25)',
                    }
                }
            }
        }
    </script>

    <style>
        body { background-color:#F7F5F0; color:#264653; font-family:'Plus Jakarta Sans',sans-serif; -webkit-font-smoothing:antialiased; }
        .fade-in { animation: fadeIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .plan-card { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), box-shadow 0.3s ease; }
        .plan-card:hover { transform: translateY(-4px); }
        .plan-card.popular  { box-shadow: 0 0 0 2px #5E8C71, 0 16px 40px -6px rgba(94,140,113,0.18); }
        .plan-card.popular:hover  { box-shadow: 0 0 0 2px #4D755D, 0 20px 48px -6px rgba(94,140,113,0.25); }
        .plan-card.corporate { box-shadow: 0 0 0 2px #D4AF37, 0 16px 40px -6px rgba(212,175,55,0.18); }
        .plan-card.corporate:hover { box-shadow: 0 0 0 2px #B8960C, 0 20px 48px -6px rgba(212,175,55,0.28); }
        .gold-gradient { background: linear-gradient(135deg,#D4AF37 0%,#F5D169 50%,#C9920C 100%); }
        .gold-text { background: linear-gradient(135deg,#B8960C,#D4AF37); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        ::-webkit-scrollbar { width:8px; }
        ::-webkit-scrollbar-track { background:#F7F5F0; }
        ::-webkit-scrollbar-thumb { background:#D9D5CB; border-radius:9999px; border:2px solid #F7F5F0; }
        ::-webkit-scrollbar-thumb:hover { background:#5E8C71; }
        .modal-backdrop { backdrop-filter: blur(6px); }
        .feature-item { display:flex; align-items:flex-start; gap:0.5rem; }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAV BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>
            <div class="flex items-center gap-4">
                <!-- Emergency Support -->
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4 w-4 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span class="hidden sm:inline">Emergency Support</span>
                </button>

                <!-- Profile Dropdown -->
                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                        <?php echo htmlspecialchars($user_initial); ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-52 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto z-50">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="px-3 py-1 text-xs text-brand-sage font-semibold font-outfit capitalize"><?php echo htmlspecialchars($current_plan); ?> Plan</div>
                        <hr class="my-2 border-gray-100">
                        <?php if ($user_role === 'admin'): ?>
                        <a href="admin_dashboard.php" class="block px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">Admin Console</a>
                        <?php endif; ?>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium font-outfit">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-6xl px-4 py-8 pb-24 md:pb-12 fade-in">

        <!-- NAV TABS -->
        <?php include 'nav_menu.php'; ?>

        <!-- TOAST: Upgrade Success -->
        <?php if (isset($_GET['upgraded']) && $_GET['upgraded'] == '1'): ?>
        <div id="upgrade-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="flex-grow">
                <h4 class="font-bold text-sm font-outfit">Plan Upgraded Successfully!</h4>
                <p class="text-xs text-gray-600 mt-0.5">Your subscription has been updated. Welcome to your new plan — enjoy all the new features.</p>
            </div>
            <button onclick="document.getElementById('upgrade-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <!-- TOAST: Error -->
        <?php if (!empty($upgrade_error)): ?>
        <div id="error-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <div class="flex-grow">
                <h4 class="font-bold text-sm">Notice</h4>
                <p class="text-xs text-gray-600 mt-0.5"><?php echo htmlspecialchars($upgrade_error); ?></p>
            </div>
            <button onclick="document.getElementById('error-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <!-- PAGE HERO -->
        <div class="mb-10 relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-slate to-[#1a3542] p-8 md:p-10 text-white shadow-active">
            <div class="absolute -top-10 -right-10 h-48 w-48 rounded-full bg-brand-sage/20 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-8 -left-8 h-40 w-40 rounded-full bg-brand-sky/15 blur-2xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <p class="text-xs font-bold tracking-widest uppercase text-brand-sky font-outfit">Billing &amp; Plans</p>
                    <h1 class="text-3xl md:text-4xl font-extrabold font-outfit tracking-tight">Subscription &amp; Billing</h1>
                    <p class="text-sm text-white/70 leading-relaxed max-w-lg">
                        Unlock the full power of the Tet Wellbeing platform. Choose a plan that fits your care journey — from free essentials to enterprise-grade corporate wellness.
                    </p>
                </div>
                <div class="shrink-0 flex flex-col items-start md:items-end gap-2">
                    <span class="text-xs text-white/50 uppercase tracking-wider font-outfit">Current Plan</span>
                    <span class="inline-flex items-center gap-2 rounded-2xl px-5 py-2 text-sm font-bold <?php echo $current_plan === 'corporate' ? 'gold-gradient text-[#3d2c00]' : 'bg-brand-sage text-white'; ?>">
                        <?php if ($current_plan === 'corporate'): ?>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php elseif ($current_plan === 'professional'): ?>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php else: ?>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>
                        <?php endif; ?>
                        <?php echo htmlspecialchars(ucfirst($current_plan)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- PLAN CARDS -->
        <section class="mb-12">
            <h2 class="text-xl font-bold font-outfit text-brand-slate mb-6">Choose Your Plan</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">

                <?php
                /* ── FREE ── */
                $free = $plan_map['free'] ?? ['slug'=>'free','name'=>'Free Starter','price_monthly'=>0.00,'features'=>'AI Companion,Community Hub,1 Journal Entry/day,3 Streaming Tracks'];
                $free_features  = array_map('trim', explode(',', $free['features']));
                $is_free_active = ($current_plan === 'free');
                ?>
                <!-- FREE Card -->
                <div class="plan-card bg-white rounded-3xl border border-[#EBE8E0] p-6 flex flex-col shadow-card">
                    <div class="mb-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-100 text-xl">🌱</div>
                            <?php if ($is_free_active): ?>
                            <span class="inline-flex items-center gap-1 rounded-xl bg-brand-sageLight text-brand-sage text-[11px] font-bold px-2.5 py-1 font-outfit uppercase tracking-wide">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active
                            </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-extrabold font-outfit text-brand-slate"><?php echo htmlspecialchars($free['name']); ?></h3>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-3xl font-extrabold font-outfit text-brand-slate">£0</span>
                            <span class="text-sm text-gray-400 font-outfit">/month</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">No credit card required</p>
                    </div>
                    <ul class="space-y-2.5 flex-grow mb-6">
                        <?php foreach ($free_features as $feat): ?>
                        <li class="feature-item text-sm text-gray-600">
                            <svg class="h-4 w-4 text-brand-sage shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><?php echo htmlspecialchars($feat); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($is_free_active): ?>
                    <div class="mt-auto w-full py-2.5 rounded-2xl bg-brand-sageLight text-brand-sage text-sm font-bold text-center font-outfit cursor-default">&#10003; Current Plan</div>
                    <?php else: ?>
                    <button disabled class="mt-auto w-full py-2.5 rounded-2xl bg-gray-100 text-gray-400 text-sm font-semibold cursor-not-allowed font-outfit">Get Started Free</button>
                    <?php endif; ?>
                </div>

                <?php
                /* ── PROFESSIONAL ── */
                $pro = $plan_map['professional'] ?? ['slug'=>'professional','name'=>'Professional','price_monthly'=>29.00,'features'=>'Everything in Free,Unlimited Journal Entries,Full Streaming Library,Teletherapy Booking,Predictive Health Alerts,Digital Twin Access,Priority Support'];
                $pro_features  = array_map('trim', explode(',', $pro['features']));
                $is_pro_active = ($current_plan === 'professional');
                $pro_price     = number_format((float)$pro['price_monthly'], 0);
                ?>
                <!-- PROFESSIONAL Card (Most Popular) -->
                <div class="plan-card popular bg-white rounded-3xl p-6 flex flex-col relative">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-sage px-4 py-1.5 text-[11px] font-bold text-white shadow-active font-outfit uppercase tracking-wider whitespace-nowrap">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Most Popular
                        </span>
                    </div>
                    <div class="mb-5 pt-3">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-sageLight text-xl">&#9889;</div>
                            <?php if ($is_pro_active): ?>
                            <span class="inline-flex items-center gap-1 rounded-xl bg-brand-sageLight text-brand-sage text-[11px] font-bold px-2.5 py-1 font-outfit uppercase tracking-wide">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active
                            </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-extrabold font-outfit text-brand-slate"><?php echo htmlspecialchars($pro['name']); ?></h3>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-3xl font-extrabold font-outfit text-brand-sage">£<?php echo $pro_price; ?></span>
                            <span class="text-sm text-gray-400 font-outfit">/month</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Billed monthly. Cancel anytime.</p>
                    </div>
                    <ul class="space-y-2.5 flex-grow mb-6">
                        <?php foreach ($pro_features as $feat): ?>
                        <li class="feature-item text-sm text-gray-600">
                            <svg class="h-4 w-4 text-brand-sage shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><?php echo htmlspecialchars($feat); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($is_pro_active): ?>
                    <div class="mt-auto w-full py-2.5 rounded-2xl bg-brand-sage text-white text-sm font-bold text-center font-outfit cursor-default">&#10003; Current Plan</div>
                    <?php else: ?>
                    <button onclick="openCheckoutModal('professional', '<?php echo htmlspecialchars(addslashes($pro['name'])); ?>', '&pound;<?php echo $pro_price; ?>/mo')"
                        class="mt-auto w-full py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white text-sm font-bold transition-all duration-200 shadow-active hover:shadow-lg active:scale-95 font-outfit">
                        Upgrade to Professional &#8594;
                    </button>
                    <?php endif; ?>
                </div>

                <?php
                /* ── CORPORATE ── */
                $corp = $plan_map['corporate'] ?? ['slug'=>'corporate','name'=>'Corporate Wellness','price_monthly'=>199.00,'features'=>'Everything in Professional,Corporate HR Portal,Bulk Session Credits (50),Staff Roster Management,Org Wellbeing Analytics,Dedicated Account Manager,White-label Reports'];
                $corp_features  = array_map('trim', explode(',', $corp['features']));
                $is_corp_active = ($current_plan === 'corporate');
                $corp_price     = number_format((float)$corp['price_monthly'], 0);
                ?>
                <!-- CORPORATE Card (Gold) -->
                <div class="plan-card corporate bg-white rounded-3xl p-6 flex flex-col relative">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-10">
                        <span class="inline-flex items-center gap-1.5 rounded-full gold-gradient px-4 py-1.5 text-[11px] font-bold text-[#3d2c00] shadow-gold font-outfit uppercase tracking-wider whitespace-nowrap">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Enterprise
                        </span>
                    </div>
                    <div class="mb-5 pt-3">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-yellow-50 text-xl">&#127970;</div>
                            <?php if ($is_corp_active): ?>
                            <span class="inline-flex items-center gap-1 rounded-xl bg-yellow-50 text-[11px] font-bold px-2.5 py-1 font-outfit uppercase tracking-wide gold-text">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Active
                            </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-extrabold font-outfit text-brand-slate"><?php echo htmlspecialchars($corp['name']); ?></h3>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-3xl font-extrabold font-outfit gold-text">£<?php echo $corp_price; ?></span>
                            <span class="text-sm text-gray-400 font-outfit">/month</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Per organisation. Unlimited staff seats.</p>
                    </div>
                    <ul class="space-y-2.5 flex-grow mb-6">
                        <?php foreach ($corp_features as $feat): ?>
                        <li class="feature-item text-sm text-gray-600">
                            <svg class="h-4 w-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="stroke:#D4AF37"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><?php echo htmlspecialchars($feat); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($is_corp_active): ?>
                    <div class="mt-auto w-full py-2.5 rounded-2xl gold-gradient text-[#3d2c00] text-sm font-bold text-center font-outfit cursor-default">&#10003; Current Plan</div>
                    <?php else: ?>
                    <button onclick="openCheckoutModal('corporate', '<?php echo htmlspecialchars(addslashes($corp['name'])); ?>', '&pound;<?php echo $corp_price; ?>/mo')"
                        class="mt-auto w-full py-2.5 rounded-2xl gold-gradient text-[#3d2c00] text-sm font-bold transition-all duration-200 shadow-gold hover:opacity-90 active:scale-95 font-outfit">
                        Go Corporate &#8594;
                    </button>
                    <?php endif; ?>
                </div>

            </div><!-- /grid -->
        </section>

        <!-- INVOICE HISTORY -->
        <section class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6 md:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold font-outfit text-brand-slate">Invoice History</h2>
                    <p class="text-xs text-gray-500 mt-0.5">All billing transactions linked to your account.</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-sageLight text-brand-sage">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
            </div>

            <?php if (empty($invoices)): ?>
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-sageLight text-3xl mb-4">&#128196;</div>
                <h3 class="font-bold text-brand-slate font-outfit mb-1">No Invoices Yet</h3>
                <p class="text-sm text-gray-400 max-w-xs">Your billing history will appear here once you upgrade your plan.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left pb-3 text-xs font-bold text-gray-400 uppercase tracking-wider font-outfit">Invoice #</th>
                            <th class="text-left pb-3 text-xs font-bold text-gray-400 uppercase tracking-wider font-outfit">Plan</th>
                            <th class="text-left pb-3 text-xs font-bold text-gray-400 uppercase tracking-wider font-outfit">Amount</th>
                            <th class="text-left pb-3 text-xs font-bold text-gray-400 uppercase tracking-wider font-outfit">Status</th>
                            <th class="text-left pb-3 text-xs font-bold text-gray-400 uppercase tracking-wider font-outfit">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($invoices as $inv):
                            $inv_status = $inv['status'] ?? 'paid';
                            if ($inv_status === 'paid') {
                                $status_class = 'bg-green-50 text-green-700 border-green-200';
                            } elseif ($inv_status === 'pending') {
                                $status_class = 'bg-amber-50 text-amber-700 border-amber-200';
                            } elseif ($inv_status === 'cancelled') {
                                $status_class = 'bg-red-50 text-red-700 border-red-200';
                            } else {
                                $status_class = 'bg-gray-50 text-gray-600 border-gray-200';
                            }
                            $inv_plan_label = ucfirst($inv['plan_slug'] ?? 'Unknown');
                            $inv_amount     = '&pound;' . number_format((float)($inv['amount'] ?? 0), 2);
                            $inv_date       = date('d M Y', strtotime($inv['created_at'] ?? 'now'));
                            $inv_id_display = 'INV-' . str_pad($inv['id'] ?? '0', 5, '0', STR_PAD_LEFT);
                        ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="py-4 pr-4">
                                <span class="font-mono text-xs font-semibold text-brand-slate bg-gray-50 rounded-lg px-2.5 py-1"><?php echo htmlspecialchars($inv_id_display); ?></span>
                            </td>
                            <td class="py-4 pr-4 font-semibold text-brand-slate"><?php echo htmlspecialchars($inv_plan_label); ?></td>
                            <td class="py-4 pr-4 font-bold font-outfit text-brand-slate"><?php echo $inv_amount; ?></td>
                            <td class="py-4 pr-4">
                                <span class="inline-flex items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide font-outfit <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($inv_status)); ?>
                                </span>
                            </td>
                            <td class="py-4 text-gray-500 text-xs"><?php echo htmlspecialchars($inv_date); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- CHECKOUT MODAL -->
    <div id="checkout-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 modal-backdrop bg-brand-slate/40" onclick="closeCheckoutModal()"></div>
        <div id="checkout-inner" class="relative bg-white w-full max-w-md rounded-3xl shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300 overflow-hidden">

            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-brand-slate to-[#1a3542] px-6 py-5 text-white">
                <button onclick="closeCheckoutModal()" class="absolute top-4 right-4 text-white/60 hover:text-white transition-colors p-1 rounded-full hover:bg-white/10">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 text-lg">&#128179;</div>
                    <div>
                        <h3 class="text-lg font-bold font-outfit">Complete Your Upgrade</h3>
                        <p class="text-xs text-white/60 mt-0.5">Secure simulated checkout</p>
                    </div>
                </div>
            </div>

            <!-- Plan Summary Banner -->
            <div class="px-6 py-3 bg-brand-sageLight border-b border-brand-sage/15 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Upgrading to</p>
                    <p id="modal-plan-name" class="font-bold text-brand-slate font-outfit text-sm">—</p>
                </div>
                <p id="modal-plan-price" class="text-xl font-extrabold font-outfit text-brand-sage">—</p>
            </div>

            <!-- Form -->
            <form method="POST" action="subscription.php" id="checkout-form" class="px-6 py-5 space-y-4">
                <input type="hidden" name="action" value="upgrade_plan">
                <input type="hidden" name="plan_slug" id="modal-plan-slug" value="">

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cardholder Name</label>
                    <input type="text" name="cardholder_name" placeholder="Jane Smith" required
                        class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Card Number</label>
                    <div class="relative">
                        <input type="text" name="card_number" id="card-number-input" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" maxlength="19" required
                            class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all pr-14">
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex gap-0.5 opacity-70">
                            <div class="h-4 w-6 rounded bg-blue-600"></div>
                            <div class="h-4 w-6 rounded bg-yellow-500 -ml-2"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Expiry Date</label>
                        <input type="text" name="card_expiry" id="card-expiry-input" placeholder="MM / YY" maxlength="7" required
                            class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">CVV</label>
                        <input type="text" name="card_cvv" placeholder="&bull;&bull;&bull;" maxlength="4" required
                            class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Coupon Code <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="coupon_code" placeholder="e.g. WELLBEING20"
                        class="w-full rounded-2xl border border-gray-200 bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:border-brand-sage focus:outline-none focus:ring-2 focus:ring-brand-sage/20 transition-all uppercase">
                </div>

                <!-- Disclaimer -->
                <div class="flex items-start gap-2.5 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3">
                    <svg class="h-4 w-4 text-amber-500 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <p class="text-[11px] text-amber-700 leading-relaxed">
                        <strong>Simulated checkout</strong> — no real charge will be made. This is a demonstration environment. Your card details are not stored or processed.
                    </p>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeCheckoutModal()" class="flex-1 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-sm transition-all shadow-active hover:shadow-lg active:scale-95 font-outfit">Confirm &amp; Upgrade</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EMERGENCY MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div><h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4><p class="text-xs text-gray-500">Call or Text 24/7 (US &amp; Canada)</p></div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div><h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4><p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p></div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div><h4 class="text-sm font-bold text-brand-slate">Crisis Text Line (US/CA)</h4><p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p></div>
                    <a href="sms:741741?body=HOME" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div><h4 class="text-sm font-bold text-brand-slate">Shout Crisis Text (UK)</h4><p class="text-xs text-gray-500">Text SHOUT to 85258 (Free, 24/7)</p></div>
                    <a href="sms:85258?body=SHOUT" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text SHOUT</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        /* ── Checkout Modal ─────────────────────────────────────────────────── */
        var checkoutModal  = document.getElementById('checkout-modal');
        var checkoutInner  = document.getElementById('checkout-inner');
        var modalPlanName  = document.getElementById('modal-plan-name');
        var modalPlanPrice = document.getElementById('modal-plan-price');
        var modalPlanSlug  = document.getElementById('modal-plan-slug');

        function openCheckoutModal(slug, name, price) {
            modalPlanName.textContent  = name;
            modalPlanPrice.innerHTML   = price;
            modalPlanSlug.value        = slug;
            checkoutModal.classList.remove('opacity-0', 'pointer-events-none');
            checkoutInner.classList.remove('scale-95');
            checkoutInner.classList.add('scale-100');
            document.body.style.overflow = 'hidden';
        }

        function closeCheckoutModal() {
            checkoutModal.classList.add('opacity-0', 'pointer-events-none');
            checkoutInner.classList.remove('scale-100');
            checkoutInner.classList.add('scale-95');
            document.body.style.overflow = '';
        }

        /* ── Card number auto-format ────────────────────────────────────────── */
        var cardNumInput = document.getElementById('card-number-input');
        if (cardNumInput) {
            cardNumInput.addEventListener('input', function () {
                var v = this.value.replace(/\D/g, '').substring(0, 16);
                var parts = v.match(/.{1,4}/g);
                this.value = parts ? parts.join(' ') : v;
            });
        }

        /* ── Expiry auto-format ─────────────────────────────────────────────── */
        var expiryInput = document.getElementById('card-expiry-input');
        if (expiryInput) {
            expiryInput.addEventListener('input', function () {
                var v = this.value.replace(/\D/g, '').substring(0, 4);
                if (v.length >= 3) {
                    this.value = v.substring(0, 2) + ' / ' + v.substring(2);
                } else {
                    this.value = v;
                }
            });
        }

        /* ── Emergency Modal ────────────────────────────────────────────────── */
        var emergencyModal = document.getElementById('emergency-modal');

        function openEmergencyModal() {
            emergencyModal.classList.remove('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-95');
            emergencyModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEmergencyModal() {
            emergencyModal.classList.add('opacity-0', 'pointer-events-none');
            emergencyModal.querySelector('.relative').classList.remove('scale-100');
            emergencyModal.querySelector('.relative').classList.add('scale-95');
        }

        /* ── Escape key closes modals ───────────────────────────────────────── */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (!checkoutModal.classList.contains('opacity-0'))  closeCheckoutModal();
                if (!emergencyModal.classList.contains('opacity-0')) closeEmergencyModal();
            }
        });

        /* ── Auto-dismiss toasts after 6 s ─────────────────────────────────── */
        ['upgrade-toast', 'error-toast'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                setTimeout(function () {
                    el.style.transition = 'opacity 0.4s';
                    el.style.opacity = '0';
                    setTimeout(function () { el.remove(); }, 400);
                }, 6000);
            }
        });
    </script>

</body>
</html>
