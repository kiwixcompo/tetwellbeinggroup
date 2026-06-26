<?php
/**
 * Tet Wellbeing Group - Corporate HR Manager Wellness Dashboard (corporate_portal.php)
 * A premium HR portal for managing organisational wellness programmes.
 * Handles staff invitations, usage analytics, and credit management.
 */
require_once 'db.php';

// ─── Auth Guard ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'HR Manager';
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_role    = $_SESSION['user_role'] ?? 'client';

// ─── Subscription Plan Check ──────────────────────────────────────────────────
$subscription_plan = $_SESSION['subscription_plan'] ?? 'free';

// Try to load from DB if not in session
if ($db_connected && $pdo && $subscription_plan === 'free') {
    try {
        $stmt = $pdo->prepare("SELECT subscription_plan FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $subscription_plan = $row['subscription_plan'] ?? 'free';
            $_SESSION['subscription_plan'] = $subscription_plan;
        }
    } catch (PDOException $ex) {}
}

// Also check mock_users
if (isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $subscription_plan = $u['subscription_plan'] ?? $subscription_plan;
            break;
        }
    }
}

$is_admin    = ($user_role === 'admin');
$is_corporate = ($subscription_plan === 'corporate' || $is_admin);

// ─── Bootstrap Session Mock Data (if absent) ──────────────────────────────────
if (!isset($_SESSION['mock_corporate_organisations'])) {
    $_SESSION['mock_corporate_organisations'] = [
        [
            'id'            => 1,
            'name'          => 'Acme Wellness Ltd',
            'contact_email' => 'hr@acmewellness.co.uk',
            'hr_user_id'    => $user_id,
            'plan_credits'  => 200,
            'used_credits'  => 48,
            'created_at'    => date('Y-m-d', strtotime('-90 days')),
        ],
    ];
}

if (!isset($_SESSION['mock_corporate_staff'])) {
    $_SESSION['mock_corporate_staff'] = [
        ['id' => 1, 'org_id' => 1, 'user_id' => 101, 'email' => 'alice@acmewellness.co.uk',   'name' => 'Alice Johnson',  'status' => 'active',  'invited_at' => date('Y-m-d H:i:s', strtotime('-60 days'))],
        ['id' => 2, 'org_id' => 1, 'user_id' => 102, 'email' => 'bob@acmewellness.co.uk',     'name' => 'Bob Kariuki',    'status' => 'active',  'invited_at' => date('Y-m-d H:i:s', strtotime('-45 days'))],
        ['id' => 3, 'org_id' => 1, 'user_id' => 103, 'email' => 'carol@acmewellness.co.uk',   'name' => 'Carol Osei',     'status' => 'invited', 'invited_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ['id' => 4, 'org_id' => 1, 'user_id' => 104, 'email' => 'david@acmewellness.co.uk',   'name' => 'David Mensah',   'status' => 'removed', 'invited_at' => date('Y-m-d H:i:s', strtotime('-80 days'))],
        ['id' => 5, 'org_id' => 1, 'user_id' => 105, 'email' => 'emma@acmewellness.co.uk',    'name' => 'Emma Clarke',    'status' => 'active',  'invited_at' => date('Y-m-d H:i:s', strtotime('-20 days'))],
    ];
}

// ─── Load Organisation ────────────────────────────────────────────────────────
$org = null;
if ($is_admin) {
    $org = $_SESSION['mock_corporate_organisations'][0] ?? null;
} else {
    foreach ($_SESSION['mock_corporate_organisations'] as $o) {
        if ($o['hr_user_id'] == $user_id) {
            $org = $o;
            break;
        }
    }
}

// ─── Handle POST Actions ──────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$redirect_params = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $org !== null) {
    $org_id = $org['id'];

    // ── Invite Staff ──────────────────────────────────────────────────────────
    if ($action === 'invite_staff') {
        $invite_email = trim(filter_input(INPUT_POST, 'invite_email', FILTER_SANITIZE_EMAIL));
        $invite_name  = trim(filter_input(INPUT_POST, 'invite_name',  FILTER_DEFAULT) ?? '');

        if (filter_var($invite_email, FILTER_VALIDATE_EMAIL)) {
            $already_exists = false;
            foreach ($_SESSION['mock_corporate_staff'] as $s) {
                if ($s['email'] === $invite_email && $s['org_id'] == $org_id) {
                    $already_exists = true;
                    break;
                }
            }

            if (!$already_exists) {
                $new_id = count($_SESSION['mock_corporate_staff']) + 1;
                $new_staff = [
                    'id'         => $new_id,
                    'org_id'     => $org_id,
                    'user_id'    => null,
                    'email'      => $invite_email,
                    'name'       => $invite_name ?: $invite_email,
                    'status'     => 'invited',
                    'invited_at' => date('Y-m-d H:i:s'),
                ];
                $_SESSION['mock_corporate_staff'][] = $new_staff;

                // Try DB insert
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO corporate_staff (org_id, email, name, status, invited_at) VALUES (?, ?, ?, 'invited', NOW())");
                        $stmt->execute([$org_id, $invite_email, $invite_name]);
                    } catch (PDOException $ex) {}
                }
                $redirect_params = '?staff_invited=1&tab=staff';
            } else {
                $redirect_params = '?staff_exists=1&tab=staff';
            }
        } else {
            $redirect_params = '?invite_error=1&tab=staff';
        }
        header('Location: corporate_portal.php' . $redirect_params);
        exit;
    }

    // ── Remove Staff ──────────────────────────────────────────────────────────
    if ($action === 'remove_staff') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        foreach ($_SESSION['mock_corporate_staff'] as &$s) {
            if ($s['id'] === $staff_id && $s['org_id'] == $org_id) {
                $s['status'] = 'removed';
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("UPDATE corporate_staff SET status='removed' WHERE id=?");
                        $stmt->execute([$staff_id]);
                    } catch (PDOException $ex) {}
                }
                break;
            }
        }
        unset($s);
        header('Location: corporate_portal.php?staff_removed=1&tab=staff');
        exit;
    }

    // ── Reinstate Staff ───────────────────────────────────────────────────────
    if ($action === 'reinstate_staff') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        foreach ($_SESSION['mock_corporate_staff'] as &$s) {
            if ($s['id'] === $staff_id && $s['org_id'] == $org_id) {
                $s['status'] = 'active';
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("UPDATE corporate_staff SET status='active' WHERE id=?");
                        $stmt->execute([$staff_id]);
                    } catch (PDOException $ex) {}
                }
                break;
            }
        }
        unset($s);
        header('Location: corporate_portal.php?staff_reinstated=1&tab=staff');
        exit;
    }

    // ── Top Up Credits ────────────────────────────────────────────────────────
    if ($action === 'top_up_credits') {
        foreach ($_SESSION['mock_corporate_organisations'] as &$o) {
            if ($o['id'] == $org_id) {
                $o['plan_credits'] += 50;
                $org = $o;
                break;
            }
        }
        unset($o);
        header('Location: corporate_portal.php?credits_added=1&tab=overview');
        exit;
    }
}

// ─── Re-load organisation after potential credits top-up ─────────────────────
if ($org !== null) {
    foreach ($_SESSION['mock_corporate_organisations'] as $o) {
        if ($o['id'] == $org['id']) {
            $org = $o;
            break;
        }
    }
}

// ─── Load Staff for this Org ──────────────────────────────────────────────────
$all_staff = [];
if ($org !== null) {
    foreach ($_SESSION['mock_corporate_staff'] as $s) {
        if ($s['org_id'] == $org['id']) {
            $all_staff[] = $s;
        }
    }
}

// ─── KPI Calculations ─────────────────────────────────────────────────────────
$total_staff    = count(array_filter($all_staff, fn($s) => $s['status'] !== 'removed'));
$active_members = count(array_filter($all_staff, fn($s) => $s['status'] === 'active'));
$plan_credits   = $org['plan_credits']  ?? 200;
$used_credits   = $org['used_credits']  ?? 0;
$credits_left   = max(0, $plan_credits - $used_credits);
$credit_pct     = $plan_credits > 0 ? round(($used_credits / $plan_credits) * 100) : 0;

// ─── Recent Activity (last 5 staff sorted by invited_at desc) ─────────────────
$recent_staff = $all_staff;
usort($recent_staff, fn($a, $b) => strtotime($b['invited_at']) - strtotime($a['invited_at']));
$recent_staff = array_slice($recent_staff, 0, 5);

// ─── Active Tab ───────────────────────────────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'overview';
if (!in_array($active_tab, ['overview', 'staff', 'analytics'])) {
    $active_tab = 'overview';
}

// ─── Toast messages from query params ────────────────────────────────────────
$toast_type    = '';
$toast_message = '';
if (isset($_GET['staff_invited']))    { $toast_type = 'success'; $toast_message = 'Staff member invited successfully!'; }
if (isset($_GET['staff_removed']))    { $toast_type = 'warning'; $toast_message = 'Staff member removed from the programme.'; }
if (isset($_GET['staff_reinstated'])) { $toast_type = 'success'; $toast_message = 'Staff member reinstated successfully!'; }
if (isset($_GET['credits_added']))    { $toast_type = 'success'; $toast_message = '50 credits added to your plan!'; }
if (isset($_GET['staff_exists']))     { $toast_type = 'warning'; $toast_message = 'This email is already in your staff roster.'; }
if (isset($_GET['invite_error']))     { $toast_type = 'error';   $toast_message = 'Please enter a valid email address.'; }
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corporate Wellness Portal – Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind Config -->
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
                            coralLight: '#FCEBE6'
                        }
                    },
                    fontFamily: {
                        sans:   ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    borderRadius: { '2xl': '1rem', '3xl': '1.5rem' },
                    boxShadow: {
                        'soft':   '0 4px 20px -2px rgba(94,140,113,0.08)',
                        'card':   '0 10px 30px -5px rgba(38,70,83,0.04)',
                        'active': '0 12px 24px -6px rgba(94,140,113,0.15)'
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color:#F7F5F0; color:#264653; font-family:'Plus Jakarta Sans',sans-serif; -webkit-font-smoothing:antialiased; }
        .fade-in { animation: fadeIn 0.55s cubic-bezier(0.16,1,0.3,1) forwards; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        ::-webkit-scrollbar { width:7px; }
        ::-webkit-scrollbar-track { background:#F7F5F0; }
        ::-webkit-scrollbar-thumb { background:#D9D5CB; border-radius:9999px; border:2px solid #F7F5F0; }
        ::-webkit-scrollbar-thumb:hover { background:#5E8C71; }

        /* Tab transitions */
        .tab-panel { display:none; animation: fadeIn 0.4s cubic-bezier(0.16,1,0.3,1) forwards; }
        .tab-panel.active { display:block; }

        /* KPI Cards hover */
        .kpi-card { transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 16px 36px -8px rgba(38,70,83,0.10); }

        /* Progress bar animation */
        .progress-fill { transition: width 1s cubic-bezier(0.4,0,0.2,1); }

        /* Toast */
        #toast-banner {
            transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        #toast-banner.hidden-toast {
            opacity: 0;
            transform: translateY(-16px);
            pointer-events: none;
        }

        /* Donut SVG */
        .donut-ring { transition: stroke-dashoffset 1.2s cubic-bezier(0.4,0,0.2,1); }

        /* Emergency modal */
        #emergency-modal { transition: opacity 0.3s ease; }
        #emergency-modal.hidden { opacity:0; pointer-events:none; }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

<!-- ═══════════════════════════════════════════════════════════════════════════
     TOP NAVIGATION BAR
════════════════════════════════════════════════════════════════════════════ -->
<header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
    <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <!-- Logo -->
        <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
            <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
        </a>

        <!-- Right Actions -->
        <div class="flex items-center gap-4">
            <!-- Emergency Support -->
            <button type="button" onclick="openEmergencyModal()"
                    class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                <svg class="h-4 w-4 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span class="hidden sm:inline">Emergency Support</span>
            </button>

            <!-- Profile Avatar Dropdown -->
            <div class="relative group cursor-pointer">
                <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                    <?php echo htmlspecialchars($user_initial); ?>
                </div>
                <div class="absolute right-0 mt-2 w-52 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                    <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="px-3 py-0.5">
                        <span class="inline-block text-[10px] font-bold uppercase tracking-wider bg-brand-sageLight text-brand-sage px-2 py-0.5 rounded-full">Corporate Plan</span>
                    </div>
                    <hr class="my-2 border-gray-100">
                    <?php if ($is_admin): ?>
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

<!-- ═══════════════════════════════════════════════════════════════════════════
     MAIN BODY
════════════════════════════════════════════════════════════════════════════ -->
<main class="flex-grow mx-auto w-full max-w-6xl px-4 py-8 pb-24 md:pb-12">

<?php if (!$is_corporate): ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     UPGRADE GATE — Non-corporate users
════════════════════════════════════════════════════════════════════════════ -->
<div class="fade-in flex flex-col items-center justify-center min-h-[60vh] text-center gap-8 max-w-lg mx-auto">
    <div class="w-24 h-24 rounded-full bg-brand-sageLight flex items-center justify-center shadow-soft mx-auto">
        <svg class="w-12 h-12 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
    </div>
    <div>
        <h1 class="text-3xl font-extrabold font-outfit text-brand-slate mb-3">Corporate Portal</h1>
        <p class="text-gray-500 text-base leading-relaxed mb-2">
            The <strong class="text-brand-slate">Corporate Wellness Portal</strong> is exclusively available on the <span class="text-brand-sage font-bold">Corporate Plan</span>.
        </p>
        <p class="text-gray-400 text-sm">Unlock organisation-wide wellness tracking, staff management, and analytics for your team.</p>
    </div>
    <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6 w-full text-left">
        <h3 class="font-bold text-brand-slate font-outfit mb-4 text-lg">What's included in Corporate Plan</h3>
        <ul class="space-y-3 text-sm text-gray-600">
            <?php foreach ([
                ['🏢', 'Organisation-wide wellness dashboard'],
                ['👥', 'Unlimited staff invitations & roster management'],
                ['📊', 'Wellbeing analytics & engagement reports'],
                ['💳', 'Shared session credit pool for the whole team'],
                ['🤝', 'Dedicated corporate support line'],
            ] as [$icon, $text]): ?>
            <li class="flex items-center gap-3">
                <span class="text-xl"><?php echo $icon; ?></span>
                <span><?php echo $text; ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="flex flex-col sm:flex-row gap-3 w-full">
        <a href="dashboard.php" class="flex-1 text-center px-6 py-3 rounded-2xl border border-[#EBE8E0] text-brand-slate font-semibold hover:bg-white transition-all font-outfit">← Back to Dashboard</a>
        <a href="#" class="flex-1 text-center px-6 py-3 rounded-2xl bg-brand-sage text-white font-bold hover:bg-brand-sageHover transition-all shadow-active font-outfit">Upgrade to Corporate</a>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     DASHBOARD — Corporate users
════════════════════════════════════════════════════════════════════════════ -->

<!-- Toast Notification -->
<?php if (!empty($toast_message)): ?>
<div id="toast-banner"
     class="mb-6 flex items-center gap-3 rounded-2xl px-5 py-4 shadow-soft border text-sm font-medium fade-in
            <?php echo $toast_type === 'success' ? 'bg-brand-sageLight border-brand-sage text-brand-slate' :
                      ($toast_type === 'warning' ? 'bg-amber-50 border-amber-300 text-amber-800' :
                                                   'bg-brand-coralLight border-brand-coral text-brand-slate'); ?>">
    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full
                <?php echo $toast_type === 'success' ? 'bg-brand-sage' : ($toast_type === 'warning' ? 'bg-amber-400' : 'bg-brand-coral'); ?> text-white">
        <?php if ($toast_type === 'success'): ?>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: ?>
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php endif; ?>
    </div>
    <span><?php echo htmlspecialchars($toast_message); ?></span>
    <button onclick="dismissToast()" class="ml-auto text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
</div>
<?php endif; ?>

<!-- ─── Page Hero ──────────────────────────────────────────────────────────── -->
<div class="mb-8 relative overflow-hidden rounded-3xl border border-[#EBE8E0] bg-white shadow-card p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 fade-in">
    <!-- Decorative blob -->
    <div class="pointer-events-none absolute -top-16 -right-16 w-64 h-64 rounded-full bg-brand-sage/5 blur-3xl"></div>
    <div class="z-10">
        <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest bg-brand-sage text-white px-3 py-1 rounded-full font-outfit">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Corporate Plan
            </span>
            <span class="text-xs text-gray-400 font-outfit"><?php echo date('l, F j, Y'); ?></span>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold font-outfit text-brand-slate tracking-tight">
            Corporate Wellness Portal
        </h1>
        <p class="mt-1.5 text-gray-500 text-base font-medium">
            <?php echo htmlspecialchars($org['name'] ?? 'Your Organisation'); ?>
            <span class="mx-2 text-gray-300">·</span>
            <a href="mailto:<?php echo htmlspecialchars($org['contact_email'] ?? ''); ?>" class="text-brand-sage hover:underline text-sm">
                <?php echo htmlspecialchars($org['contact_email'] ?? ''); ?>
            </a>
        </p>
    </div>
    <div class="flex items-center gap-3 flex-wrap z-10">
        <div class="text-right bg-brand-sageLight/60 rounded-2xl px-5 py-3 border border-brand-sage/10">
            <p class="text-xs font-semibold text-brand-sage uppercase tracking-wide font-outfit">Plan Credits</p>
            <p class="text-2xl font-extrabold text-brand-slate font-outfit"><?php echo number_format($credits_left); ?><span class="text-sm font-medium text-gray-400"> / <?php echo number_format($plan_credits); ?></span></p>
        </div>
    </div>
</div>

<!-- ─── KPI Cards ─────────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php
    $kpis = [
        ['label' => 'Total Staff', 'value' => $total_staff, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>', 'colour' => 'sage'],
        ['label' => 'Active Members', 'value' => $active_members, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'colour' => 'sage'],
        ['label' => 'Credits Remaining', 'value' => number_format($credits_left), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>', 'colour' => 'sky'],
        ['label' => 'Sessions This Month', 'value' => $used_credits, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', 'colour' => 'coral'],
    ];
    foreach ($kpis as $kpi):
        $icon_colour = match($kpi['colour']) { 'sage' => 'text-brand-sage bg-brand-sageLight', 'sky' => 'text-blue-500 bg-blue-50', 'coral' => 'text-brand-coral bg-brand-coralLight', default => 'text-brand-sage bg-brand-sageLight' };
    ?>
    <div class="kpi-card bg-white rounded-2xl border border-[#EBE8E0] shadow-card p-5 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide font-outfit"><?php echo $kpi['label']; ?></p>
            <div class="w-9 h-9 rounded-xl <?php echo $icon_colour; ?> flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?php echo $kpi['icon']; ?></svg>
            </div>
        </div>
        <p class="text-3xl font-extrabold font-outfit text-brand-slate tracking-tight"><?php echo $kpi['value']; ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- ─── Nav Menu ──────────────────────────────────────────────────────────── -->
<?php include 'nav_menu.php'; ?>

<!-- ─── Tab Navigation ───────────────────────────────────────────────────── -->
<div class="mb-6 flex items-center gap-1 bg-white border border-[#EBE8E0] rounded-2xl p-1.5 shadow-soft w-full max-w-sm">
    <?php foreach (['overview' => 'Overview', 'staff' => 'Manage Staff', 'analytics' => 'Analytics'] as $tab_key => $tab_label): ?>
    <button type="button"
            onclick="switchTab('<?php echo $tab_key; ?>')"
            id="tab-btn-<?php echo $tab_key; ?>"
            class="flex-1 text-sm font-semibold py-2 px-3 rounded-xl transition-all duration-200 font-outfit <?php echo $active_tab === $tab_key ? 'bg-brand-sage text-white shadow-active' : 'text-gray-500 hover:text-brand-slate hover:bg-brand-bg'; ?>">
        <?php echo $tab_label; ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB 1 — OVERVIEW
════════════════════════════════════════════════════════════════════════════ -->
<div id="tab-overview" class="tab-panel <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Credit Usage Progress Card -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <div class="flex items-start justify-between mb-5">
                <div>
                    <h2 class="font-bold font-outfit text-brand-slate text-lg">Credit Usage</h2>
                    <p class="text-gray-400 text-sm mt-0.5"><?php echo $used_credits; ?> sessions used of <?php echo $plan_credits; ?> total</p>
                </div>
                <span class="text-3xl font-extrabold font-outfit <?php echo $credit_pct >= 80 ? 'text-brand-coral' : 'text-brand-sage'; ?>"><?php echo $credit_pct; ?>%</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full h-4 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-4 rounded-full progress-fill <?php echo $credit_pct >= 80 ? 'bg-brand-coral' : 'bg-brand-sage'; ?>"
                     id="credit-bar" style="width: 0%"
                     data-target="<?php echo $credit_pct; ?>"></div>
            </div>
            <div class="flex justify-between mt-2 text-xs font-medium text-gray-400">
                <span>0 credits</span>
                <span class="<?php echo $credit_pct >= 80 ? 'text-brand-coral' : 'text-brand-sage'; ?> font-semibold"><?php echo $credits_left; ?> remaining</span>
                <span><?php echo $plan_credits; ?> credits</span>
            </div>
            <?php if ($credit_pct >= 80): ?>
            <div class="mt-4 flex items-center gap-2 text-xs text-brand-coral bg-brand-coralLight rounded-xl px-4 py-2.5">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span class="font-semibold">You're nearing your credit limit. Consider topping up soon.</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Org Info Card -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <h2 class="font-bold font-outfit text-brand-slate text-lg mb-4">Organisation</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Organisation Name</p>
                    <p class="text-sm font-semibold text-brand-slate mt-0.5"><?php echo htmlspecialchars($org['name'] ?? '—'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">HR Contact</p>
                    <p class="text-sm font-medium text-brand-slate mt-0.5"><?php echo htmlspecialchars($org['contact_email'] ?? '—'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Member Since</p>
                    <p class="text-sm font-medium text-brand-slate mt-0.5"><?php echo isset($org['created_at']) ? date('F j, Y', strtotime($org['created_at'])) : '—'; ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Plan</p>
                    <span class="inline-block mt-0.5 bg-brand-sage text-white text-xs font-bold px-2.5 py-0.5 rounded-full font-outfit">Corporate</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <h2 class="font-bold font-outfit text-brand-slate text-lg mb-4">Quick Actions</h2>
            <div class="flex flex-col gap-3">
                <button type="button" onclick="switchTab('staff')"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl bg-brand-sageLight text-brand-sage font-semibold text-sm hover:bg-brand-sage hover:text-white transition-all font-outfit group">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Invite Staff Member
                </button>
                <form method="post" action="corporate_portal.php">
                    <input type="hidden" name="action" value="top_up_credits">
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl border border-brand-sage/20 text-brand-slate font-semibold text-sm hover:bg-brand-sageLight transition-all font-outfit">
                        <svg class="h-5 w-5 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Top Up Credits (+50)
                    </button>
                </form>
                <button type="button" onclick="switchTab('analytics')"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl border border-[#EBE8E0] text-gray-500 font-semibold text-sm hover:bg-brand-bg transition-all font-outfit">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    View Analytics
                </button>
            </div>
        </div>

        <!-- Recent Staff Activity -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <h2 class="font-bold font-outfit text-brand-slate text-lg mb-4">Recent Staff Activity</h2>
            <?php if (empty($recent_staff)): ?>
                <div class="text-center py-8 text-gray-400 text-sm">No staff added yet. <button class="text-brand-sage font-semibold hover:underline" onclick="switchTab('staff')">Invite your first team member →</button></div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recent_staff as $rs): ?>
                <div class="flex items-center gap-3 py-3 border-b border-gray-50 last:border-0">
                    <div class="w-9 h-9 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage font-bold text-sm shrink-0">
                        <?php echo strtoupper(substr($rs['name'] ?? $rs['email'], 0, 1)); ?>
                    </div>
                    <div class="flex-grow min-w-0">
                        <p class="text-sm font-semibold text-brand-slate truncate"><?php echo htmlspecialchars($rs['name'] ?? $rs['email']); ?></p>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($rs['email']); ?></p>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <?php
                        $badge = match($rs['status']) {
                            'active'  => 'bg-brand-sageLight text-brand-sage',
                            'invited' => 'bg-amber-50 text-amber-700',
                            default   => 'bg-gray-100 text-gray-400',
                        };
                        ?>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full <?php echo $badge; ?> font-outfit"><?php echo ucfirst($rs['status']); ?></span>
                        <span class="text-[10px] text-gray-300"><?php echo date('M j, Y', strtotime($rs['invited_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB 2 — MANAGE STAFF
════════════════════════════════════════════════════════════════════════════ -->
<div id="tab-staff" class="tab-panel <?php echo $active_tab === 'staff' ? 'active' : ''; ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Invite Form -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-2xl bg-brand-sageLight flex items-center justify-center">
                    <svg class="w-5 h-5 text-brand-sage" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
                <div>
                    <h2 class="font-bold font-outfit text-brand-slate text-lg leading-tight">Invite Staff Member</h2>
                    <p class="text-xs text-gray-400">Send a wellness programme invite</p>
                </div>
            </div>
            <form method="post" action="corporate_portal.php" class="space-y-4">
                <input type="hidden" name="action" value="invite_staff">
                <div>
                    <label for="invite_email" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Email Address <span class="text-brand-coral">*</span></label>
                    <input type="email" id="invite_email" name="invite_email" required
                           placeholder="colleague@company.com"
                           class="w-full rounded-xl border border-[#EBE8E0] bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-sage/30 focus:border-brand-sage transition-all">
                </div>
                <div>
                    <label for="invite_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Full Name <span class="text-gray-300 font-normal">(optional)</span></label>
                    <input type="text" id="invite_name" name="invite_name"
                           placeholder="Jane Smith"
                           class="w-full rounded-xl border border-[#EBE8E0] bg-brand-inputBg px-4 py-2.5 text-sm text-brand-slate placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-sage/30 focus:border-brand-sage transition-all">
                </div>
                <button type="submit"
                        class="w-full py-3 rounded-2xl bg-brand-sage text-white font-bold text-sm font-outfit shadow-active hover:bg-brand-sageHover active:scale-95 transition-all">
                    Send Invitation
                </button>
            </form>
            <p class="text-[11px] text-gray-300 mt-4 text-center leading-relaxed">An invitation email will be sent to join your organisation's wellness programme.</p>
        </div>

        <!-- Staff Roster Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6 overflow-hidden">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-bold font-outfit text-brand-slate text-lg">Staff Roster</h2>
                    <p class="text-gray-400 text-xs mt-0.5"><?php echo count($all_staff); ?> total members</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <span class="bg-brand-sageLight text-brand-sage px-2.5 py-1 rounded-full"><?php echo $active_members; ?> active</span>
                    <span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full"><?php echo count(array_filter($all_staff, fn($s) => $s['status'] === 'invited')); ?> invited</span>
                </div>
            </div>

            <?php if (empty($all_staff)): ?>
                <div class="text-center py-16 text-gray-300">
                    <svg class="h-12 w-12 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="text-sm">No staff members yet. Use the form to invite your first colleague.</p>
                </div>
            <?php else: ?>
            <div class="overflow-x-auto -mx-6 px-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Email / Name</th>
                            <th class="pb-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit hidden sm:table-cell">User ID</th>
                            <th class="pb-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Status</th>
                            <th class="pb-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit hidden md:table-cell">Invited</th>
                            <th class="pb-3 text-right text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_staff as $staff):
                            $badge_class = match($staff['status']) {
                                'active'  => 'bg-brand-sageLight text-brand-sage',
                                'invited' => 'bg-amber-50 text-amber-700',
                                default   => 'bg-gray-100 text-gray-400',
                            };
                        ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-brand-bg/40 transition-colors">
                            <td class="py-3.5 pr-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-brand-sageLight flex items-center justify-center text-brand-sage font-bold text-xs shrink-0">
                                        <?php echo strtoupper(substr($staff['name'] ?? $staff['email'], 0, 1)); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-brand-slate truncate max-w-[150px]"><?php echo htmlspecialchars($staff['name'] ?? '—'); ?></p>
                                        <p class="text-xs text-gray-400 truncate max-w-[150px]"><?php echo htmlspecialchars($staff['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 pr-4 text-gray-400 text-xs font-outfit hidden sm:table-cell">
                                <?php echo $staff['user_id'] ? '#' . $staff['user_id'] : '<span class="text-gray-200">—</span>'; ?>
                            </td>
                            <td class="py-3.5 pr-4">
                                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?php echo $badge_class; ?> font-outfit">
                                    <?php echo ucfirst($staff['status']); ?>
                                </span>
                            </td>
                            <td class="py-3.5 pr-4 text-xs text-gray-400 hidden md:table-cell">
                                <?php echo date('M j, Y', strtotime($staff['invited_at'])); ?>
                            </td>
                            <td class="py-3.5 text-right">
                                <?php if ($staff['status'] !== 'removed'): ?>
                                <form method="post" action="corporate_portal.php" class="inline">
                                    <input type="hidden" name="action" value="remove_staff">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <button type="submit"
                                            onclick="return confirm('Remove this staff member from the programme?')"
                                            class="text-xs font-semibold text-brand-coral hover:text-brand-coralHover hover:bg-brand-coralLight px-2.5 py-1.5 rounded-xl transition-all font-outfit">
                                        Remove
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="corporate_portal.php" class="inline">
                                    <input type="hidden" name="action" value="reinstate_staff">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <button type="submit"
                                            class="text-xs font-semibold text-brand-sage hover:text-brand-sageHover hover:bg-brand-sageLight px-2.5 py-1.5 rounded-xl transition-all font-outfit">
                                        Reinstate
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB 3 — ANALYTICS
════════════════════════════════════════════════════════════════════════════ -->
<div id="tab-analytics" class="tab-panel <?php echo $active_tab === 'analytics' ? 'active' : ''; ?>">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="font-bold font-outfit text-brand-slate text-xl">Wellbeing Analytics</h2>
            <p class="text-gray-400 text-sm mt-0.5">Aggregated wellness insights for your organisation</p>
        </div>
        <button type="button" onclick="window.print()"
                class="flex items-center gap-2 px-4 py-2 rounded-2xl border border-[#EBE8E0] bg-white text-brand-slate text-sm font-semibold hover:bg-brand-bg transition-all font-outfit">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Export / Print
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Wellbeing Score Donut -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6 flex flex-col items-center">
            <h3 class="font-bold font-outfit text-brand-slate text-base mb-5 self-start">Wellbeing Score</h3>
            <div class="relative w-44 h-44">
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <!-- Background ring -->
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#E8EFEA" stroke-width="12"/>
                    <!-- Score ring -->
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#5E8C71" stroke-width="12"
                            stroke-linecap="round"
                            stroke-dasharray="251.2"
                            stroke-dashoffset="68"
                            class="donut-ring"
                            id="donut-ring"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-extrabold font-outfit text-brand-slate" id="donut-label">72</span>
                    <span class="text-xs text-gray-400 font-semibold">out of 100</span>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-sm">
                <div class="w-3 h-3 rounded-full bg-brand-sage"></div>
                <span class="text-gray-500 text-xs">Team Average — <strong class="text-brand-slate">Good</strong></span>
            </div>
            <div class="mt-4 w-full bg-brand-sageLight/50 rounded-2xl px-4 py-3 text-center">
                <p class="text-xs text-gray-400">Score based on check-ins, session engagement and self-reported wellbeing over the past 30 days.</p>
            </div>
        </div>

        <!-- Top Wellbeing Concerns -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <h3 class="font-bold font-outfit text-brand-slate text-base mb-5">Top Wellbeing Concerns</h3>
            <div class="space-y-5">
                <?php
                $concerns = [
                    ['label' => 'Caregiver Burnout',   'pct' => 45, 'colour' => '#E76F51', 'bg' => '#FCEBE6'],
                    ['label' => 'Work-Life Balance',    'pct' => 32, 'colour' => '#5E8C71', 'bg' => '#E8EFEA'],
                    ['label' => 'Sleep Quality',        'pct' => 28, 'colour' => '#8ECAE6', 'bg' => '#EDF6FB'],
                ];
                foreach ($concerns as $c):
                ?>
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="text-sm font-semibold text-brand-slate"><?php echo $c['label']; ?></span>
                        <span class="text-sm font-bold font-outfit" style="color:<?php echo $c['colour']; ?>"><?php echo $c['pct']; ?>%</span>
                    </div>
                    <div class="w-full h-2.5 rounded-full" style="background:<?php echo $c['bg']; ?>">
                        <div class="h-2.5 rounded-full progress-fill"
                             style="background:<?php echo $c['colour']; ?>; width:0%"
                             data-target="<?php echo $c['pct']; ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 pt-4 border-t border-gray-50">
                <p class="text-[11px] text-gray-300 leading-relaxed">Based on anonymised session topics and check-in themes. Individual data is never disclosed.</p>
            </div>
        </div>

        <!-- Monthly Engagement Table -->
        <div class="bg-white rounded-3xl border border-[#EBE8E0] shadow-card p-6">
            <h3 class="font-bold font-outfit text-brand-slate text-base mb-5">Monthly Engagement</h3>
            <div class="overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Week</th>
                            <th class="pb-3 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Sessions</th>
                            <th class="pb-3 text-right text-[11px] font-bold text-gray-400 uppercase tracking-wider font-outfit">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $weeks = [
                            ['label' => 'Week 1 (Jun 1–7)',   'sessions' => 8,  'delta' => null],
                            ['label' => 'Week 2 (Jun 8–14)',  'sessions' => 14, 'delta' => '+6'],
                            ['label' => 'Week 3 (Jun 15–21)', 'sessions' => 11, 'delta' => '-3'],
                            ['label' => 'Week 4 (Jun 22–28)', 'sessions' => 15, 'delta' => '+4'],
                        ];
                        foreach ($weeks as $w):
                            $delta_class = (!$w['delta']) ? '' : (str_starts_with($w['delta'], '+') ? 'text-brand-sage' : 'text-brand-coral');
                        ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-brand-bg/40 transition-colors">
                            <td class="py-3 text-xs text-gray-500"><?php echo $w['label']; ?></td>
                            <td class="py-3 text-center font-bold text-brand-slate font-outfit"><?php echo $w['sessions']; ?></td>
                            <td class="py-3 text-right text-xs font-bold font-outfit <?php echo $delta_class; ?>">
                                <?php echo $w['delta'] ?? '<span class="text-gray-200">—</span>'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-100">
                            <td class="pt-3 text-xs font-bold text-brand-slate">Total</td>
                            <td class="pt-3 text-center font-extrabold text-brand-sage font-outfit">48</td>
                            <td class="pt-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-5 pt-4 border-t border-gray-50 flex items-center gap-2 text-xs text-gray-400">
                <svg class="h-4 w-4 text-brand-sky" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Data refreshes at the end of each week.</span>
            </div>
        </div>

    </div>
</div>
<!-- ═══ End Corporate Dashboard ═══ -->

<?php endif; // end $is_corporate ?>

</main>

<!-- ═══════════════════════════════════════════════════════════════════════════
     EMERGENCY SUPPORT MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div id="emergency-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-brand-slate/60 backdrop-blur-sm p-4" role="dialog" aria-modal="true">
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 text-center">
        <button type="button" onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition-colors">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="w-16 h-16 bg-brand-coralLight rounded-full flex items-center justify-center mx-auto mb-5">
            <svg class="h-8 w-8 text-brand-coral" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-extrabold font-outfit text-brand-slate mb-2">Emergency Support</h2>
        <p class="text-gray-500 text-sm mb-6 leading-relaxed">If you or a colleague are in crisis, please reach out immediately. You are not alone.</p>
        <div class="space-y-3 mb-6">
            <a href="tel:116123" class="flex items-center gap-3 p-4 rounded-2xl bg-brand-coralLight border border-brand-coral/20 hover:bg-brand-coral/10 transition-all group">
                <div class="w-10 h-10 bg-brand-coral rounded-xl flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-bold text-brand-slate text-sm">Samaritans (UK)</p>
                    <p class="text-brand-coral font-bold font-outfit">116 123 — Free, 24/7</p>
                </div>
            </a>
            <a href="tel:999" class="flex items-center gap-3 p-4 rounded-2xl bg-red-50 border border-red-100 hover:bg-red-100/50 transition-all">
                <div class="w-10 h-10 bg-red-500 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <div class="text-left">
                    <p class="font-bold text-brand-slate text-sm">Emergency Services</p>
                    <p class="text-red-600 font-bold font-outfit">999 — Immediate Danger</p>
                </div>
            </a>
        </div>
        <button type="button" onclick="closeEmergencyModal()"
                class="w-full py-3 rounded-2xl border border-gray-200 text-gray-500 text-sm font-semibold hover:bg-brand-bg transition-all font-outfit">
            I'm okay, close this
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════════════════ -->
<script>
// ── Tab Switching ─────────────────────────────────────────────────────────────
function switchTab(tabKey) {
    const keys = ['overview', 'staff', 'analytics'];
    keys.forEach(k => {
        const panel = document.getElementById('tab-' + k);
        const btn   = document.getElementById('tab-btn-' + k);
        if (!panel || !btn) return;
        if (k === tabKey) {
            panel.classList.add('active');
            btn.classList.remove('text-gray-500', 'hover:text-brand-slate', 'hover:bg-brand-bg');
            btn.classList.add('bg-brand-sage', 'text-white', 'shadow-active');
        } else {
            panel.classList.remove('active');
            btn.classList.add('text-gray-500', 'hover:text-brand-slate', 'hover:bg-brand-bg');
            btn.classList.remove('bg-brand-sage', 'text-white', 'shadow-active');
        }
    });
    // Trigger progress fill animations when analytics tab is opened
    if (tabKey === 'analytics') {
        setTimeout(animateProgressBars, 100);
        setTimeout(animateDonut, 100);
    }
}

// ── Animate Progress Bars ────────────────────────────────────────────────────
function animateProgressBars() {
    document.querySelectorAll('.progress-fill').forEach(el => {
        const target = el.dataset.target || 0;
        el.style.width = target + '%';
    });
}

// ── Animate Donut ────────────────────────────────────────────────────────────
function animateDonut() {
    const ring = document.getElementById('donut-ring');
    if (!ring) return;
    // circumference ≈ 251.2 for r=40. 72% filled → offset = 251.2 * (1 - 0.72) ≈ 70.3
    ring.style.strokeDashoffset = (251.2 * (1 - 0.72)).toFixed(1);
}

// ── Emergency Modal ────────────────────────────────────────────────────────
function openEmergencyModal() {
    const m = document.getElementById('emergency-modal');
    m.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeEmergencyModal() {
    const m = document.getElementById('emergency-modal');
    m.classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('emergency-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEmergencyModal();
});

// ── Toast Dismiss ─────────────────────────────────────────────────────────
function dismissToast() {
    const t = document.getElementById('toast-banner');
    if (t) {
        t.style.opacity = '0';
        t.style.transform = 'translateY(-12px)';
        setTimeout(() => t.remove(), 400);
    }
}

// ── On DOM Ready ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Animate credit progress bar on overview tab
    setTimeout(() => {
        const bar = document.getElementById('credit-bar');
        if (bar) bar.style.width = bar.dataset.target + '%';
    }, 300);

    // Auto-dismiss toast after 5 seconds
    setTimeout(dismissToast, 5000);

    // If analytics tab is active on load, animate it
    const analyticsPanel = document.getElementById('tab-analytics');
    if (analyticsPanel && analyticsPanel.classList.contains('active')) {
        setTimeout(animateProgressBars, 400);
        setTimeout(animateDonut, 400);
    }

    // Set active tab from PHP (already done via class, but sync buttons too)
    const activeFromServer = '<?php echo $active_tab; ?>';
    switchTab(activeFromServer);
});
</script>
</body>
</html>
