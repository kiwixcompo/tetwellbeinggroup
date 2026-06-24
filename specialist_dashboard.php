<?php
/**
 * Tet Wellbeing Group - Specialist Dashboard (specialist_dashboard.php)
 * Premium portal for clinical specialists to manage bio, schedule, and track escrow earnings.
 */
require_once 'db.php';

// Auth Guard: Specialist & Admin Only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['specialist', 'admin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));
$user_role = $_SESSION['user_role'] ?? 'specialist';

// 1. FETCH CURRENT SPECIALIST DATA
$specialist = null;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $specialist = $stmt->fetch();
    } catch (PDOException $ex) {}
}

if (!$specialist && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $specialist = $u;
            break;
        }
    }
}

if (!$specialist) {
    // Session expired or user deleted
    header('Location: logout.php');
    exit;
}

$specialist_name = $specialist['name'];

// 2. AUTO-CLEARANCE LEDGER LOGIC
$cleared_count = 0;
$cleared_amount = 0;
$now_str = date('Y-m-d H:i:s');

if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM teletherapy_bookings WHERE (therapist_id = ? OR therapist_name = ?) AND payment_status = 'escrow' AND release_date <= NOW()");
        $stmt->execute([$user_id, $specialist_name]);
        $to_clear = $stmt->fetchAll();
        
        if (!empty($to_clear)) {
            $pdo->beginTransaction();
            foreach ($to_clear as $bk) {
                $up = $pdo->prepare("UPDATE teletherapy_bookings SET payment_status = 'released' WHERE id = ?");
                $up->execute([$bk['id']]);
                
                $cleared_amount += $bk['amount_paid'];
                $cleared_count++;
            }
            
            $up_bal = $pdo->prepare("UPDATE users SET escrow_balance = GREATEST(0, escrow_balance - ?), clearance_balance = clearance_balance + ? WHERE id = ?");
            $up_bal->execute([$cleared_amount, $cleared_amount, $user_id]);
            $pdo->commit();
            
            // Reload specialist data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $specialist = $stmt->fetch();
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

// Session mock auto-clearance
if (isset($_SESSION['mock_bookings'])) {
    $cleared_mock_amount = 0;
    foreach ($_SESSION['mock_bookings'] as &$bk) {
        $matches_therapist = ($bk['therapist_name'] === $specialist_name || ($bk['therapist_id'] ?? null) == $user_id);
        if ($matches_therapist && ($bk['payment_status'] ?? '') === 'escrow' && isset($bk['release_date']) && $bk['release_date'] <= $now_str) {
            $bk['payment_status'] = 'released';
            $cleared_mock_amount += $bk['amount_paid'];
            $cleared_count++;
        }
    }
    unset($bk);
    
    if ($cleared_mock_amount > 0 && isset($_SESSION['mock_users'])) {
        foreach ($_SESSION['mock_users'] as $email => &$u) {
            if ($u['id'] == $user_id) {
                $u['escrow_balance'] = max(0, ($u['escrow_balance'] ?? 0) - $cleared_mock_amount);
                $u['clearance_balance'] = ($u['clearance_balance'] ?? 0) + $cleared_mock_amount;
                $specialist = $u;
                break;
            }
        }
        unset($u);
    }
}

// 3. POST ACTION HANDLERS
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 3.1: Update Profile Details
    if ($action === 'update_profile') {
        $license_no = filter_input(INPUT_POST, 'license_no', FILTER_DEFAULT);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_DEFAULT);
        $hourly_rate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);

        if (!empty($license_no) && !empty($bio) && $hourly_rate > 0) {
            $saved = false;
            
            // Database Save
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET license_no = ?, bio = ?, hourly_rate = ? WHERE id = ?");
                    $stmt->execute([$license_no, $bio, $hourly_rate, $user_id]);
                    $saved = true;
                } catch (PDOException $ex) {
                    $error_message = "Database error: " . $ex->getMessage();
                }
            }
            
            // Mock Save
            if (isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $email => &$u) {
                    if ($u['id'] == $user_id) {
                        $u['license_no'] = $license_no;
                        $u['bio'] = $bio;
                        $u['hourly_rate'] = $hourly_rate;
                        $specialist = $u;
                        $saved = true;
                        break;
                    }
                }
                unset($u);
            }
            
            if ($saved) {
                $success_message = "Your professional profile has been updated successfully.";
            }
        } else {
            $error_message = "Please complete all profile fields with valid values.";
        }
    }

    // Action 3.2: Save availability slots
    if ($action === 'save_availability') {
        $selected_slots = $_POST['slots'] ?? [];
        $saved = false;
        
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();
                // Clear existing for this specialist by name
                $clear = $pdo->prepare("DELETE FROM therapist_availability WHERE therapist_name = ?");
                $clear->execute([$specialist_name]);
                
                if (!empty($selected_slots)) {
                    $insert = $pdo->prepare("INSERT INTO therapist_availability (therapist_name, day_of_week, time_slot) VALUES (?, ?, ?)");
                    foreach ($selected_slots as $slot_raw) {
                        $parts = explode('|', $slot_raw);
                        if (count($parts) === 2) {
                            $insert->execute([$specialist_name, $parts[0], $parts[1]]);
                        }
                    }
                }
                $pdo->commit();
                $saved = true;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error_message = "Database error saving availability: " . $ex->getMessage();
            }
        }
        
        // Mock Save
        if (isset($_SESSION['mock_availability'])) {
            // Delete old
            $_SESSION['mock_availability'] = array_filter($_SESSION['mock_availability'], function($item) use ($specialist_name) {
                return $item['therapist_name'] !== $specialist_name;
            });
            // Insert new
            foreach ($selected_slots as $slot_raw) {
                $parts = explode('|', $slot_raw);
                if (count($parts) === 2) {
                    $_SESSION['mock_availability'][] = [
                        'therapist_name' => $specialist_name,
                        'day_of_week' => $parts[0],
                        'time_slot' => $parts[1]
                    ];
                }
            }
            $saved = true;
        }

        if ($saved) {
            $success_message = "Your weekly availability periods have been updated.";
        }
    }

    // Action 3.3: Developer Tools - Toggle Approval
    if ($action === 'toggle_approval') {
        $new_approval = $specialist['is_approved'] == 1 ? 0 : 1;
        $saved = false;
        
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_approved = ? WHERE id = ?");
                $stmt->execute([$new_approval, $user_id]);
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$u) {
                if ($u['id'] == $user_id) {
                    $u['is_approved'] = $new_approval;
                    $specialist = $u;
                    $saved = true;
                    break;
                }
            }
            unset($u);
        }

        if ($saved) {
            $success_message = "Developer Mode: Account approval state toggled to " . ($new_approval ? 'APPROVED' : 'PENDING') . ".";
        }
    }

    // Action 3.4: Developer Tools - Simulate 7-Day Time Leap
    if ($action === 'simulate_leap') {
        $saved = false;
        $eight_days_ago = date('Y-m-d H:i:s', strtotime('-8 days'));
        
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE teletherapy_bookings SET release_date = ? WHERE (therapist_id = ? OR therapist_name = ?) AND payment_status = 'escrow'");
                $stmt->execute([$eight_days_ago, $user_id, $specialist_name]);
                $saved = true;
            } catch (PDOException $ex) {}
        }
        
        if (isset($_SESSION['mock_bookings'])) {
            foreach ($_SESSION['mock_bookings'] as &$bk) {
                $matches_therapist = ($bk['therapist_name'] === $specialist_name || ($bk['therapist_id'] ?? null) == $user_id);
                if ($matches_therapist && ($bk['payment_status'] ?? '') === 'escrow') {
                    $bk['release_date'] = $eight_days_ago;
                }
            }
            unset($bk);
            $saved = true;
        }

        if ($saved) {
            // Redirect to refresh and trigger auto-clearance logic immediately
            header("Location: specialist_dashboard.php?leap_success=1");
            exit;
        }
    }
}

if (isset($_GET['leap_success'])) {
    $success_message = "Simulated 7-Day Time Leap Successful! Escrow funds cleared automatically.";
}

// 4. FETCH ACTIVE BOOKINGS FOR THIS THERAPIST
$bookings = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as client_name 
            FROM teletherapy_bookings b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.therapist_id = ? OR b.therapist_name = ?
            ORDER BY b.booking_date DESC, b.booking_time DESC
        ");
        $stmt->execute([$user_id, $specialist_name]);
        $bookings = $stmt->fetchAll();
    } catch (PDOException $ex) {}
}

if (empty($bookings) && isset($_SESSION['mock_bookings'])) {
    foreach ($_SESSION['mock_bookings'] as $bk) {
        $matches_therapist = ($bk['therapist_name'] === $specialist_name || ($bk['therapist_id'] ?? null) == $user_id);
        if ($matches_therapist) {
            // Find client name from mock users
            $client_name = 'Mark';
            if (isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $mu) {
                    if ($mu['id'] == $bk['user_id']) {
                        $client_name = $mu['name'];
                        break;
                    }
                }
            }
            $bookings[] = array_merge($bk, ['client_name' => $client_name]);
        }
    }
    // Sort descending by date
    usort($bookings, function($a, $b) {
        return strcmp($b['booking_date'] . ' ' . $b['booking_time'], $a['booking_date'] . ' ' . $a['booking_time']);
    });
}

// 5. FETCH CURRENT AVAILABILITY PERIODS FOR EDITOR
$current_avail_periods = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT day_of_week, time_slot FROM therapist_availability WHERE therapist_name = ?");
        $stmt->execute([$specialist_name]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $current_avail_periods[] = $r['day_of_week'] . '|' . $r['time_slot'];
        }
    } catch (PDOException $ex) {}
}

if (empty($current_avail_periods) && isset($_SESSION['mock_availability'])) {
    foreach ($_SESSION['mock_availability'] as $item) {
        if ($item['therapist_name'] === $specialist_name) {
            $current_avail_periods[] = $item['day_of_week'] . '|' . $item['time_slot'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Portal - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            bg: '#F7F5F0',       // Soft warm off-white
                            sage: '#5E8C71',     // Calming Sage Green
                            slate: '#264653',    // Deep Slate
                            sky: '#8ECAE6',      // Soft Sky Blue
                            coral: '#E76F51',    // Muted Coral
                            sageHover: '#4D755D',
                            coralHover: '#D95C3D',
                            cardBg: '#FFFFFF',
                            inputBg: '#FAF9F6',
                            sageLight: '#E8EFEA',
                            coralLight: '#FCEBE6',
                            skyLight: '#E8F4F8'
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif']
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(94, 140, 113, 0.08)',
                        'card': '0 10px 30px -5px rgba(38, 70, 83, 0.04)',
                        'active': '0 12px 24px -6px rgba(94, 140, 113, 0.15)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F7F5F0;
            color: #264653;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="specialist_dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <span class="hidden sm:inline-block text-xs font-bold text-brand-sage bg-brand-sageLight px-3 py-1.5 rounded-full uppercase tracking-wider">
                    Provider Portal
                </span>

                <!-- Profile Avatar -->
                <div class="relative group cursor-pointer">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage/20 bg-brand-sageLight text-sm font-bold text-brand-sage transition-all hover:border-brand-sage">
                        <?php echo $user_initial; ?>
                    </div>
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-xl border border-gray-100 opacity-0 scale-95 pointer-events-none transition-all duration-200 group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Signed in as</div>
                        <div class="px-3 py-1 font-bold text-brand-slate text-sm truncate"><?php echo htmlspecialchars($user_name); ?></div>
                        <hr class="my-2 border-gray-100">
                        <?php if ($user_role === 'admin'): ?>
                        <a href="admin_dashboard.php" class="block px-3 py-2 text-sm text-brand-sage rounded-xl hover:bg-brand-sageLight transition-colors font-bold">Admin Console</a>
                        <?php endif; ?>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-4xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Header Banner Card -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-lg">
                <div class="flex items-center gap-2">
                    <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit">Welcome Back</p>
                    <?php if ($specialist['is_approved'] == 1): ?>
                        <span class="px-2 py-0.5 rounded-md bg-brand-sageLight text-[9px] font-bold text-brand-sage uppercase">Approved Directory Listing</span>
                    <?php else: ?>
                        <span class="px-2 py-0.5 rounded-md bg-brand-coralLight text-[9px] font-bold text-brand-coral uppercase animate-pulse">Pending Admin Approval</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-3xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    <?php echo htmlspecialchars($user_name); ?>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed mt-1">
                    Configure your professional profile, update weekly times, and track your escrow ledger clearance events here.
                </p>
            </div>
            <div class="relative shrink-0 w-full sm:w-48 h-32 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10 bg-brand-sageLight flex flex-col justify-center items-center p-4">
                <span class="text-4xl">💼</span>
                <span class="text-xs font-bold text-brand-slate mt-2">Specialist Account</span>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if (!empty($success_message)): ?>
            <div id="status-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Operation Success</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $success_message; ?></p>
                </div>
                <button onclick="document.getElementById('status-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div id="error-toast" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Notice</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $error_message; ?></p>
                </div>
                <button onclick="document.getElementById('error-toast').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- SECTION GRID -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- COLUMN 1 & 2: Main Contents -->
            <div class="md:col-span-2 space-y-6">
                
                <!-- ESCROW EARNINGS LEDGER -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Escrow Earnings Ledger</h3>
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">7-Day Clearance Hold</span>
                    </div>

                    <!-- Balance cards -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="p-4 bg-brand-bg rounded-2xl border border-gray-100 flex flex-col">
                            <span class="text-[10px] text-gray-400 font-bold uppercase">Escrow Held</span>
                            <span class="text-2xl font-extrabold text-brand-slate mt-1">
                                $<?php echo number_format((float)($specialist['escrow_balance'] ?? 0.00), 2); ?>
                            </span>
                        </div>
                        <div class="p-4 bg-brand-sageLight/40 rounded-2xl border border-brand-sage/10 flex flex-col">
                            <span class="text-[10px] text-brand-sage font-bold uppercase">Cleared Balance</span>
                            <span class="text-2xl font-extrabold text-brand-sage mt-1">
                                $<?php echo number_format((float)($specialist['clearance_balance'] ?? 0.00), 2); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Bookings table -->
                    <h4 class="text-xs font-bold text-brand-slate mb-3 uppercase tracking-wider">Session Payments History</h4>
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-8 text-gray-400 text-xs bg-[#FAF9F6] rounded-2xl border border-dashed border-gray-200">
                            No booked client sessions recorded.
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($bookings as $bk): ?>
                                <?php 
                                    $is_released = ($bk['payment_status'] ?? '') === 'released';
                                    $date_diff = strtotime($bk['release_date'] ?? '') - time();
                                    $days_left = max(0, ceil($date_diff / 86400));
                                ?>
                                <div class="p-3.5 rounded-2xl border border-gray-100 bg-[#FAF9F6] flex items-center justify-between gap-4 text-xs">
                                    <div>
                                        <div class="font-bold text-brand-slate"><?php echo htmlspecialchars($bk['client_name']); ?></div>
                                        <div class="text-[10px] text-gray-500 mt-0.5">
                                            <span>📅 <?php echo date('M j, Y', strtotime($bk['booking_date'])); ?></span>
                                            <span class="mx-1">•</span>
                                            <span>⏰ <?php echo htmlspecialchars($bk['booking_time']); ?></span>
                                        </div>
                                        <?php if (!$is_released && isset($bk['release_date'])): ?>
                                            <div class="text-[9px] text-brand-coral font-bold mt-1 uppercase">
                                                🔒 Escrow Clears in <?php echo $days_left; ?> days (<?php echo date('M j', strtotime($bk['release_date'])); ?>)
                                            </div>
                                        <?php else: ?>
                                            <div class="text-[9px] text-brand-sage font-bold mt-1 uppercase">
                                                ✅ Funds Cleared
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-extrabold text-brand-slate">$<?php echo number_format($bk['amount_paid'] ?? 0, 2); ?></div>
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[8px] font-bold uppercase <?php echo $is_released ? 'bg-brand-sageLight text-brand-sage' : 'bg-brand-coralLight text-brand-coral'; ?>">
                                            <?php echo $is_released ? 'Cleared' : 'Escrow Hold'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- WEEKLY AVAILABILITY PERIODS -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Weekly Availability Periods</h3>
                        <p class="text-xs text-gray-500 mt-1">Configure the weekly time blocks where clients can book sessions with you.</p>
                    </div>

                    <form method="POST" action="specialist_dashboard.php">
                        <input type="hidden" name="action" value="save_availability">

                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3.5">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            $slots = ['09:00 AM', '11:00 AM', '02:00 PM', '04:00 PM'];
                            
                            foreach ($days as $d) {
                                echo '<div class="p-3 bg-brand-bg rounded-2xl border border-gray-100 space-y-2">';
                                echo '<h4 class="text-xs font-bold text-brand-slate border-b border-gray-200 pb-1 font-outfit">' . $d . '</h4>';
                                foreach ($slots as $s) {
                                    $key = $d . '|' . $s;
                                    $checked = in_array($key, $current_avail_periods) ? 'checked' : '';
                                    echo '<label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] text-gray-600 font-medium py-0.5 hover:text-brand-slate">';
                                    echo '<input type="checkbox" name="slots[]" value="' . htmlspecialchars($key) . '" ' . $checked . ' class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage">';
                                    echo '<span>' . $s . '</span>';
                                    echo '</label>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit" class="px-6 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-soft transition-all active:scale-95">
                                Save Availability Times
                            </button>
                        </div>
                    </form>
                </section>

            </div>

            <!-- COLUMN 3: Profile Settings & Developer tools -->
            <div class="space-y-6">
                
                <!-- PROFILE DETAILS CARD -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-[#EBE8E0]">
                    <h3 class="text-lg font-bold font-outfit text-brand-slate mb-4">Profile Information</h3>

                    <form method="POST" action="specialist_dashboard.php" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">

                        <div>
                            <label for="license_no" class="block text-xs font-semibold text-brand-slate mb-1">License Number</label>
                            <input 
                                type="text" 
                                name="license_no" 
                                id="license_no" 
                                required 
                                value="<?php echo htmlspecialchars($specialist['license_no'] ?? ''); ?>"
                                placeholder="e.g. LCSW-123456"
                                class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate"
                            >
                        </div>

                        <div>
                            <label for="hourly_rate" class="block text-xs font-semibold text-brand-slate mb-1">Session Rate ($ / Hour)</label>
                            <input 
                                type="number" 
                                name="hourly_rate" 
                                id="hourly_rate" 
                                required 
                                min="10" 
                                max="1000"
                                value="<?php echo htmlspecialchars($specialist['hourly_rate'] ?? ''); ?>"
                                placeholder="e.g. 100"
                                class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate font-bold"
                            >
                        </div>

                        <div>
                            <label for="bio" class="block text-xs font-semibold text-brand-slate mb-1">Short Biography</label>
                            <textarea 
                                name="bio" 
                                id="bio" 
                                required 
                                rows="5"
                                placeholder="Tell users about your specialties, approach, and background..."
                                class="w-full rounded-xl border border-gray-200 bg-brand-inputBg p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate leading-relaxed"
                            ><?php echo htmlspecialchars($specialist['bio'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 mt-2 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-all active:scale-95">
                            Update Profile Details
                        </button>
                    </form>
                </section>

                <!-- DEVELOPER / QA TEST KIT -->
                <section class="bg-white rounded-3xl p-6 shadow-soft border border-brand-coral/20 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-brand-coral"></div>
                    <div class="flex items-center gap-1.5 text-brand-coral mb-3">
                        <span class="text-sm">⚙️</span>
                        <h3 class="text-xs font-bold uppercase tracking-wider font-outfit">Developer Test Tools</h3>
                    </div>
                    <p class="text-[10px] text-gray-500 mb-4 leading-relaxed">
                        Use these helper controls to simulate platform conditions and evaluate database state transitions during review.
                    </p>

                    <div class="space-y-3">
                        <!-- Toggle Approval -->
                        <form method="POST" action="specialist_dashboard.php">
                            <input type="hidden" name="action" value="toggle_approval">
                            <button type="submit" class="w-full py-2.5 rounded-xl border border-brand-slate text-brand-slate hover:bg-brand-slate hover:text-white text-xs font-semibold transition-all active:scale-95 text-center">
                                Toggle Approval Status: <strong><?php echo $specialist['is_approved'] == 1 ? 'Approved' : 'Pending'; ?></strong>
                            </button>
                        </form>

                        <!-- Simulate Time Leap -->
                        <form method="POST" action="specialist_dashboard.php">
                            <input type="hidden" name="action" value="simulate_leap">
                            <button type="submit" class="w-full py-2.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white text-xs font-bold transition-all active:scale-95 text-center">
                                Leap 7 Days & Clear Escrow
                            </button>
                        </form>
                    </div>
                </section>

            </div>

        </div>

    </main>

</body>
</html>
