<?php
/**
 * Tet Wellbeing Group - Teletherapy Marketplace (teletherapy_hub.php)
 * Match with vetted practitioners, check insurance compatibility, and book virtual sessions.
 */
require_once 'db.php';

// Auth Guard: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_initial = strtoupper(substr($user_name, 0, 1));

// Fetch archetype
$user_archetype = $_SESSION['user_archetype'] ?? null;
if ($user_archetype === null) {
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT archetype FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_archetype = $stmt->fetchColumn();
            $_SESSION['user_archetype'] = $user_archetype;
        } catch (PDOException $ex) {}
    }
}
if ($user_archetype === null && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $email => $u) {
        if ($u['id'] == $user_id) {
            $user_archetype = $u['archetype'] ?? null;
            $_SESSION['user_archetype'] = $user_archetype;
            break;
        }
    }
}

// Fetch approved specialists
$therapists = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'specialist' AND is_approved = 1 ORDER BY name ASC");
        $therapists = $stmt->fetchAll();
    } catch (PDOException $ex) {}
}

if (empty($therapists) && isset($_SESSION['mock_users'])) {
    foreach ($_SESSION['mock_users'] as $mu) {
        if (($mu['role'] ?? '') === 'specialist' && ($mu['is_approved'] ?? 0) == 1) {
            $therapists[] = $mu;
        }
    }
    usort($therapists, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
}

$action_success = '';
$action_error = '';

// Handle crisis dismissal if triggered in this hub
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_crisis') {
    $_SESSION['crisis_state'] = 0;
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET crisis_state = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $ex) {}
    }
    if (isset($_SESSION['mock_users'])) {
        foreach ($_SESSION['mock_users'] as $email => &$mu) {
            if ($mu['id'] == $user_id) {
                $mu['crisis_state'] = 0;
            }
        }
    }
    header('Location: teletherapy_hub.php');
    exit;
}

// 1. AJAX API Endpoint: Get available slots
if (isset($_GET['action']) && $_GET['action'] === 'get_slots') {
    header('Content-Type: application/json');
    $therapist = filter_input(INPUT_GET, 'therapist', FILTER_DEFAULT);
    $date_str = $_GET['date'] ?? '';
    
    if (empty($therapist) || empty($date_str)) {
        echo json_encode([]);
        exit;
    }
    
    $day_of_week = date('l', strtotime($date_str));
    
    // Get weekly availability for this therapist on this day
    $weekly_slots = [];
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT time_slot FROM therapist_availability WHERE therapist_name = ? AND day_of_week = ?");
            $stmt->execute([$therapist, $day_of_week]);
            $weekly_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $ex) {}
    }
    
    if (empty($weekly_slots) && isset($_SESSION['mock_availability'])) {
        foreach ($_SESSION['mock_availability'] as $avail) {
            if ($avail['therapist_name'] === $therapist && $avail['day_of_week'] === $day_of_week) {
                $weekly_slots[] = $avail['time_slot'];
            }
        }
    }
    
    // Get active bookings for this therapist on this date
    $booked_slots = [];
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT booking_time FROM teletherapy_bookings WHERE therapist_name = ? AND booking_date = ?");
            $stmt->execute([$therapist, $date_str]);
            $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $ex) {}
    }
    
    if (empty($booked_slots) && isset($_SESSION['mock_bookings'])) {
        foreach ($_SESSION['mock_bookings'] as $bk) {
            if ($bk['therapist_name'] === $therapist && $bk['booking_date'] === $date_str) {
                $booked_slots[] = $bk['booking_time'];
            }
        }
    }
    
    // Compute difference (weekly_slots not in booked_slots)
    $available = array_values(array_diff($weekly_slots, $booked_slots));
    
    echo json_encode($available);
    exit;
}

// 2. HANDLE Simulated Availability Profile Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_availability') {
    $therapist = filter_input(INPUT_POST, 'therapist_name', FILTER_DEFAULT);
    $selected_slots = $_POST['slots'] ?? []; // format: Array of 'Day_of_week|Time_slot'
    
    if (!empty($therapist)) {
        $saved = false;
        
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();
                // Clear existing
                $clear = $pdo->prepare("DELETE FROM therapist_availability WHERE therapist_name = ?");
                $clear->execute([$therapist]);
                
                // Insert new
                if (!empty($selected_slots)) {
                    $insert = $pdo->prepare("INSERT INTO therapist_availability (therapist_name, day_of_week, time_slot) VALUES (?, ?, ?)");
                    foreach ($selected_slots as $slot_raw) {
                        $parts = explode('|', $slot_raw);
                        if (count($parts) === 2) {
                            $insert->execute([$therapist, $parts[0], $parts[1]]);
                        }
                    }
                }
                $pdo->commit();
                $saved = true;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        
        // Session fallback
        if (isset($_SESSION['mock_availability'])) {
            // Remove old
            $_SESSION['mock_availability'] = array_filter($_SESSION['mock_availability'], function($item) use ($therapist) {
                return $item['therapist_name'] !== $therapist;
            });
            // Add new
            foreach ($selected_slots as $slot_raw) {
                $parts = explode('|', $slot_raw);
                if (count($parts) === 2) {
                    $_SESSION['mock_availability'][] = [
                        'therapist_name' => $therapist,
                        'day_of_week' => $parts[0],
                        'time_slot' => $parts[1]
                    ];
                }
            }
            $saved = true;
        }
        
        if ($saved) {
            header("Location: teletherapy_hub.php?availability_success=1");
            exit;
        }
    }
}

// 3. HANDLE BOOKING APPOINTMENTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_session') {
    $therapist_name = filter_input(INPUT_POST, 'therapist_name', FILTER_DEFAULT);
    $booking_date = $_POST['booking_date'] ?? '';
    $booking_time = $_POST['booking_time'] ?? '';
    $insurance_provider = filter_input(INPUT_POST, 'insurance_provider', FILTER_DEFAULT);

    if (!empty($therapist_name) && !empty($booking_date) && !empty($booking_time)) {
        
        // Collision / Double Booking Check
        $already_booked = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM teletherapy_bookings WHERE therapist_name = ? AND booking_date = ? AND booking_time = ?");
                $stmt->execute([$therapist_name, $booking_date, $booking_time]);
                if ($stmt->fetchColumn() > 0) {
                    $already_booked = true;
                }
            } catch (PDOException $ex) {}
        }
        if (!$already_booked && isset($_SESSION['mock_bookings'])) {
            foreach ($_SESSION['mock_bookings'] as $bk) {
                if ($bk['therapist_name'] === $therapist_name && $bk['booking_date'] === $booking_date && $bk['booking_time'] === $booking_time) {
                    $already_booked = true;
                    break;
                }
            }
        }

        if ($already_booked) {
            $action_error = "This appointment slot is already booked by another user. Please choose a different date or time.";
        } else {
            $booking_saved = false;

            // Fetch therapist details (ID, rate)
            $therapist_id = null;
            $therapist_rate = 100.00; // default fallback
            
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT id, hourly_rate FROM users WHERE name = ? AND role = 'specialist'");
                    $stmt->execute([$therapist_name]);
                    $t_data = $stmt->fetch();
                    if ($t_data) {
                        $therapist_id = $t_data['id'];
                        $therapist_rate = $t_data['hourly_rate'] ?? 100.00;
                    }
                } catch (PDOException $ex) {}
            }
            
            if (!$therapist_id && isset($_SESSION['mock_users'])) {
                foreach ($_SESSION['mock_users'] as $email => $u) {
                    if ($u['name'] === $therapist_name && ($u['role'] ?? '') === 'specialist') {
                        $therapist_id = $u['id'];
                        $therapist_rate = $u['hourly_rate'] ?? 100.00;
                        break;
                    }
                }
            }

            $release_date = date('Y-m-d H:i:s', strtotime('+7 days'));

            if ($db_connected && $pdo) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO teletherapy_bookings (user_id, therapist_name, therapist_id, booking_date, booking_time, insurance_provider, amount_paid, payment_status, release_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'escrow', ?)");
                    $stmt->execute([$user_id, $therapist_name, $therapist_id, $booking_date, $booking_time, $insurance_provider, $therapist_rate, $release_date]);
                    
                    if ($therapist_id) {
                        $up_escrow = $pdo->prepare("UPDATE users SET escrow_balance = escrow_balance + ? WHERE id = ?");
                        $up_escrow->execute([$therapist_rate, $therapist_id]);
                    }
                    
                    $pdo->commit();
                    $booking_saved = true;
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                }
            }

            // Fallback session write
            if (!$booking_saved) {
                $_SESSION['mock_bookings'][] = [
                    'user_id' => $user_id,
                    'therapist_name' => $therapist_name,
                    'therapist_id' => $therapist_id,
                    'booking_date' => $booking_date,
                    'booking_time' => $booking_time,
                    'insurance_provider' => $insurance_provider,
                    'amount_paid' => $therapist_rate,
                    'payment_status' => 'escrow',
                    'release_date' => $release_date,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if ($therapist_id && isset($_SESSION['mock_users'])) {
                    foreach ($_SESSION['mock_users'] as $email => &$u) {
                        if ($u['id'] == $therapist_id) {
                            $u['escrow_balance'] = ($u['escrow_balance'] ?? 0.00) + $therapist_rate;
                            break;
                        }
                    }
                    unset($u);
                }
                
                $booking_saved = true;
            }

            if ($booking_saved) {
                header("Location: teletherapy_hub.php?booking_success=1&rate=" . urlencode($therapist_rate));
                exit;
            }
        }
    } else {
        $action_error = "Please fill out all the appointment details.";
    }
}

// 4. CHECK SUCCESS REDIRECTS
if (isset($_GET['booking_success'])) {
    $rate_info = isset($_GET['rate']) ? " Payment of $" . htmlspecialchars($_GET['rate']) . " is held securely in system escrow (clears in 7 days)." : "";
    $action_success = "Your teletherapy session has been successfully booked. You will receive a video room link via email." . $rate_info;
}
if (isset($_GET['availability_success'])) {
    $action_success = "Practitioner availability schedule updated successfully.";
}

// 3. READ ACTIVE BOOKINGS FOR USER
$bookings = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM teletherapy_bookings WHERE user_id = ? ORDER BY booking_date ASC, booking_time ASC");
        $stmt->execute([$user_id]);
        $bookings = $stmt->fetchAll();
    } catch (PDOException $ex) {}
}

if (empty($bookings) && isset($_SESSION['mock_bookings'])) {
    $filtered = array_filter($_SESSION['mock_bookings'], function($b) use ($user_id) {
        return $b['user_id'] == $user_id;
    });
    // Sort ascending by date
    usort($filtered, function($a, $b) {
        $dateA = $a['booking_date'] . ' ' . $a['booking_time'];
        $dateB = $b['booking_date'] . ' ' . $b['booking_time'];
        return strcmp($dateA, $dateB);
    });
    $bookings = $filtered;
}

$today_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teletherapy Marketplace - Tet Wellbeing Group</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Tailwind Configuration -->
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
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #F7F5F0;
        }
        ::-webkit-scrollbar-thumb {
            background: #D9D5CB;
            border-radius: 9999px;
            border: 2px solid #F7F5F0;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #5E8C71;
        }
    </style>
</head>
<body class="min-h-full flex flex-col selection:bg-brand-sage/20 selection:text-brand-slate">

    <!-- TOP NAVIGATION BAR -->
    <header class="sticky top-0 z-40 w-full border-b border-[#EBE8E0] bg-brand-bg/95 backdrop-blur-md">
        <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="dashboard.php" class="flex items-center transition-transform hover:scale-[1.01] active:scale-95">
                <img src="logo.svg" alt="Tet Wellbeing Group" class="h-16 w-auto">
            </a>

            <!-- Actions -->
            <div class="flex items-center gap-4">
                <button type="button" onclick="openEmergencyModal()" class="flex items-center gap-2 rounded-2xl bg-brand-coral px-4 py-2 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:bg-brand-coralHover hover:shadow-lg active:scale-95">
                    <svg class="h-4.5 w-4.5 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>Emergency Support</span>
                </button>

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
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">My Profile</a>
                        <a href="#" class="block px-3 py-2 text-sm text-gray-600 rounded-xl hover:bg-brand-bg transition-colors">Settings</a>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-brand-coral rounded-xl hover:bg-brand-coralLight transition-colors font-medium">Log out</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-grow mx-auto w-full max-w-4xl px-4 py-8 pb-24 md:pb-8 fade-in">
        
        <!-- Header / Banner Card (The Safe Bridge) -->
        <div class="mb-8 relative overflow-hidden rounded-3xl bg-white border border-[#EBE8E0] shadow-soft p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 z-10 max-w-lg">
                <p class="text-xs font-bold tracking-wider text-brand-sage uppercase font-outfit">Ecosystem Layer 3</p>
                <h1 class="text-3xl md:text-4xl font-extrabold font-outfit text-brand-slate tracking-tight mt-1">
                    Teletherapy <span class="text-brand-sage">Marketplace</span>
                </h1>
                <p class="text-gray-500 text-sm leading-relaxed mt-1">
                    Schedule virtual sessions with accredited, licensed clinical specialists specializing in caregiver burnout and individual coping methods.
                </p>
            </div>
            <div class="relative shrink-0 w-full md:w-56 h-36 md:h-36 rounded-2xl overflow-hidden shadow-soft border border-brand-sage/10">
                <img src="images/safe_bridge.png" alt="Teletherapy Professional" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- APP NAVIGATION TABS -->
        <div class="flex items-center gap-6 border-b border-[#EBE8E0] mb-8 text-sm font-semibold overflow-x-auto whitespace-nowrap pb-1">
            <a href="dashboard.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">My Dashboard</a>
            <a href="caregiver_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Caregiver Hub</a>
            <a href="community_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Community Hub</a>
            <a href="teletherapy_hub.php" class="border-b-2 border-brand-sage pb-3 px-1 text-brand-sage font-outfit">Teletherapy Hub</a>
            <a href="ai_companion.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">AI Companion</a>
            <a href="predictive_hub.php" class="border-b-2 border-transparent pb-3 px-1 text-gray-400 hover:text-brand-slate hover:border-gray-300 transition-all font-outfit">Digital Twin</a>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if (!empty($action_success)): ?>
            <div id="action-toast-s" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-sage bg-brand-sageLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-sage text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Appointment Booked</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_success; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-s').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($action_error)): ?>
            <div id="action-toast-e" class="mb-6 flex items-start gap-3 rounded-2xl border border-brand-coral bg-brand-coralLight p-4 text-brand-slate shadow-soft transition-all duration-300">
                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-coral text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <div class="flex-grow">
                    <h4 class="font-bold text-sm">Notice</h4>
                    <p class="text-xs text-gray-600 mt-0.5"><?php echo $action_error; ?></p>
                </div>
                <button onclick="document.getElementById('action-toast-e').remove()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- GRID LAYOUT: FILTER BAR & PRACTITIONER LIST -->
        <div class="space-y-8">
            
            <!-- DYNAMIC SEARCH & FILTER CONTROLS -->
            <section class="bg-white rounded-3xl p-5 shadow-soft border border-[#EBE8E0] grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Specialty Dropdown -->
                <div>
                    <label for="filter-specialty" class="block text-xs font-bold text-brand-slate uppercase tracking-wider mb-1.5 font-outfit">Filter by Specialty</label>
                    <select id="filter-specialty" onchange="applyFilters()" class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs font-semibold focus:border-brand-sage focus:outline-none text-brand-slate">
                        <option value="all">All Specialties</option>
                        <option value="cbt">Cognitive Behavioral Therapy (CBT)</option>
                        <option value="caregiver">Caregiver Respite & stress</option>
                        <option value="anxiety">Anxiety & Burnout Support</option>
                    </select>
                </div>

                <!-- Insurance Dropdown -->
                <div>
                    <label for="filter-insurance" class="block text-xs font-bold text-brand-slate uppercase tracking-wider mb-1.5 font-outfit">Filter by Insurance</label>
                    <select id="filter-insurance" onchange="applyFilters()" class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs font-semibold focus:border-brand-sage focus:outline-none text-brand-slate">
                        <option value="all">All Insurance Compatibility</option>
                        <option value="blue-cross">Blue Cross</option>
                        <option value="aetna">Aetna</option>
                        <option value="cigna">Cigna</option>
                        <option value="self-pay">Self-Pay / Private</option>
                    </select>
                </div>
            </section>

            <!-- THERAPIST DIRECTORY -->
            <section>
                <div class="mb-4 flex items-center justify-between px-1">
                    <span class="text-xs text-gray-400 font-bold uppercase tracking-wider">Vetted Clinical Specialists</span>
                    <span id="filtered-count-text" class="text-xs text-brand-sage font-bold">Showing 3 Practitioners</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="therapists-grid">
                    <?php foreach ($therapists as $t): 
                        $t_name = $t['name'];
                        $t_id = $t['id'];
                        $t_rate = $t['hourly_rate'] ?? 100.00;
                        $t_bio = $t['bio'] ?? '';
                        $t_license = $t['license_no'] ?? 'Accredited Specialist';
                        
                        // Default properties
                        $specialties_attr = 'cbt,caregiver,anxiety';
                        $specialty_badges = ['Clinical Care', 'Coping Strategies'];
                        $insurances_attr = 'blue-cross,aetna,cigna,self-pay';
                        $is_recommended = false;
                        
                        if (strpos($t_name, 'Evelyn Carter') !== false) {
                            $specialties_attr = 'cbt,anxiety';
                            $specialty_badges = ['CBT', 'Anxiety Relief'];
                            $insurances_attr = 'blue-cross,cigna,self-pay';
                            if ($user_archetype === 'stressed_student') {
                                $is_recommended = true;
                            }
                        } elseif (strpos($t_name, 'Marcus Vance') !== false) {
                            $specialties_attr = 'caregiver';
                            $specialty_badges = ['Caregiver Respite', 'Setting Boundaries'];
                            $insurances_attr = 'blue-cross,aetna,self-pay';
                            if ($user_archetype === 'dementia_carer') {
                                $is_recommended = true;
                            }
                        } elseif (strpos($t_name, 'Clara Mendoza') !== false) {
                            $specialties_attr = 'anxiety,cbt';
                            $specialty_badges = ['Family Support', 'Anxiety & Burnout'];
                            $insurances_attr = 'aetna,cigna,self-pay';
                            if ($user_archetype === 'general_wellbeing') {
                                $is_recommended = true;
                            }
                        } else {
                            if ($user_archetype === 'general_wellbeing') {
                                $is_recommended = true;
                            }
                        }
                        
                        // Avatar initials
                        $initials = '';
                        $words = explode(' ', str_replace(['Dr.', 'PhD', 'LCSW', 'LMFT', ','], '', $t_name));
                        $words = array_filter(array_map('trim', $words));
                        foreach (array_slice($words, 0, 2) as $w) {
                            $initials .= strtoupper(substr($w, 0, 1));
                        }
                        if (empty($initials)) $initials = 'SP';
                        
                        $should_highlight = $is_recommended && isset($_GET['recommend']);
                    ?>
                    
                    <div class="therapist-card bg-white rounded-3xl p-6 border <?php echo $should_highlight ? 'border-brand-coral bg-brand-coralLight/10 ring-2 ring-brand-coral/20' : 'border-[#EBE8E0]'; ?> shadow-soft flex flex-col justify-between transition-all duration-300 hover:shadow-card hover:-translate-y-1 relative"
                         data-specialties="<?php echo $specialties_attr; ?>" data-insurances="<?php echo $insurances_attr; ?>">
                        
                        <?php if ($is_recommended): ?>
                            <span class="absolute top-4 right-4 px-2 py-0.5 rounded bg-brand-sageLight text-brand-sage text-[8px] font-bold uppercase tracking-wider">
                                Recommended
                            </span>
                        <?php endif; ?>

                        <div>
                            <!-- Header / Avatar placeholder -->
                            <div class="flex items-center gap-3 mb-4">
                                <div class="h-12 w-12 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold text-sm">
                                    <?php echo $initials; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-brand-slate text-sm font-outfit"><?php echo htmlspecialchars($t_name); ?></h4>
                                    <span class="text-[10px] text-gray-400 font-medium truncate max-w-[150px] inline-block"><?php echo htmlspecialchars($t_license); ?></span>
                                </div>
                            </div>
                            
                            <!-- Specialties badges -->
                            <div class="flex flex-wrap gap-1 mb-3">
                                <?php foreach ($specialty_badges as $badge): ?>
                                    <span class="px-2 py-0.5 rounded-md bg-brand-bg text-[9px] font-bold text-brand-slate"><?php echo htmlspecialchars($badge); ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="text-xs text-gray-500 leading-relaxed mb-4">
                                <?php echo htmlspecialchars($t_bio); ?>
                            </p>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-brand-slate">$<?php echo number_format($t_rate, 2); ?></span>
                                <span class="text-[9px] text-gray-400">/ session</span>
                            </div>
                            <button type="button" onclick="openBookingModal('<?php echo htmlspecialchars($t_name); ?>')" class="px-4 py-2 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold transition-all active:scale-95">
                                Book Session
                            </button>
                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                </div>

                <!-- Empty search results notice (hidden by default) -->
                <div id="empty-filters-notice" class="hidden bg-white rounded-3xl p-12 text-center border border-[#EBE8E0] text-gray-400 text-xs shadow-soft">
                    <span class="text-3xl block mb-2">🔍</span>
                    No practitioners match the selected filters.<br>Try relaxing your specialty or insurance selectors.
                </div>
            </section>

            <!-- USER BOOKING HISTORY -->
            <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0]">
                <h3 class="text-lg font-bold font-outfit text-brand-slate mb-4">My Booked Sessions</h3>
                
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-8 text-gray-400 text-xs bg-[#FAF9F6] rounded-2xl border border-dashed border-gray-200">
                        <span class="text-2xl block mb-1">📅</span>
                        No teletherapy bookings scheduled yet.<br>Click "Book Session" on a clinical specialist above.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($bookings as $bk): ?>
                            <div class="p-4 rounded-2xl bg-brand-bg border border-gray-100 flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <h4 class="text-xs font-bold text-brand-slate"><?php echo htmlspecialchars($bk['therapist_name']); ?></h4>
                                    <p class="text-[10px] text-gray-500 font-semibold flex items-center gap-1">
                                        <span>📅</span>
                                        <span><?php echo date('l, M j, Y', strtotime($bk['booking_date'])); ?></span>
                                    </p>
                                    <p class="text-[10px] text-gray-500 font-semibold flex items-center gap-1">
                                        <span>⏰</span>
                                        <span><?php echo htmlspecialchars($bk['booking_time']); ?></span>
                                    </p>
                                    <?php if (!empty($bk['insurance_provider'])): ?>
                                        <p class="text-[9px] text-gray-400 uppercase font-bold bg-white px-2 py-0.5 rounded inline-block">
                                            Insurance: <?php echo htmlspecialchars($bk['insurance_provider']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2.5 py-1 rounded bg-[#E8EFEA] text-[9px] font-bold text-brand-sage uppercase tracking-wider">Scheduled</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- PRACTITIONER AVAILABILITY EDITOR (Simulated Profile Settings) -->
            <section class="bg-white rounded-3xl p-6 md:p-8 shadow-soft border border-[#EBE8E0]">
                <div class="mb-4">
                    <h3 class="text-lg font-bold font-outfit text-brand-slate">Practitioner Availability Settings (Simulated)</h3>
                    <p class="text-xs text-gray-500 mt-1">Select a clinical specialist below to update their available times in their active profile. Clients will only see slots matching these settings that aren't already booked.</p>
                </div>
                
                <form method="POST" action="teletherapy_hub.php" class="space-y-6">
                    <input type="hidden" name="action" value="save_availability">
                    
                    <div class="w-full sm:w-72">
                        <label for="editor-therapist" class="block text-xs font-bold text-brand-slate uppercase tracking-wider mb-1.5 font-outfit">Select Practitioner Profile</label>
                        <select id="editor-therapist" name="therapist_name" onchange="loadEditorAvailability()" class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs font-semibold focus:border-brand-sage focus:outline-none text-brand-slate">
                            <option value="Dr. Evelyn Carter, PhD">Dr. Evelyn Carter, PhD</option>
                            <option value="Marcus Vance, LCSW">Marcus Vance, LCSW</option>
                            <option value="Clara Mendoza, LMFT">Clara Mendoza, LMFT</option>
                        </select>
                    </div>

                    <!-- Interactive grid of checkboxes -->
                    <div class="space-y-4 pt-2">
                        <span class="block text-xs font-bold text-brand-slate uppercase tracking-wider font-outfit">Select Available Weekly Slots</span>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4" id="availability-checkboxes-grid">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            $slots = ['09:00 AM', '11:00 AM', '02:00 PM', '04:00 PM'];
                            
                            // Fetch all availability
                            $current_avail = [];
                            if ($db_connected && $pdo) {
                                try {
                                    $current_avail = $pdo->query("SELECT * FROM therapist_availability")->fetchAll();
                                } catch (PDOException $ex) {}
                            } else if (isset($_SESSION['mock_availability'])) {
                                $current_avail = $_SESSION['mock_availability'];
                            }
                            
                            foreach ($days as $d) {
                                echo '<div class="p-3 bg-[#FAF9F6] rounded-2xl border border-gray-100 space-y-2">';
                                echo '<h4 class="text-xs font-bold text-brand-slate border-b border-gray-200 pb-1 font-outfit">' . $d . '</h4>';
                                foreach ($slots as $s) {
                                    $key = $d . '|' . $s;
                                    echo '<label class="flex items-center gap-2 cursor-pointer select-none text-[11px] text-gray-600 font-medium py-0.5 hover:text-brand-slate">';
                                    echo '<input type="checkbox" name="slots[]" value="' . htmlspecialchars($key) . '" class="avail-chk rounded border-gray-300 text-brand-sage focus:ring-brand-sage" data-day="' . $d . '" data-slot="' . $s . '">';
                                    echo '<span>' . $s . '</span>';
                                    echo '</label>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-6 py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-soft transition-all active:scale-95">
                            Save Availability Profile
                        </button>
                    </div>
                </form>
            </section>

        </div>

    </main>

    <!-- MOBILE NAVIGATION BAR (Sticky Bottom) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 block md:hidden border-t border-[#EBE8E0] bg-white/90 backdrop-blur-lg px-6 py-2 shadow-2xl">
        <div class="flex items-center justify-between mx-auto max-w-md">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Home</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span class="text-[10px] tracking-wider uppercase font-semibold">Journal</span>
            </a>
            <a href="caregiver_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Caregiver</span>
            </a>
            <a href="community_hub.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-brand-slate transition-colors">
                <svg class="h-5.5 w-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-[10px] tracking-wider uppercase">Community</span>
            </a>
        </div>
    </nav>

    <!-- EMERGENCY SUPPORT MODAL -->
    <div id="emergency-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeEmergencyModal()"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/20 transform scale-95 transition-all duration-300">
            <button onclick="closeEmergencyModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="flex items-center gap-3 mb-4 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-xs text-brand-coral font-semibold">Immediate 24/7 Assistance</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">If you are facing an emergency, in distress, or in danger of hurting yourself, please reach out to one of the free support services below.</p>
            <div class="space-y-3.5 mb-6">
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">988 Crisis Lifeline</h4>
                        <p class="text-xs text-gray-500">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 988</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Samaritans Helpline</h4>
                        <p class="text-xs text-gray-500">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-4 py-1.5 rounded-xl bg-brand-coral text-white text-xs font-bold shadow-sm hover:bg-brand-coralHover transition-colors">Call 116 123</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Crisis Text Line (US/CA)</h4>
                        <p class="text-xs text-gray-500">Text HOME to 741741 (Free, 24/7)</p>
                    </div>
                    <a href="sms:741741?body=HOME" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text HOME</a>
                </div>
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-[#FAF9F6] border border-gray-100">
                    <div>
                        <h4 class="text-sm font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-xs text-gray-500">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-4 py-1.5 rounded-xl bg-brand-slate text-white text-xs font-bold shadow-sm hover:bg-gray-800 transition-colors">Text SHOUT</a>
                </div>
            </div>
            <button onclick="closeEmergencyModal()" class="w-full py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-sm transition-colors">Close</button>
        </div>
    </div>

    <!-- CLINICAL SAFETY TRIAGE CRISIS MODAL -->
    <?php if (isset($_SESSION['crisis_state']) && $_SESSION['crisis_state'] == 1): ?>
    <div id="crisis-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-brand-slate/60 backdrop-blur-md"></div>
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-brand-coral/30 transform scale-100 transition-all duration-300">
            
            <div class="flex items-center gap-3.5 mb-5 text-brand-coral">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-coralLight">
                    <svg class="h-6 w-6 text-brand-coral" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold font-outfit text-brand-slate">Crisis Support Resources</h3>
                    <p class="text-[10px] text-brand-coral font-bold uppercase tracking-wider font-outfit">Un-bypassable safety notice</p>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-6 leading-relaxed">
                Our safety scans detected keywords indicating severe distress in your journal entry. Your life and wellbeing are valuable to us. Please connect with one of these free, confidential support resources immediately:
            </p>

            <div class="space-y-3 mb-6">
                <!-- 988 -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">988 Suicide & Crisis Lifeline</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call or Text 24/7 (US & Canada)</p>
                    </div>
                    <a href="tel:988" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 988</a>
                </div>

                <!-- Samaritans UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Samaritans Helpline (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Call 116 123 24/7 (United Kingdom)</p>
                    </div>
                    <a href="tel:116123" class="px-3.5 py-1.5 rounded-xl bg-brand-coral text-white font-bold text-[10px] shadow-sm hover:bg-brand-coralHover">Call 116 123</a>
                </div>

                <!-- Shout UK -->
                <div class="flex items-center justify-between p-3.5 rounded-2xl bg-brand-inputBg border border-gray-100 text-xs">
                    <div>
                        <h4 class="font-bold text-brand-slate">Shout Crisis Text (UK)</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">Text SHOUT to 85258 (Free, 24/7)</p>
                    </div>
                    <a href="sms:85258?body=SHOUT" class="px-3.5 py-1.5 rounded-xl bg-brand-slate text-white font-bold text-[10px] shadow-sm hover:bg-gray-800">Text SHOUT</a>
                </div>
            </div>

            <form method="POST" action="teletherapy_hub.php">
                <input type="hidden" name="action" value="clear_crisis">
                
                <label class="flex items-start gap-2.5 mb-4 cursor-pointer select-none text-[10px] text-gray-500 leading-normal">
                    <input type="checkbox" required class="rounded border-gray-300 text-brand-sage focus:ring-brand-sage mt-0.5">
                    <span>I acknowledge these resources and confirm that I am safe or currently seeking appropriate help.</span>
                </label>

                <button type="submit" class="w-full py-2.5 rounded-2xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-colors text-center">
                    Acknowledge & Dismiss Modal
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- BOOKING MODAL -->
    <div id="booking-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-brand-slate/40 backdrop-blur-sm" onclick="closeBookingModal()"></div>
        
        <!-- Modal Dialog -->
        <div class="relative bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-gray-100 transform scale-95 transition-all duration-300">
            <!-- Close Button -->
            <button onclick="closeBookingModal()" class="absolute top-4 right-4 text-gray-400 hover:text-brand-slate transition-colors p-1 rounded-full hover:bg-gray-100">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <!-- Header -->
            <div class="mb-4">
                <h3 class="text-lg font-bold font-outfit text-brand-slate">Book Teletherapy Session</h3>
                <p id="booking-therapist-subtitle" class="text-xs text-brand-sage font-semibold mt-0.5">Marcus Vance, LCSW</p>
            </div>

            <!-- Form -->
            <form method="POST" action="teletherapy_hub.php" class="space-y-4">
                <input type="hidden" name="action" value="book_session">
                <input type="hidden" name="therapist_name" id="modal-therapist-name">

                <div>
                    <label for="booking_date" class="block text-xs font-semibold text-brand-slate mb-1">Appointment Date</label>
                    <input 
                        type="date" 
                        name="booking_date" 
                        id="booking_date" 
                        required 
                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                        onchange="fetchAvailableSlots()"
                        class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate"
                    >
                </div>

                <div>
                    <label for="booking_time" class="block text-xs font-semibold text-brand-slate mb-1">Preferred Time Slot</label>
                    <select 
                        name="booking_time" 
                        id="booking_time" 
                        required 
                        disabled
                        class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate font-semibold disabled:opacity-50"
                    >
                        <option value="">Please select a date first</option>
                    </select>
                </div>

                <div>
                    <label for="insurance_provider" class="block text-xs font-semibold text-brand-slate mb-1">Insurance Provider (Optional)</label>
                    <input 
                        type="text" 
                        name="insurance_provider" 
                        id="insurance_provider" 
                        placeholder="e.g. Blue Cross (Leave blank for Self-Pay)"
                        class="w-full rounded-xl border border-gray-200 bg-[#FAF9F6] p-3 text-xs focus:border-brand-sage focus:outline-none text-brand-slate"
                    >
                </div>

                <div class="pt-2 flex gap-3">
                    <button type="button" onclick="closeBookingModal()" class="w-1/2 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-xs transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-md transition-colors">
                        Confirm Session
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VANILLA JAVASCRIPT -->
    <script>
        // 1. DYNAMIC PRACTITIONER FILTER ENGINE
        function applyFilters() {
            const selectedSpecialty = document.getElementById('filter-specialty').value;
            const selectedInsurance = document.getElementById('filter-insurance').value;
            
            const cards = document.querySelectorAll('.therapist-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardSpecialties = card.getAttribute('data-specialties').split(',');
                const cardInsurances = card.getAttribute('data-insurances').split(',');

                const matchesSpecialty = (selectedSpecialty === 'all' || cardSpecialties.includes(selectedSpecialty));
                const matchesInsurance = (selectedInsurance === 'all' || cardInsurances.includes(selectedInsurance));

                if (matchesSpecialty && matchesInsurance) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Update visible count text
            const countText = document.getElementById('filtered-count-text');
            countText.innerText = `Showing ${visibleCount} Practitioner${visibleCount === 1 ? '' : 's'}`;

            // Show/hide empty notice
            const notice = document.getElementById('empty-filters-notice');
            if (visibleCount === 0) {
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }
        }

        // 2. BOOKING MODAL ACTIONS
        const bookingModal = document.getElementById('booking-modal');
        const modalSubtitle = document.getElementById('booking-therapist-subtitle');
        const hiddenTherapistInput = document.getElementById('modal-therapist-name');
        const bookingDateInput = document.getElementById('booking_date');
        const bookingTimeSelect = document.getElementById('booking_time');

        function openBookingModal(therapistName) {
            modalSubtitle.innerText = therapistName;
            hiddenTherapistInput.value = therapistName;
            
            // Reset modal states
            bookingDateInput.value = '';
            bookingTimeSelect.innerHTML = '<option value="">Please select a date first</option>';
            bookingTimeSelect.disabled = true;
            
            bookingModal.classList.remove('opacity-0', 'pointer-events-none');
            bookingModal.querySelector('.relative').classList.remove('scale-95');
            bookingModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeBookingModal() {
            bookingModal.classList.add('opacity-0', 'pointer-events-none');
            bookingModal.querySelector('.relative').classList.remove('scale-100');
            bookingModal.querySelector('.relative').classList.add('scale-95');
        }

        // Asynchronously fetch available slots
        function fetchAvailableSlots() {
            const therapist = hiddenTherapistInput.value;
            const date = bookingDateInput.value;
            
            if (!therapist || !date) return;
            
            bookingTimeSelect.innerHTML = '<option value="">Loading slots...</option>';
            bookingTimeSelect.disabled = true;
            
            fetch(`teletherapy_hub.php?action=get_slots&therapist=${encodeURIComponent(therapist)}&date=${encodeURIComponent(date)}`)
                .then(response => response.json())
                .then(slots => {
                    bookingTimeSelect.innerHTML = '';
                    if (slots.length === 0) {
                        bookingTimeSelect.innerHTML = '<option value="">No slots available on this day</option>';
                        bookingTimeSelect.disabled = true;
                    } else {
                        const defaultOpt = document.createElement('option');
                        defaultOpt.value = '';
                        defaultOpt.text = 'Select an available slot';
                        bookingTimeSelect.appendChild(defaultOpt);
                        
                        slots.forEach(slot => {
                            const opt = document.createElement('option');
                            opt.value = slot;
                            opt.text = slot;
                            bookingTimeSelect.appendChild(opt);
                        });
                        bookingTimeSelect.disabled = false;
                    }
                })
                .catch(err => {
                    console.error("Error fetching slots:", err);
                    bookingTimeSelect.innerHTML = '<option value="">Error loading slots</option>';
                });
        }

        // 3. Simulated Availability editor loading
        const availabilityMap = <?php
            $formatted = [];
            foreach ($current_avail as $item) {
                $formatted[$item['therapist_name']][] = $item['day_of_week'] . '|' . $item['time_slot'];
            }
            echo json_encode($formatted);
        ?>;

        function loadEditorAvailability() {
            const selectedTherapist = document.getElementById('editor-therapist').value;
            const activeSlots = availabilityMap[selectedTherapist] || [];
            
            const checkboxes = document.querySelectorAll('.avail-chk');
            checkboxes.forEach(chk => {
                chk.checked = activeSlots.includes(chk.value);
            });
        }

        // 4. EMERGENCY MODAL ACTIONS
        const emergencyModal = document.getElementById('emergency-modal');

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

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeBookingModal();
                closeEmergencyModal();
            }
        });

        // Run on load to set up the default selected therapist checkbox states
        document.addEventListener('DOMContentLoaded', () => {
            loadEditorAvailability();
        });
    </script>
</body>
</html>
