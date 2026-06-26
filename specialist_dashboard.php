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

// Handle AJAX Chat Inquiries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'get_chat_messages') {
        header('Content-Type: application/json');
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $messages = [];
        if ($booking_id > 0) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT c.*, u.name as sender_name FROM teletherapy_chat_logs c JOIN users u ON c.sender_id = u.id WHERE c.booking_id = ? ORDER BY c.created_at ASC");
                    $stmt->execute([$booking_id]);
                    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {}
            } else {
                $mock_chats = $_SESSION['mock_teletherapy_chat_logs'] ?? [];
                foreach ($mock_chats as $c) {
                    if ($c['booking_id'] == $booking_id) {
                        $sender_name = 'User';
                        if (isset($_SESSION['mock_users'])) {
                            foreach ($_SESSION['mock_users'] as $mu) {
                                if ($mu['id'] == $c['sender_id']) {
                                    $sender_name = $mu['name'];
                                    break;
                                }
                            }
                        }
                        $messages[] = array_merge($c, ['sender_name' => $sender_name]);
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }

    if ($_POST['action'] === 'send_chat_message') {
        header('Content-Type: application/json');
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($booking_id > 0 && !empty($message)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO teletherapy_chat_logs (booking_id, sender_id, message) VALUES (?, ?, ?)");
                    $stmt->execute([$booking_id, $user_id, $message]);
                    $new_id = $pdo->lastInsertId();
                    $created_at = date('Y-m-d H:i:s');
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
            } else {
                $new_id = count($_SESSION['mock_teletherapy_chat_logs']) + 1;
                $created_at = date('Y-m-d H:i:s');
                $_SESSION['mock_teletherapy_chat_logs'][] = [
                    'id' => $new_id,
                    'booking_id' => $booking_id,
                    'sender_id' => $user_id,
                    'message' => $message,
                    'created_at' => $created_at
                ];
            }
            // Fetch name
            $sender_name = $_SESSION['user_name'] ?? 'User';
            echo json_encode([
                'success' => true, 
                'message' => [
                    'id' => $new_id,
                    'booking_id' => $booking_id,
                    'sender_id' => $user_id,
                    'sender_name' => $sender_name,
                    'message' => $message,
                    'created_at' => $created_at
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Empty message or invalid booking ID']);
        }
        exit;
    }
}

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

// 1b. FETCH SPECIALIST'S COMMISSION LOG
$specialist_commission_logs = [];
$specialist_total_gross = 0.00;
$specialist_total_commission = 0.00;
$specialist_total_net = 0.00;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM platform_commission_logs WHERE specialist_id = ? ORDER BY logged_at DESC");
        $stmt->execute([$user_id]);
        $specialist_commission_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {}
} else {
    foreach (($_SESSION['mock_platform_commission_logs'] ?? []) as $cl) {
        if (($cl['specialist_id'] ?? null) == $user_id) {
            $specialist_commission_logs[] = $cl;
        }
    }
}
foreach ($specialist_commission_logs as $cl) {
    $specialist_total_gross      += (float)($cl['gross_amount'] ?? 0);
    $specialist_total_commission += (float)($cl['commission_amount'] ?? 0);
    $specialist_total_net        += (float)($cl['net_payout'] ?? 0);
}

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

    // Action 3.5: Emergency Crisis Referral
    if ($action === 'emergency_referral') {
        $client_id = intval($_POST['client_id'] ?? 0);
        $saved = false;
        
        if ($client_id > 0) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET crisis_state = 1 WHERE id = ?");
                    $stmt->execute([$client_id]);
                    $saved = true;
                } catch (PDOException $ex) {
                    $error_message = "Database error: " . $ex->getMessage();
                }
            }
            
            // Mock Save
            if (isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $email => &$u) {
                    if ($u['id'] == $client_id) {
                        $u['crisis_state'] = 1;
                        $saved = true;
                        break;
                    }
                }
                unset($u);
            }
            
            if ($saved) {
                $success_message = "Emergency crisis referral successfully submitted. Client access has been limited to safety resources.";
            } else {
                $error_message = "Unable to process referral. Member not found.";
            }
        } else {
            $error_message = "Invalid member ID specified.";
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
                        <h3 class="text-lg font-bold font-outfit text-brand-slate">Earnings & Payout</h3>
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">7-Day Clearance Hold</span>
                    </div>

                    <!-- Balance cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                        <div class="p-4 bg-brand-bg rounded-2xl border border-gray-100 flex flex-col">
                            <span class="text-[10px] text-gray-400 font-bold uppercase">Escrow Held</span>
                            <span class="text-2xl font-extrabold text-brand-slate mt-1">
                                &pound;<?php echo number_format((float)($specialist['escrow_balance'] ?? 0.00), 2); ?>
                            </span>
                            <span class="text-[10px] text-gray-400 mt-1">Gross (pre-fee)</span>
                        </div>
                        <div class="p-4 bg-brand-sageLight/40 rounded-2xl border border-brand-sage/10 flex flex-col">
                            <span class="text-[10px] text-brand-sage font-bold uppercase">Cleared Balance</span>
                            <span class="text-2xl font-extrabold text-brand-sage mt-1">
                                &pound;<?php echo number_format((float)($specialist['clearance_balance'] ?? 0.00), 2); ?>
                            </span>
                            <span class="text-[10px] text-brand-sage/70 mt-1">Net (85% of gross)</span>
                        </div>
                        <div class="p-4 bg-brand-coralLight rounded-2xl border border-brand-coral/10 flex flex-col">
                            <span class="text-[10px] text-brand-coral font-bold uppercase">Total Fees Deducted</span>
                            <span class="text-2xl font-extrabold text-brand-coral mt-1">
                                &pound;<?php echo number_format($specialist_total_commission, 2); ?>
                            </span>
                            <span class="text-[10px] text-brand-coral/70 mt-1">Platform fee: 15%</span>
                        </div>
                    </div>

                    <!-- Commission explanation callout -->
                    <div class="mb-5 p-3 rounded-2xl bg-brand-sageLight/50 border border-brand-sage/10 flex items-start gap-3">
                        <svg class="h-4 w-4 text-brand-sage shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                        <div class="text-[11px] text-brand-sage/90 leading-relaxed">
                            <strong>Commission Model:</strong> The platform retains a <strong>15% fee</strong> on each cleared booking. You receive the remaining <strong>85%</strong> as net payout, automatically credited to your cleared balance when admin releases the escrow hold.
                        </div>
                    </div>

                    <!-- Commission log -->
                    <?php if (!empty($specialist_commission_logs)): ?>
                    <h4 class="text-xs font-bold text-brand-slate mb-3 uppercase tracking-wider">Payout Commission History</h4>
                    <div class="overflow-x-auto rounded-2xl border border-gray-100">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-brand-bg">
                                <tr class="text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                                    <th class="p-3 pl-4">Booking</th>
                                    <th class="p-3">Gross</th>
                                    <th class="p-3">Fee (15%)</th>
                                    <th class="p-3">Net Payout</th>
                                    <th class="p-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($specialist_commission_logs as $cl): ?>
                                <tr class="hover:bg-brand-bg/50 transition-colors">
                                    <td class="p-3 pl-4 font-mono text-brand-slate">#<?php echo htmlspecialchars($cl['booking_id']); ?></td>
                                    <td class="p-3 font-bold text-gray-700">&pound;<?php echo number_format((float)$cl['gross_amount'], 2); ?></td>
                                    <td class="p-3">
                                        <span class="px-1.5 py-0.5 rounded bg-brand-coralLight text-brand-coral font-bold text-[10px]">
                                            -&pound;<?php echo number_format((float)$cl['commission_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-bold text-brand-sage">&pound;<?php echo number_format((float)$cl['net_payout'], 2); ?></td>
                                    <td class="p-3 font-mono text-[10px] text-gray-400"><?php echo htmlspecialchars(substr($cl['logged_at'], 0, 10)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>


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
                                <div class="p-3.5 rounded-2xl border border-gray-100 bg-[#FAF9F6] flex flex-col justify-between gap-3 text-xs">
                                    <div class="flex items-center justify-between gap-4">
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
                                    <div class="flex gap-2 pt-2 border-t border-dashed border-gray-250">
                                        <button onclick="openChatDrawer(<?php echo $bk['id']; ?>, '<?php echo addslashes($bk['client_name']); ?>')" class="flex-1 py-1.5 rounded-xl bg-white hover:bg-gray-50 border border-gray-200 text-brand-slate text-[10px] font-bold transition-all active:scale-95 flex items-center justify-center gap-1">
                                            <span>💬</span> Open Chat
                                        </button>
                                        <button onclick="joinCall(<?php echo $bk['id']; ?>, '<?php echo addslashes($bk['client_name']); ?>')" class="flex-1 py-1.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white text-[10px] font-bold transition-all active:scale-95 flex items-center justify-center gap-1">
                                            <span>📹</span> Join Call
                                        </button>
                                        <button onclick="triggerCrisisReferral(<?php echo $bk['user_id']; ?>, '<?php echo addslashes($bk['client_name']); ?>')" class="flex-1 py-1.5 rounded-xl bg-brand-coralLight hover:bg-brand-coral hover:text-white text-brand-coral text-[10px] font-bold transition-all active:scale-95 flex items-center justify-center gap-1">
                                            <span>🚨</span> Refer Crisis
                                        </button>
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

    <!-- CHAT DRAWER -->
    <div id="chat-drawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white shadow-2xl border-l border-gray-150 transform translate-x-full transition-transform duration-300 flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-brand-bg">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold text-xs" id="chat-recipient-avatar">C</div>
                <div>
                    <h3 class="text-xs font-bold text-brand-slate" id="chat-recipient-name">Client Chat</h3>
                    <p class="text-[9px] text-brand-sage font-bold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-sage animate-ping"></span> Online
                    </p>
                </div>
            </div>
            <button onclick="closeChatDrawer()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto p-4 bg-[#FAF9F6] space-y-3" id="chat-messages-container">
            <!-- Messages are injected here -->
        </div>
        
        <!-- Input Area -->
        <form id="chat-message-form" onsubmit="sendChatMessage(event)" class="p-3.5 border-t border-gray-100 flex gap-2 bg-white">
            <input type="hidden" id="chat-booking-id" value="">
            <input type="text" id="chat-message-input" placeholder="Type a message..." required autocomplete="off" class="flex-1 px-4 py-2 text-xs rounded-xl bg-brand-bg border border-gray-200 focus:outline-none focus:border-brand-sage focus:ring-1 focus:ring-brand-sage">
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold transition-all active:scale-95 flex items-center gap-1">
                Send <span>➔</span>
            </button>
        </form>
    </div>

    <!-- SIMULATED LIVE CALL CONSOLE -->
    <div id="call-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-brand-slate/85 backdrop-blur-md"></div>
        
        <!-- Main Console Window -->
        <div class="relative bg-[#0d0f12] w-full max-w-4xl rounded-3xl overflow-hidden shadow-2xl border border-gray-800 flex flex-col h-[520px] transform scale-95 transition-transform duration-300">
            <!-- Top Bar -->
            <div class="absolute top-0 inset-x-0 p-4 flex items-center justify-between z-20 bg-gradient-to-b from-black/80 to-transparent">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-brand-coral animate-pulse"></div>
                    <span class="text-white text-[10px] uppercase font-bold tracking-wider font-outfit" id="call-status-label">Simulating Secure Call...</span>
                </div>
                <div class="text-gray-300 text-xs font-bold font-outfit" id="call-participant-title">Session Room</div>
            </div>
            
            <!-- Video Stage -->
            <div class="flex-1 relative bg-[#090b0d] flex items-center justify-center overflow-hidden">
                <!-- Remote Stream (Animated Canvas) -->
                <canvas id="call-canvas" class="w-full h-full absolute inset-0"></canvas>
                
                <!-- Remote Center Avatar & Label -->
                <div class="relative flex flex-col items-center gap-3 z-10 text-center">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-brand-sage to-brand-sageLight text-white flex items-center justify-center text-3xl font-bold shadow-lg" id="call-remote-avatar">C</div>
                    <div class="text-white text-xs font-bold tracking-wide" id="call-remote-name">Client Feed</div>
                    <div class="text-[10px] text-gray-400">Secure Feed Connected</div>
                </div>
                
                <!-- Picture-in-Picture Local Video (Self) -->
                <div class="absolute bottom-4 right-4 w-40 h-28 rounded-2xl overflow-hidden border border-white/20 shadow-xl bg-gray-900 z-20 flex items-center justify-center">
                    <video id="call-local-video" autoplay playsinline muted class="w-full h-full object-cover hidden"></video>
                    <!-- Local Avatar Fallback -->
                    <div id="call-local-avatar" class="w-full h-full bg-brand-slate text-white flex flex-col items-center justify-center font-bold text-lg select-none">
                        <span id="call-local-initial">U</span>
                        <span class="text-[8px] text-gray-400 mt-1 uppercase font-normal">You</span>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Controls Bar -->
            <div class="p-6 bg-gradient-to-t from-black to-[#0d0f12] border-t border-white/5 flex items-center justify-between z-20">
                <div class="text-[10px] text-gray-450">
                    🔐 AES-256 Encrypted Telehealth
                </div>
                
                <!-- Center Actions -->
                <div class="flex items-center gap-4">
                    <!-- Toggle Audio Button -->
                    <button id="btn-toggle-audio" onclick="toggleCallAudio()" class="w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all hover:scale-105 active:scale-95">
                        <svg class="w-5 h-5" id="icon-audio-on" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                        <svg class="w-5 h-5 hidden" id="icon-audio-off" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"></path></svg>
                    </button>
                    
                    <!-- Toggle Video Button -->
                    <button id="btn-toggle-video" onclick="toggleCallVideo()" class="w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all hover:scale-105 active:scale-95">
                        <svg class="w-5 h-5" id="icon-video-on" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        <svg class="w-5 h-5 hidden" id="icon-video-off" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                    </button>
                    
                    <!-- End Call Button -->
                    <button onclick="leaveCall()" class="px-5 py-2.5 rounded-full bg-brand-coral hover:bg-brand-coralHover text-white text-xs font-bold tracking-wide flex items-center gap-2 hover:scale-105 active:scale-95 transition-all">
                        🔴 End Session
                    </button>
                </div>
                
                <div class="text-white text-xs font-mono" id="call-timer">
                    00:00
                </div>
            </div>
        </div>
    </div>

    <!-- CRISIS REFERRAL MODAL -->
    <div id="crisis-referral-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-sm" onclick="closeCrisisReferralModal()"></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/30 transform scale-95 transition-transform duration-300">
            <div class="flex items-center gap-3.5 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6 text-brand-coral" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold font-outfit text-brand-slate">Trigger Crisis Referral</h3>
                    <p class="text-[9px] text-brand-coral font-bold uppercase tracking-wider">Clinical Safety Escalation</p>
                </div>
            </div>
            
            <p class="text-xs text-gray-500 mb-5 leading-relaxed">
                You are about to flag <strong id="crisis-client-name" class="text-brand-slate">the client</strong> in active crisis. This will lock their hub dashboard, present emergency hotline resources, and alert administrators.
            </p>
            
            <form method="POST" action="specialist_dashboard.php">
                <input type="hidden" name="action" value="emergency_referral">
                <input type="hidden" id="crisis-client-id" name="client_id" value="">
                
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Internal Safety Note / Rationale</label>
                    <textarea name="rationale" placeholder="Describe symptoms or reasons for crisis triage..." required class="w-full p-3 text-xs rounded-xl bg-brand-bg border border-gray-200 focus:outline-none focus:border-brand-coral focus:ring-1 focus:ring-brand-coral h-20 resize-none"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeCrisisReferralModal()" class="flex-1 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-xs transition-colors">Cancel</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-xs shadow-md transition-colors">Confirm Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // Chat & Call State Variables
        const currentUserId = <?php echo json_encode($user_id); ?>;
        let chatInterval = null;
        let localStream = null;
        let callTimerInterval = null;
        let callTimerSeconds = 0;
        let canvasAnimationId = null;
        let isAudioMuted = false;
        let isVideoMuted = false;

        // Chat Drawer Actions
        function openChatDrawer(bookingId, recipientName) {
            document.getElementById('chat-booking-id').value = bookingId;
            document.getElementById('chat-recipient-name').innerText = recipientName;
            document.getElementById('chat-recipient-avatar').innerText = recipientName.trim().charAt(0).toUpperCase();
            
            const drawer = document.getElementById('chat-drawer');
            drawer.classList.remove('translate-x-full');
            
            // Fetch messages immediately and start polling
            fetchChatMessages();
            if (chatInterval) clearInterval(chatInterval);
            chatInterval = setInterval(fetchChatMessages, 3000);
        }

        function closeChatDrawer() {
            const drawer = document.getElementById('chat-drawer');
            drawer.classList.add('translate-x-full');
            if (chatInterval) {
                clearInterval(chatInterval);
                chatInterval = null;
            }
        }

        function fetchChatMessages() {
            const bookingId = document.getElementById('chat-booking-id').value;
            if (!bookingId) return;
            
            const formData = new FormData();
            formData.append('action', 'get_chat_messages');
            formData.append('booking_id', bookingId);
            
            fetch('specialist_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('chat-messages-container');
                    const scrollAtBottom = (container.scrollHeight - container.clientHeight - container.scrollTop) < 50;
                    
                    container.innerHTML = '';
                    if (data.messages.length === 0) {
                        container.innerHTML = '<div class="text-center text-gray-400 text-[10px] py-4">No messages yet. Send a note to start the conversation!</div>';
                    } else {
                        data.messages.forEach(msg => {
                            const isMe = msg.sender_id == currentUserId;
                            const bubbleClass = isMe 
                                ? 'bg-brand-sage text-white rounded-br-none ml-auto' 
                                : 'bg-white text-brand-slate border border-gray-100 rounded-bl-none';
                            const containerClass = isMe ? 'text-right' : 'text-left';
                            
                            const div = document.createElement('div');
                            div.className = `max-w-[80%] p-3 rounded-2xl text-xs ${bubbleClass}`;
                            
                            const senderSpan = document.createElement('span');
                            senderSpan.className = 'block text-[8px] font-extrabold uppercase tracking-wider mb-1 opacity-75';
                            senderSpan.innerText = isMe ? 'You' : msg.sender_name;
                            
                            const msgContent = document.createElement('p');
                            msgContent.className = 'leading-relaxed break-words';
                            msgContent.innerText = msg.message;
                            
                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'block text-[8px] mt-1 opacity-60 text-right font-mono';
                            try {
                                const dt = new Date(msg.created_at);
                                timeSpan.innerText = dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            } catch(e) {
                                timeSpan.innerText = msg.created_at;
                            }
                            
                            div.appendChild(senderSpan);
                            div.appendChild(msgContent);
                            div.appendChild(timeSpan);
                            
                            const wrapper = document.createElement('div');
                            wrapper.className = `mb-3 ${containerClass}`;
                            wrapper.appendChild(div);
                            container.appendChild(wrapper);
                        });
                    }
                    
                    if (scrollAtBottom || container.scrollTop === 0) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            })
            .catch(err => console.error("Error fetching chats:", err));
        }

        // Send chat message
        function sendChatMessage(event) {
            event.preventDefault();
            const bookingId = document.getElementById('chat-booking-id').value;
            const input = document.getElementById('chat-message-input');
            const message = input.value.trim();
            if (!bookingId || !message) return;
            
            input.value = '';
            
            const formData = new FormData();
            formData.append('action', 'send_chat_message');
            formData.append('booking_id', bookingId);
            formData.append('message', message);
            
            fetch('specialist_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetchChatMessages();
                } else {
                    alert("Failed to send message: " + (data.error || "Unknown error"));
                }
            })
            .catch(err => console.error("Error sending chat:", err));
        }

        // Live Call Console Actions
        function playJoinChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;
                const playTone = (freq, time, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(freq, time);
                    gain.gain.setValueAtTime(0, time);
                    gain.gain.linearRampToValueAtTime(0.12, time + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.0001, time + duration);
                    osc.start(time);
                    osc.stop(time + duration);
                };
                playTone(392.00, now, 0.5); // G4
                playTone(523.25, now + 0.12, 0.5); // C5
                playTone(659.25, now + 0.24, 0.6); // E5
                playTone(783.99, now + 0.36, 0.8); // G5
            } catch(e) { console.warn("Audio context not allowed or supported yet", e); }
        }

        function playLeaveChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;
                const playTone = (freq, time, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(freq, time);
                    gain.gain.setValueAtTime(0, time);
                    gain.gain.linearRampToValueAtTime(0.12, time + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.0001, time + duration);
                    osc.start(time);
                    osc.stop(time + duration);
                };
                playTone(587.33, now, 0.4); // D5
                playTone(440.00, now + 0.12, 0.4); // A4
                playTone(349.23, now + 0.24, 0.6); // F4
            } catch(e) { console.warn("Audio context not supported", e); }
        }

        function joinCall(bookingId, participantName) {
            playJoinChime();
            
            document.getElementById('call-participant-title').innerText = `Session with ${participantName}`;
            document.getElementById('call-remote-name').innerText = participantName;
            document.getElementById('call-remote-avatar').innerText = participantName.trim().charAt(0).toUpperCase();
            document.getElementById('call-local-initial').innerText = "<?php echo addslashes(substr($user_name, 0, 1)); ?>";
            
            const modal = document.getElementById('call-modal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.remove('scale-95');
            modal.querySelector('.relative').classList.add('scale-100');
            
            // Get local camera/mic stream
            navigator.mediaDevices.getUserMedia({ video: true, audio: true })
                .then(stream => {
                    localStream = stream;
                    const video = document.getElementById('call-local-video');
                    video.srcObject = stream;
                    video.classList.remove('hidden');
                    document.getElementById('call-local-avatar').classList.add('hidden');
                    
                    isAudioMuted = false;
                    isVideoMuted = false;
                    updateControlButtons();
                })
                .catch(err => {
                    console.warn("Could not access camera/mic: ", err);
                    document.getElementById('call-local-video').classList.add('hidden');
                    document.getElementById('call-local-avatar').classList.remove('hidden');
                });
            
            // Start call timer
            callTimerSeconds = 0;
            const timerEl = document.getElementById('call-timer');
            timerEl.innerText = "00:00";
            if (callTimerInterval) clearInterval(callTimerInterval);
            callTimerInterval = setInterval(() => {
                callTimerSeconds++;
                const mins = String(Math.floor(callTimerSeconds / 60)).padStart(2, '0');
                const secs = String(callTimerSeconds % 60).padStart(2, '0');
                timerEl.innerText = `${mins}:${secs}`;
            }, 1000);
            
            // Start remote feed animation on Canvas
            startCanvasAnimation();
        }

        function leaveCall() {
            playLeaveChime();
            
            const modal = document.getElementById('call-modal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.remove('scale-100');
            modal.querySelector('.relative').classList.add('scale-95');
            
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            if (callTimerInterval) {
                clearInterval(callTimerInterval);
                callTimerInterval = null;
            }
            
            if (canvasAnimationId) {
                cancelAnimationFrame(canvasAnimationId);
                canvasAnimationId = null;
            }
        }

        function toggleCallAudio() {
            if (!localStream) return;
            isAudioMuted = !isAudioMuted;
            localStream.getAudioTracks().forEach(track => {
                track.enabled = !isAudioMuted;
            });
            updateControlButtons();
        }

        function toggleCallVideo() {
            if (!localStream) return;
            isVideoMuted = !isVideoMuted;
            localStream.getVideoTracks().forEach(track => {
                track.enabled = !isVideoMuted;
            });
            
            const video = document.getElementById('call-local-video');
            const avatar = document.getElementById('call-local-avatar');
            if (isVideoMuted) {
                video.classList.add('hidden');
                avatar.classList.remove('hidden');
            } else {
                video.classList.remove('hidden');
                avatar.classList.add('hidden');
            }
            updateControlButtons();
        }

        function updateControlButtons() {
            const btnAudio = document.getElementById('btn-toggle-audio');
            const iconAudioOn = document.getElementById('icon-audio-on');
            const iconAudioOff = document.getElementById('icon-audio-off');
            
            if (isAudioMuted) {
                btnAudio.classList.remove('bg-white/10', 'hover:bg-white/20');
                btnAudio.classList.add('bg-brand-coral/20', 'hover:bg-brand-coral/30', 'text-brand-coral');
                iconAudioOn.classList.add('hidden');
                iconAudioOff.classList.remove('hidden');
            } else {
                btnAudio.classList.add('bg-white/10', 'hover:bg-white/20');
                btnAudio.classList.remove('bg-brand-coral/20', 'hover:bg-brand-coral/30', 'text-brand-coral');
                iconAudioOn.classList.remove('hidden');
                iconAudioOff.classList.add('hidden');
            }
            
            const btnVideo = document.getElementById('btn-toggle-video');
            const iconVideoOn = document.getElementById('icon-video-on');
            const iconVideoOff = document.getElementById('icon-video-off');
            
            if (isVideoMuted) {
                btnVideo.classList.remove('bg-white/10', 'hover:bg-white/20');
                btnVideo.classList.add('bg-brand-coral/20', 'hover:bg-brand-coral/30', 'text-brand-coral');
                iconVideoOn.classList.add('hidden');
                iconVideoOff.classList.remove('hidden');
            } else {
                btnVideo.classList.add('bg-white/10', 'hover:bg-white/20');
                btnVideo.classList.remove('bg-brand-coral/20', 'hover:bg-brand-coral/30', 'text-brand-coral');
                iconVideoOn.classList.remove('hidden');
                iconVideoOff.classList.add('hidden');
            }
        }

        function startCanvasAnimation() {
            const canvas = document.getElementById('call-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const waves = [
                { amplitude: 35, frequency: 0.012, speed: 0.04, color: 'rgba(94, 140, 113, 0.25)' }, // Sage
                { amplitude: 20, frequency: 0.025, speed: 0.06, color: 'rgba(142, 202, 230, 0.2)' }, // Sky
                { amplitude: 45, frequency: 0.006, speed: 0.02, color: 'rgba(38, 70, 83, 0.1)' }    // Slate
            ];
            let phase = 0;
            
            function animate() {
                if (canvas.width !== canvas.clientWidth || canvas.height !== canvas.clientHeight) {
                    canvas.width = canvas.clientWidth;
                    canvas.height = canvas.clientHeight;
                }
                
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Draw background gradient
                const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
                grad.addColorStop(0, '#0d0f12');
                grad.addColorStop(1, '#07080a');
                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                const centerY = canvas.height / 2;
                
                // Draw decorative ambient glow in center
                const pulseGlow = 100 + Math.sin(phase * 0.03) * 15;
                const radialGrad = ctx.createRadialGradient(canvas.width/2, centerY, 10, canvas.width/2, centerY, pulseGlow);
                radialGrad.addColorStop(0, 'rgba(94, 140, 113, 0.15)');
                radialGrad.addColorStop(1, 'rgba(0,0,0,0)');
                ctx.fillStyle = radialGrad;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                waves.forEach(w => {
                    ctx.beginPath();
                    ctx.strokeStyle = w.color;
                    ctx.lineWidth = w.amplitude === 20 ? 1.5 : 2.5;
                    
                    for (let x = 0; x < canvas.width; x++) {
                        const noise = Math.sin(phase * 1.5 + x * 0.03) * 1.5;
                        const y = centerY + Math.sin(x * w.frequency + phase * w.speed) * w.amplitude + noise;
                        if (x === 0) ctx.moveTo(x, y);
                        else ctx.lineTo(x, y);
                    }
                    ctx.stroke();
                });
                
                phase += 0.4;
                canvasAnimationId = requestAnimationFrame(animate);
            }
            
            if (canvasAnimationId) cancelAnimationFrame(canvasAnimationId);
            canvasAnimationId = requestAnimationFrame(animate);
        }

        // Crisis Referral Actions
        function triggerCrisisReferral(clientId, clientName) {
            document.getElementById('crisis-client-id').value = clientId;
            document.getElementById('crisis-client-name').innerText = clientName;
            
            const modal = document.getElementById('crisis-referral-modal');
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.remove('scale-95');
            modal.querySelector('.relative').classList.add('scale-100');
        }

        function closeCrisisReferralModal() {
            const modal = document.getElementById('crisis-referral-modal');
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('.relative').classList.remove('scale-100');
            modal.querySelector('.relative').classList.add('scale-95');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCrisisReferralModal();
                leaveCall();
                closeChatDrawer();
            }
        });
    </script>

</body>
</html>
