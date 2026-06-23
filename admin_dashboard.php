<?php
/**
 * Tet Wellbeing Group - Administrative Command Center (admin_dashboard.php)
 * Central console for platform monitoring, user management, and specialist approval.
 */
require_once 'db.php';

// Auth Guard: Enforce admin role only
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Administrator';
$user_email = $_SESSION['user_email'] ?? 'admin@tetwellbeing.com';
$user_initial = strtoupper(substr($user_name, 0, 1));

$action_success = '';
$action_error = '';

// Process Administrative Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_id = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);

    if ($action === 'approve_specialist' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'specialist'");
                $stmt->execute([$target_id]);
                $action_success = "Specialist approved successfully.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        // Always sync mock session fallback
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$user) {
                if ($user['id'] == $target_id) {
                    $user['is_approved'] = 1;
                    $action_success = "Specialist approved successfully (Session Mock).";
                }
            }
        }
    }

    elseif ($action === 'suspend_user' && $target_id) {
        if ($target_id == $_SESSION['user_id']) {
            $action_error = "You cannot suspend your own administrator account.";
        } else {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_suspended = 1 WHERE id = ?");
                    $stmt->execute([$target_id]);
                    $action_success = "User account suspended successfully.";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $email => &$user) {
                    if ($user['id'] == $target_id) {
                        $user['is_suspended'] = 1;
                        $action_success = "User account suspended successfully (Session Mock).";
                    }
                }
            }
        }
    }

    elseif ($action === 'unsuspend_user' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_suspended = 0 WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "User account activated successfully.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$user) {
                if ($user['id'] == $target_id) {
                    $user['is_suspended'] = 0;
                    $action_success = "User account activated successfully (Session Mock).";
                }
            }
        }
    }

    elseif ($action === 'edit_user' && $target_id) {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
        $archetype = filter_input(INPUT_POST, 'archetype', FILTER_SANITIZE_SPECIAL_CHARS);
        if (empty($archetype)) { $archetype = null; }
        
        $license_no = filter_input(INPUT_POST, 'license_no', FILTER_SANITIZE_SPECIAL_CHARS);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_SPECIAL_CHARS);
        $hourly_rate = filter_input(INPUT_POST, 'hourly_rate', FILTER_VALIDATE_FLOAT);
        if ($hourly_rate === false) { $hourly_rate = 0.00; }

        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, archetype = ?, license_no = ?, bio = ?, hourly_rate = ? WHERE id = ?");
                $stmt->execute([$name, $role, $archetype, $license_no, $bio, $hourly_rate, $target_id]);
                $action_success = "User details updated successfully.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$user) {
                if ($user['id'] == $target_id) {
                    $user['name'] = $name;
                    $user['role'] = $role;
                    $user['archetype'] = $archetype;
                    $user['license_no'] = $license_no;
                    $user['bio'] = $bio;
                    $user['hourly_rate'] = $hourly_rate;
                    $action_success = "User details updated successfully (Session Mock).";
                }
            }
        }
    }

    elseif ($action === 'delete_user' && $target_id) {
        if ($target_id == $_SESSION['user_id']) {
            $action_error = "You cannot delete your own administrator account.";
        } else {
            if ($db_connected && $pdo) {
                try {
                    $pdo->beginTransaction();
                    
                    // Retrieve user name & role in case they are a specialist to delete availability
                    $stmt_u = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
                    $stmt_u->execute([$target_id]);
                    $user_to_del = $stmt_u->fetch(PDO::FETCH_ASSOC);

                    // Cascade deletions in dependent tables to satisfy integrity constraints
                    $stmt = $pdo->prepare("DELETE FROM daily_checkins WHERE user_id = ?");
                    $stmt->execute([$target_id]);

                    $stmt = $pdo->prepare("DELETE FROM caregiver_burnout_logs WHERE user_id = ?");
                    $stmt->execute([$target_id]);

                    $stmt = $pdo->prepare("DELETE FROM caregiver_respite_breaks WHERE user_id = ?");
                    $stmt->execute([$target_id]);

                    // Fixed Column Name client_id -> user_id
                    $stmt = $pdo->prepare("DELETE FROM teletherapy_bookings WHERE user_id = ? OR therapist_id = ?");
                    $stmt->execute([$target_id, $target_id]);

                    if ($user_to_del && $user_to_del['role'] === 'specialist') {
                        $stmt_av = $pdo->prepare("DELETE FROM therapist_availability WHERE therapist_name = ?");
                        $stmt_av->execute([$user_to_del['name']]);
                    }

                    $stmt = $pdo->prepare("DELETE FROM community_posts WHERE user_id = ?");
                    $stmt->execute([$target_id]);

                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$target_id]);

                    $pdo->commit();
                    $action_success = "User and all associated data deleted successfully.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (isset($_SESSION['mock_users'])) {
                $del_key = null;
                foreach ($_SESSION['mock_users'] as $email => $user) {
                    if ($user['id'] == $target_id) {
                        $del_key = $email;
                        break;
                    }
                }
                if ($del_key) {
                    unset($_SESSION['mock_users'][$del_key]);
                    $action_success = "User deleted successfully (Session Mock).";
                }
            }
        }
    }

    elseif ($action === 'clear_crisis_admin' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET crisis_state = 0 WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "Crisis safety blocker resolved and client access restored.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$user) {
                if ($user['id'] == $target_id) {
                    $user['crisis_state'] = 0;
                    $action_success = "Crisis safety blocker resolved (Session Mock).";
                }
            }
        }
    }

    elseif ($action === 'release_escrow' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT * FROM teletherapy_bookings WHERE id = ? AND payment_status = 'escrow'");
                $stmt->execute([$target_id]);
                $bk = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($bk) {
                    $amount = $bk['amount_paid'];
                    $t_id = $bk['therapist_id'];
                    
                    $stmt_up = $pdo->prepare("UPDATE teletherapy_bookings SET payment_status = 'released' WHERE id = ?");
                    $stmt_up->execute([$target_id]);
                    
                    if ($t_id) {
                        $stmt_bal = $pdo->prepare("UPDATE users SET escrow_balance = GREATEST(0, escrow_balance - ?), clearance_balance = clearance_balance + ? WHERE id = ?");
                        $stmt_bal->execute([$amount, $amount, $t_id]);
                    }
                    $pdo->commit();
                    $action_success = "Escrow hold released and funds credited to specialist cleared balance.";
                } else {
                    $pdo->rollBack();
                    $action_error = "Escrow booking not found or already released.";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_bookings'])) {
            foreach ($_SESSION['mock_bookings'] as $idx => &$bk) {
                if (($bk['payment_status'] ?? '') === 'escrow' && ($idx + 1) == $target_id) {
                    $bk['payment_status'] = 'released';
                    $amount = $bk['amount_paid'];
                    $t_id = $bk['therapist_id'];
                    if ($t_id && isset($_SESSION['mock_users'])) {
                        foreach ($_SESSION['mock_users'] as $email => &$u) {
                            if ($u['id'] == $t_id) {
                                $u['escrow_balance'] = max(0, ($u['escrow_balance'] ?? 0.00) - $amount);
                                $u['clearance_balance'] = ($u['clearance_balance'] ?? 0.00) + $amount;
                            }
                        }
                    }
                    $action_success = "Escrow hold released (Session Mock).";
                    break;
                }
            }
        }
    }

    elseif ($action === 'delete_post' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "Community forum post moderated and deleted successfully.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_community_posts'])) {
            foreach ($_SESSION['mock_community_posts'] as $idx => $p) {
                if ($p['id'] == $target_id) {
                    unset($_SESSION['mock_community_posts'][$idx]);
                    $_SESSION['mock_community_posts'] = array_values($_SESSION['mock_community_posts']);
                    $action_success = "Community forum post deleted (Session Mock).";
                    break;
                }
            }
        }
    }

    elseif ($action === 'seed_test_crisis') {
        if ($db_connected && $pdo) {
            try {
                $cid = $pdo->query("SELECT id FROM users WHERE role = 'client' LIMIT 1")->fetchColumn();
                if ($cid) {
                    $stmt = $pdo->prepare("UPDATE users SET crisis_state = 1 WHERE id = ?");
                    $stmt->execute([$cid]);
                    
                    $stmt = $pdo->prepare("INSERT INTO daily_checkins (user_id, mood, notes) VALUES (?, 'terrible', 'I feel overwhelmed and want to end my life')");
                    $stmt->execute([$cid]);
                    
                    $action_success = "Test client crisis flag successfully simulated in database.";
                } else {
                    $action_error = "No client users found to seed simulated crisis.";
                }
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $email => &$u) {
                if ($u['role'] === 'client') {
                    $u['crisis_state'] = 1;
                    $_SESSION['mock_checkins'][] = [
                        'user_id' => $u['id'],
                        'mood' => 'terrible',
                        'notes' => 'I feel overwhelmed and want to end my life',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $action_success = "Test client crisis flag simulated (Session Mock).";
                    break;
                }
            }
        }
    }

    elseif ($action === 'seed_test_escrow') {
        if ($db_connected && $pdo) {
            try {
                $cid = $pdo->query("SELECT id FROM users WHERE role = 'client' LIMIT 1")->fetchColumn();
                $tid = $pdo->query("SELECT id, name FROM users WHERE role = 'specialist' AND is_approved = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                
                if ($cid && $tid) {
                    $release_date = date('Y-m-d H:i:s', strtotime('+7 days'));
                    $stmt = $pdo->prepare("INSERT INTO teletherapy_bookings (user_id, therapist_name, therapist_id, booking_date, booking_time, amount_paid, payment_status, release_date) VALUES (?, ?, ?, ?, '10:00 AM', 120.00, 'escrow', ?)");
                    $stmt->execute([$cid, $tid['name'], $tid['id'], date('Y-m-d', strtotime('+2 days')), $release_date]);
                    
                    $stmt_up = $pdo->prepare("UPDATE users SET escrow_balance = escrow_balance + 120.00 WHERE id = ?");
                    $stmt_up->execute([$tid['id']]);
                    
                    $action_success = "Test escrow hold booking simulated successfully.";
                } else {
                    $action_error = "No client or approved specialist found to seed escrow.";
                }
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_users'])) {
            $cid = 1;
            $tid = 2;
            $tname = 'Dr. Evelyn Carter, PhD';
            $release_date = date('Y-m-d H:i:s', strtotime('+7 days'));
            $_SESSION['mock_bookings'][] = [
                'user_id' => $cid,
                'therapist_name' => $tname,
                'therapist_id' => $tid,
                'booking_date' => date('Y-m-d', strtotime('+2 days')),
                'booking_time' => '10:00 AM',
                'amount_paid' => 120.00,
                'payment_status' => 'escrow',
                'release_date' => $release_date,
                'created_at' => date('Y-m-d H:i:s')
            ];
            foreach ($_SESSION['mock_users'] as $email => &$u) {
                if ($u['id'] == $tid) {
                    $u['escrow_balance'] = ($u['escrow_balance'] ?? 0.00) + 120.00;
                }
            }
            $action_success = "Test escrow hold booking simulated (Session Mock).";
        }
    }
}

// Load users database
$users = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY role DESC, name ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} else {
    $users = array_values($_SESSION['mock_users'] ?? []);
    usort($users, function($a, $b) {
        return strcmp($b['role'], $a['role']) ?: strcmp($a['name'], $b['name']);
    });
}

// Calculate Metrics
$total_clients = 0;
$total_specialists = 0;
$pending_specialists = 0;
$suspended_accounts = 0;
$escrow_balance_total = 0.00;

foreach ($users as $u) {
    if (($u['role'] ?? '') === 'client') {
        $total_clients++;
    } elseif (($u['role'] ?? '') === 'specialist') {
        $total_specialists++;
        if (($u['is_approved'] ?? 0) == 0) {
            $pending_specialists++;
        }
        $escrow_balance_total += ($u['escrow_balance'] ?? 0.00) + ($u['clearance_balance'] ?? 0.00);
    }
    if (($u['is_suspended'] ?? 0) == 1) {
        $suspended_accounts++;
    }
}

// Fetch Escrow bookings
$escrow_bookings = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT b.*, u.name as client_name, t.name as therapist_name_db FROM teletherapy_bookings b 
                             LEFT JOIN users u ON b.user_id = u.id 
                             LEFT JOIN users t ON b.therapist_id = t.id 
                             WHERE b.payment_status = 'escrow' ORDER BY b.release_date ASC");
        $escrow_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} else {
    if (isset($_SESSION['mock_bookings'])) {
        foreach ($_SESSION['mock_bookings'] as $idx => $bk) {
            if (($bk['payment_status'] ?? '') === 'escrow') {
                $c_name = 'Unknown Client';
                $t_name = $bk['therapist_name'];
                if (isset($_SESSION['mock_users'])) {
                    foreach ($_SESSION['mock_users'] as $mu) {
                        if ($mu['id'] == $bk['user_id']) {
                            $c_name = $mu['name'];
                        }
                    }
                }
                $escrow_bookings[] = array_merge($bk, ['client_name' => $c_name, 'therapist_name_db' => $t_name, 'id' => $idx + 1]);
            }
        }
    }
}

// Fetch Crisis Alerts
$crisis_alerts = [];
if ($db_connected && $pdo) {
    try {
        // Query users with active crisis flags and their latest daily checkins
        $stmt = $pdo->query("SELECT u.id as user_id, u.name as user_name, u.email as user_email, 
                                    (SELECT notes FROM daily_checkins WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as notes,
                                    (SELECT created_at FROM daily_checkins WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as created_at
                             FROM users u 
                             WHERE u.crisis_state = 1");
        $crisis_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} else {
    if (isset($_SESSION['mock_users'])) {
        foreach ($_SESSION['mock_users'] as $mu) {
            if (($mu['crisis_state'] ?? 0) == 1) {
                $note = "Severe distress reported via daily check-in.";
                $date = date('Y-m-d H:i:s');
                if (isset($_SESSION['mock_checkins'])) {
                    foreach ($_SESSION['mock_checkins'] as $mc) {
                        if ($mc['user_id'] == $mu['id']) {
                            $note = $mc['notes'];
                            $date = $mc['created_at'];
                        }
                    }
                }
                $crisis_alerts[] = [
                    'user_id' => $mu['id'],
                    'user_name' => $mu['name'],
                    'user_email' => $mu['email'],
                    'notes' => $note,
                    'created_at' => $date
                ];
            }
        }
    }
}

// Fetch community posts for moderation
$forum_posts = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM community_posts ORDER BY created_at DESC LIMIT 15");
        $forum_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} else {
    $forum_posts = array_slice($_SESSION['mock_community_posts'] ?? [], 0, 15);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tet Wellbeing Group</title>
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
                            bg: '#F7F5F0',       // Soft off-white
                            sage: '#5E8C71',     // Sage green
                            slate: '#264653',    // Slate grey
                            sky: '#8ECAE6',      // Sky Blue
                            coral: '#E76F51',    // Coral Red
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
            animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP HEADER -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-6">
                <!-- Branding Logo -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-sage text-white shadow-soft transition-transform group-hover:scale-105">
                        <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-lg font-bold font-outfit text-brand-slate tracking-tight">Tet Wellbeing Group</span>
                        <p class="text-[9px] text-brand-sage font-bold tracking-widest uppercase">Admin System</p>
                    </div>
                </a>
            </div>

            <!-- Profile Menu -->
            <div class="flex items-center gap-4">
                <span class="hidden md:inline text-xs font-bold text-brand-slate font-outfit uppercase tracking-wider bg-brand-sageLight px-3 py-1 rounded-full text-brand-sage">
                    Platform Owner
                </span>
                
                <div class="relative group">
                    <button class="flex items-center gap-3 outline-none">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-sage bg-brand-sage text-white font-bold text-sm shadow-sm">
                            <?php echo $user_initial; ?>
                        </div>
                        <div class="hidden sm:block text-left">
                            <h4 class="text-xs font-bold text-brand-slate leading-tight"><?php echo htmlspecialchars($user_name); ?></h4>
                            <p class="text-[10px] text-gray-500 font-medium"><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                    </button>
                    <!-- Dropdown -->
                    <div class="absolute right-0 mt-2 w-48 origin-top-right rounded-2xl bg-white p-2 shadow-2xl border border-gray-100 opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                        <a href="dashboard.php" class="block px-3 py-2 text-xs text-gray-600 rounded-xl hover:bg-brand-sageLight hover:text-brand-sage transition-colors font-medium font-outfit">Switch to Client View</a>
                        <a href="specialist_dashboard.php" class="block px-3 py-2 text-xs text-gray-600 rounded-xl hover:bg-brand-sageLight hover:text-brand-sage transition-colors font-medium font-outfit font-medium">Switch to Specialist View</a>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-3 py-2 text-xs text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-bold">Logout Console</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENT WRAPPER -->
    <main class="flex-1 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-8 fade-in">
        
        <!-- Alerts Banner -->
        <?php if (!empty($action_success)): ?>
        <div class="mb-6 flex items-center gap-3 p-4 rounded-2xl bg-brand-sageLight border border-brand-sage/20 text-brand-sage text-xs font-bold">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?php echo $action_success; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
        <div class="mb-6 flex items-center gap-3 p-4 rounded-2xl bg-brand-coralLight border border-brand-coral/20 text-brand-coral text-xs font-bold">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?php echo $action_error; ?></span>
        </div>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold font-outfit text-brand-slate tracking-tight">Administrative Command Center</h1>
                <p class="text-sm text-gray-500 mt-1">Supervise system operation, manage specialists credentials, handle holds, and audit user permissions.</p>
            </div>
            <div>
                <a href="dashboard.php" class="px-4 py-2.5 rounded-2xl border border-[#EBE8E0] bg-white text-xs font-bold text-brand-slate hover:bg-brand-sageLight hover:text-brand-sage transition-all shadow-sm">
                    Go to Portal Dashboard
                </a>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- Total Clients -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 flex items-center gap-5">
                <div class="h-12 w-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center shrink-0">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Registered Clients</p>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5"><?php echo $total_clients; ?></h3>
                </div>
            </div>

            <!-- Specialists Approval Status -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 flex items-center gap-5">
                <div class="h-12 w-12 rounded-2xl bg-brand-skyLight text-brand-slate flex items-center justify-center shrink-0">
                    <svg class="h-6 w-6 text-brand-slate" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Specialists Approved</p>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5">
                        <?php echo ($total_specialists - $pending_specialists); ?> <span class="text-xs text-brand-coral font-bold">/ <?php echo $total_specialists; ?> total</span>
                    </h3>
                </div>
            </div>

            <!-- Suspended Accounts -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 flex items-center gap-5">
                <div class="h-12 w-12 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center shrink-0">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Suspended Accounts</p>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5"><?php echo $suspended_accounts; ?></h3>
                </div>
            </div>

            <!-- Escrow holdings -->
            <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 flex items-center gap-5">
                <div class="h-12 w-12 rounded-2xl bg-[#E8F4F8] text-brand-slate flex items-center justify-center shrink-0">
                    <svg class="h-6 w-6 text-brand-slate" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">System Escrow Holding</p>
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5">$<?php echo number_format($escrow_balance_total, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- SPECIALIST MANAGEMENT TABLE -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">Clinical Specialists Directory</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Approve incoming applications, manage billing rates, and suspend licenses.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Specialist Info</th>
                            <th class="p-4">License No</th>
                            <th class="p-4">Hourly Rate</th>
                            <th class="p-4">Escrow Balances</th>
                            <th class="p-4">Verification</th>
                            <th class="p-4">Suspension</th>
                            <th class="p-4 pr-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php 
                        $has_specialists = false;
                        foreach ($users as $u): 
                            if (($u['role'] ?? '') !== 'specialist') continue;
                            $has_specialists = true;
                            $hold = $u['escrow_balance'] ?? 0.00;
                            $cleared = $u['clearance_balance'] ?? 0.00;
                        ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold">
                                        <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate"><?php echo htmlspecialchars($u['name']); ?></h4>
                                        <p class="text-[10px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($u['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 font-mono font-bold text-brand-slate"><?php echo htmlspecialchars($u['license_no'] ?? 'Not Specified'); ?></td>
                            <td class="p-4 font-bold text-brand-slate">$<?php echo number_format($u['hourly_rate'] ?? 0.00, 2); ?>/hr</td>
                            <td class="p-4">
                                <div class="text-[10px] text-gray-600">
                                    <span class="font-bold text-brand-coral">Hold:</span> $<?php echo number_format($hold, 2); ?><br>
                                    <span class="font-bold text-brand-sage">Cleared:</span> $<?php echo number_format($cleared, 2); ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <?php if (($u['is_approved'] ?? 0) == 1): ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">
                                    Approved
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[9px] uppercase tracking-wider">
                                    Pending Approval
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if (($u['is_suspended'] ?? 0) == 1): ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-brand-coralLight text-brand-coral font-bold text-[9px] uppercase tracking-wider">
                                    Suspended
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">
                                    Active
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 pr-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Approve Action -->
                                    <?php if (($u['is_approved'] ?? 0) == 0): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="approve_specialist">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-[10px] shadow-sm transition-colors">
                                            Approve
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Suspend Toggle -->
                                    <?php if (($u['is_suspended'] ?? 0) == 1): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="unsuspend_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-sageLight hover:bg-brand-sage text-brand-sage hover:text-white font-bold text-[10px] transition-colors">
                                            Activate
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this specialist account? They will lose access immediately.');">
                                        <input type="hidden" name="action" value="suspend_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-coralLight hover:bg-brand-coral text-brand-coral hover:text-white font-bold text-[10px] transition-colors">
                                            Suspend
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="px-2.5 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-brand-slate font-bold text-[10px] transition-colors">
                                        Edit
                                    </button>

                                    <form method="POST" class="inline" onsubmit="return confirm('WARNING: Deleting this user will purge all bookings and journal histories. Proceed?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="p-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral transition-colors">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        if (!$has_specialists):
                        ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500 font-medium">No clinical specialists registered yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CLIENT MANAGEMENT TABLE -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">Client & Member Directory</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Monitor client accounts, customize onboarding archetypes, and review account permissions.</p>
                </div>
                <!-- Search bar -->
                <div class="relative max-w-xs w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <input type="text" id="clientSearch" oninput="searchClients()" placeholder="Search clients..." class="block w-full pl-9 pr-4 py-2 border border-gray-200 rounded-2xl bg-brand-inputBg text-xs focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="clientTable">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Client Info</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Archetype Profile</th>
                            <th class="p-4">Account Status</th>
                            <th class="p-4 pr-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php 
                        $has_clients = false;
                        foreach ($users as $u): 
                            if (($u['role'] ?? '') === 'specialist') continue;
                            $has_clients = true;
                            $is_self = $u['id'] == $_SESSION['user_id'];
                        ?>
                        <tr class="client-row hover:bg-brand-bg/40 transition-colors" data-name="<?php echo htmlspecialchars(strtolower($u['name'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($u['email'])); ?>">
                            <td class="p-4 pl-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-brand-sage/10 text-brand-sage flex items-center justify-center font-bold">
                                        <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-brand-slate"><?php echo htmlspecialchars($u['name']); ?> <?php echo $is_self ? '<span class="text-[9px] text-brand-sage font-bold italic">(You)</span>' : ''; ?></h4>
                                        <p class="text-[10px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($u['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="font-semibold text-brand-slate uppercase text-[9px] tracking-wider bg-gray-100 px-2.5 py-0.5 rounded-full">
                                    <?php echo htmlspecialchars($u['role'] ?? 'client'); ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="font-bold text-brand-slate">
                                    <?php echo htmlspecialchars($u['archetype'] ?? 'Not Selected'); ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if (($u['is_suspended'] ?? 0) == 1): ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-brand-coralLight text-brand-coral font-bold text-[9px] uppercase tracking-wider">
                                    Suspended
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-brand-sageLight text-brand-sage font-bold text-[9px] uppercase tracking-wider">
                                    Active
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 pr-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Suspend Toggle -->
                                    <?php if (!$is_self): ?>
                                        <?php if (($u['is_suspended'] ?? 0) == 1): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="unsuspend_user">
                                            <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-sageLight hover:bg-brand-sage text-brand-sage hover:text-white font-bold text-[10px] transition-colors font-outfit">
                                                Activate
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this client account? They will lose access immediately.');">
                                            <input type="hidden" name="action" value="suspend_user">
                                            <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-coralLight hover:bg-brand-coral text-brand-coral hover:text-white font-bold text-[10px] transition-colors font-outfit">
                                                Suspend
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="px-2.5 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-brand-slate font-bold text-[10px] transition-colors font-outfit">
                                        Edit
                                    </button>

                                    <?php if (!$is_self): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('WARNING: Deleting this client will purge all journal check-ins and community posts. Proceed?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="p-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral transition-colors">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        if (!$has_clients):
                        ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 font-medium">No clients registered.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ESCROW HOLDS & RELEASES -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">System Escrow Holds</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Manually release funds from system escrow holds directly to specialist cleared balances.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Client</th>
                            <th class="p-4">Clinical Specialist</th>
                            <th class="p-4">Session Details</th>
                            <th class="p-4">Hold Amount</th>
                            <th class="p-4">Release Date</th>
                            <th class="p-4 pr-6 text-right font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($escrow_bookings)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 font-medium">No pending escrow holds.</td>
                        </tr>
                        <?php else: foreach ($escrow_bookings as $eb): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-bold text-brand-slate"><?php echo htmlspecialchars($eb['client_name']); ?></td>
                            <td class="p-4 font-bold text-brand-slate"><?php echo htmlspecialchars($eb['therapist_name_db']); ?></td>
                            <td class="p-4 text-gray-500"><?php echo htmlspecialchars($eb['booking_date']); ?> at <?php echo htmlspecialchars($eb['booking_time']); ?></td>
                            <td class="p-4 font-bold text-brand-coral">$<?php echo number_format($eb['amount_paid'], 2); ?></td>
                            <td class="p-4 font-mono text-[10px] text-gray-500"><?php echo htmlspecialchars($eb['release_date']); ?></td>
                            <td class="p-4 pr-6 text-right font-outfit">
                                <form method="POST" class="inline" onsubmit="return confirm('Release this escrow hold to the specialist cleared balance immediately?');">
                                    <input type="hidden" name="action" value="release_escrow">
                                    <input type="hidden" name="target_id" value="<?php echo $eb['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-[10px] shadow-sm transition-colors font-outfit">
                                        Release Escrow
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CLINICAL SAFETY TRIAGE ALERTS -->
        <div class="bg-white rounded-3xl shadow-card border border-brand-coral/20 overflow-hidden mb-10">
            <div class="p-6 border-b border-brand-coral/10 bg-brand-coralLight flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-coral">Clinical Safety Distress Alerts</h2>
                    <p class="text-xs text-brand-coral/80 mt-0.5">Real-time keyword flag notifications for client members currently blocked by the crisis safety net.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Vulnerable Member</th>
                            <th class="p-4">Flagged Note Content</th>
                            <th class="p-4">Timestamp</th>
                            <th class="p-4 pr-6 text-right font-outfit">Safety Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($crisis_alerts)): ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500 font-medium">All members safe. No active crisis flags.</td>
                        </tr>
                        <?php else: foreach ($crisis_alerts as $ca): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6">
                                <h4 class="font-bold text-brand-slate"><?php echo htmlspecialchars($ca['user_name']); ?></h4>
                                <p class="text-[10px] text-gray-400 mt-0.5"><?php echo htmlspecialchars($ca['user_email']); ?></p>
                            </td>
                            <td class="p-4 text-gray-600 max-w-sm whitespace-normal leading-relaxed font-medium">
                                "<?php echo htmlspecialchars($ca['notes'] ?? 'No text check-in logs submitted.'); ?>"
                            </td>
                            <td class="p-4 font-mono text-[10px] text-gray-500"><?php echo htmlspecialchars($ca['created_at']); ?></td>
                            <td class="p-4 pr-6 text-right font-outfit">
                                <form method="POST" class="inline" onsubmit="return confirm('Clear the safety triage blocker for this client?');">
                                    <input type="hidden" name="action" value="clear_crisis_admin">
                                    <input type="hidden" name="target_id" value="<?php echo $ca['user_id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold text-[10px] shadow-sm transition-colors font-outfit">
                                        Clear Crisis State
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FORUM CONTENT MODERATION -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">Peer Forum Moderation</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Moderate posts submitted to the community boards to ensure a safe, supportive environment.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Author</th>
                            <th class="p-4">Channel</th>
                            <th class="p-4">Post Content</th>
                            <th class="p-4">Date Posted</th>
                            <th class="p-4 pr-6 text-right font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($forum_posts)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500 font-medium">No community posts found.</td>
                        </tr>
                        <?php else: foreach ($forum_posts as $fp): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-bold text-brand-slate"><?php echo htmlspecialchars($fp['author_name']); ?></td>
                            <td class="p-4">
                                <span class="font-bold text-[10px] uppercase text-brand-sage bg-brand-sageLight px-2 py-0.5 rounded-md font-outfit">
                                    #<?php echo htmlspecialchars($fp['channel']); ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-600 max-w-md whitespace-normal leading-relaxed"><?php echo htmlspecialchars($fp['content']); ?></td>
                            <td class="p-4 font-mono text-[10px] text-gray-500"><?php echo htmlspecialchars($fp['created_at']); ?></td>
                            <td class="p-4 pr-6 text-right font-outfit">
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this forum post?');">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="target_id" value="<?php echo $fp['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral font-bold text-[10px] transition-colors font-outfit">
                                        Delete Post
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DEVELOPER SIMULATOR CARD -->
        <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6 font-outfit">
            <div>
                <h3 class="text-sm font-bold font-outfit text-brand-slate uppercase tracking-wider">Developer QA Testing Sandbox</h3>
                <p class="text-xs text-gray-500 mt-1">Directly trigger test inputs to review and demonstrate the crisis resolution and escrow release workflows.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="seed_test_crisis">
                    <button type="submit" class="px-3.5 py-2.5 rounded-2xl bg-brand-coralLight text-brand-coral hover:bg-brand-coral hover:text-white font-bold text-xs transition-colors font-outfit">
                        Simulate Client Crisis State
                    </button>
                </form>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="seed_test_escrow">
                    <button type="submit" class="px-3.5 py-2.5 rounded-2xl bg-brand-skyLight text-brand-slate hover:bg-brand-sky hover:text-brand-slate font-bold text-xs transition-colors font-outfit">
                        Simulate Escrow Booking Hold
                    </button>
                </form>
            </div>
        </div>

    </main>

    <!-- EDIT USER MODAL -->
    <div id="edit-user-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-md" onclick="closeEditModal()"></div>
        
        <!-- Modal Container -->
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300">
            <button onclick="closeEditModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="flex items-center gap-3.5 mb-5 text-brand-sage">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-sageLight">
                    <svg class="h-6 w-6 text-brand-sage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold font-outfit text-brand-slate">Modify User Credentials</h3>
                    <p class="text-[10px] text-brand-sage font-bold uppercase tracking-wider">Internal administrative editor</p>
                </div>
            </div>

            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="target_id" id="modal-target-id">

                <div class="space-y-4 mb-6 text-xs text-brand-slate">
                    <!-- Full Name -->
                    <div>
                        <label for="modal-name" class="block font-bold mb-1.5">User Full Name</label>
                        <input type="text" name="name" id="modal-name" required class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage">
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="modal-role" class="block font-bold mb-1.5">System Access Role</label>
                        <select name="role" id="modal-role" onchange="toggleRoleFields()" class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage font-medium font-outfit">
                            <option value="client">Client Member</option>
                            <option value="specialist">Clinical Specialist</option>
                            <option value="admin">System Administrator</option>
                        </select>
                    </div>

                    <!-- Client specific: Archetype -->
                    <div id="client-fields-group">
                        <label for="modal-archetype" class="block font-bold mb-1.5">Onboarding Archetype</label>
                        <select name="archetype" id="modal-archetype" class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage font-medium font-outfit">
                            <option value="">None / Not Selected</option>
                            <option value="Dementia Carer">Dementia Carer</option>
                            <option value="Stressed Student">Stressed Student</option>
                            <option value="General Wellbeing">General Wellbeing</option>
                        </select>
                    </div>

                    <!-- Specialist specific fields -->
                    <div id="specialist-fields-group" class="hidden space-y-4">
                        <div>
                            <label for="modal-license" class="block font-bold mb-1.5">License Number</label>
                            <input type="text" name="license_no" id="modal-license" class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage">
                        </div>
                        <div>
                            <label for="modal-rate" class="block font-bold mb-1.5">Hourly Session Rate ($)</label>
                            <input type="number" step="0.01" name="hourly_rate" id="modal-rate" class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="modal-bio" class="block font-bold">Specialist Biography</label>
                                <button type="button" id="transcribe-btn" class="flex items-center gap-1 text-[10px] font-bold text-brand-sage hover:text-brand-sageHover transition-all bg-brand-sageLight px-2 py-0.5 rounded-lg select-none font-outfit">
                                    🎤 Transcribe Voice
                                </button>
                            </div>
                            <textarea name="bio" id="modal-bio" rows="3" class="w-full px-3.5 py-2.5 rounded-2xl border border-gray-200 bg-brand-inputBg focus:outline-none focus:ring-2 focus:ring-brand-sage/20 focus:border-brand-sage resize-none leading-relaxed"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeEditModal()" class="w-1/2 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold transition-colors text-center font-outfit">
                        Cancel
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold shadow-md transition-colors text-center font-outfit">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS SCRIPTS -->
    <script>
        const editModal = document.getElementById('edit-user-modal');
        const modalTargetId = document.getElementById('modal-target-id');
        const modalName = document.getElementById('modal-name');
        const modalRole = document.getElementById('modal-role');
        const modalArchetype = document.getElementById('modal-archetype');
        const modalLicense = document.getElementById('modal-license');
        const modalRate = document.getElementById('modal-rate');
        const modalBio = document.getElementById('modal-bio');

        const clientGroup = document.getElementById('client-fields-group');
        const specialistGroup = document.getElementById('specialist-fields-group');

        function openEditModal(user) {
            modalTargetId.value = user.id;
            modalName.value = user.name;
            modalRole.value = user.role || 'client';
            modalArchetype.value = user.archetype || '';
            modalLicense.value = user.license_no || '';
            modalRate.value = user.hourly_rate || 0.00;
            modalBio.value = user.bio || '';

            toggleRoleFields();

            editModal.classList.remove('opacity-0', 'pointer-events-none');
            editModal.querySelector('.relative').classList.remove('scale-95');
            editModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEditModal() {
            editModal.classList.add('opacity-0', 'pointer-events-none');
            editModal.querySelector('.relative').classList.remove('scale-100');
            editModal.querySelector('.relative').classList.add('scale-95');
        }

        function toggleRoleFields() {
            const role = modalRole.value;
            if (role === 'specialist') {
                clientGroup.classList.add('hidden');
                specialistGroup.classList.remove('hidden');
            } else if (role === 'client') {
                clientGroup.classList.remove('hidden');
                specialistGroup.classList.add('hidden');
            } else {
                clientGroup.classList.add('hidden');
                specialistGroup.classList.add('hidden');
            }
        }

        // Live filtering clients
        function searchClients() {
            const query = document.getElementById('clientSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.client-row');

            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const email = row.getAttribute('data-email');
                if (name.includes(query) || email.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // VOICE TRANSCRIPTION UTILITY FOR BIO
        const transcribeBtn = document.getElementById('transcribe-btn');
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'en-US';

            recognition.onstart = () => {
                transcribeBtn.textContent = 'Listening...';
                transcribeBtn.classList.remove('bg-brand-sageLight', 'text-brand-sage');
                transcribeBtn.classList.add('bg-brand-coral', 'text-white', 'animate-pulse');
            };

            recognition.onresult = (event) => {
                const speechToText = event.results[0][0].transcript;
                modalBio.value = (modalBio.value ? modalBio.value + ' ' : '') + speechToText;
            };

            recognition.onend = () => {
                transcribeBtn.textContent = '🎤 Transcribe Voice';
                transcribeBtn.classList.remove('bg-brand-coral', 'text-white', 'animate-pulse');
                transcribeBtn.classList.add('bg-brand-sageLight', 'text-brand-sage');
            };

            recognition.onerror = () => {
                recognition.stop();
            };

            transcribeBtn.addEventListener('click', () => {
                recognition.start();
            });
        } else {
            transcribeBtn.style.display = 'none';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
