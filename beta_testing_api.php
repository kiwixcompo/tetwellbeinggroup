<?php
/**
 * Tet Wellbeing Group - Beta Tester Module Backend Handler (beta_testing_api.php)
 * Handles AJAX requests for:
 * 1. Setting/clearing active beta testing category in session
 * 2. Tracking visited required pages
 * 3. Submitting completed checklist judgments to database & session
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Beta Tester';
$user_email = $_SESSION['user_email'] ?? 'tester@tetwellbeing.com';

// 1. Select / Activate Category
if ($action === 'select_category') {
    $category_id = trim($_POST['category_id'] ?? '');
    $category_title = trim($_POST['category_title'] ?? '');

    if (empty($category_id)) {
        echo json_encode(['success' => false, 'error' => 'Category ID is required']);
        exit;
    }

    $_SESSION['beta_tester'] = [
        'active' => true,
        'category_id' => $category_id,
        'category_title' => $category_title,
        'started_at' => date('Y-m-d H:i:s'),
        'visited_pages' => [],
        'dismissed_modal' => true
    ];

    $category_default_pages = [
        'clinician' => 'teletherapy_hub.php',
        'hr_leader' => 'corporate_portal.php',
        'caregiver' => 'caregiver_hub.php',
        'peer_volunteer' => 'befriender_network.php',
        'stressed_pro' => 'dashboard.php',
        'full_explorer' => 'dashboard.php'
    ];
    $redirect_url = $category_default_pages[$category_id] ?? 'dashboard.php';

    echo json_encode([
        'success' => true,
        'category_id' => $category_id,
        'category_title' => $category_title,
        'redirect_url' => $redirect_url
    ]);
    exit;
}

// 2. Log Page Visit
elseif ($action === 'log_visit') {
    $page = trim($_POST['page'] ?? '');
    if (!isset($_SESSION['beta_tester']) || empty($_SESSION['beta_tester']['active'])) {
        echo json_encode(['success' => false, 'error' => 'Beta testing mode inactive']);
        exit;
    }

    if ($page && !in_array($page, $_SESSION['beta_tester']['visited_pages'])) {
        $_SESSION['beta_tester']['visited_pages'][] = $page;
    }

    echo json_encode([
        'success' => true,
        'visited_pages' => $_SESSION['beta_tester']['visited_pages']
    ]);
    exit;
}

// 3. Submit Checklist Feedback
elseif ($action === 'submit_checklist') {
    $category_id = trim($_POST['category_id'] ?? ($_SESSION['beta_tester']['category_id'] ?? 'general'));
    $category_title = trim($_POST['category_title'] ?? ($_SESSION['beta_tester']['category_title'] ?? 'Beta Tester'));
    $tester_name = trim($_POST['tester_name'] ?? $user_name);
    $tester_email = trim($_POST['tester_email'] ?? $user_email);
    $rating = intval($_POST['overall_rating'] ?? 5);
    $feedback = trim($_POST['feedback'] ?? '');
    $recommendation = trim($_POST['recommendation'] ?? 'yes');
    if (!in_array($recommendation, ['yes', 'maybe', 'no'])) {
        $recommendation = 'yes';
    }

    // Decode or parse checklist items
    $raw_checklist = $_POST['checklist'] ?? [];
    if (is_string($raw_checklist)) {
        $checklist_items = json_decode($raw_checklist, true) ?: [];
    } else {
        $checklist_items = (array)$raw_checklist;
    }

    $checked_count = 0;
    foreach ($checklist_items as $k => $v) {
        if (!empty($v)) $checked_count++;
    }
    $total_items = count($checklist_items);

    $json_checklist = json_encode($checklist_items);

    // Save to DB if connected
    $new_id = null;
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO beta_test_submissions (user_id, tester_name, tester_email, category_id, category_title, overall_rating, completed_checklist, checked_count, total_items, feedback, recommendation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $tester_name,
                $tester_email,
                $category_id,
                $category_title,
                $rating,
                $json_checklist,
                $checked_count,
                $total_items,
                $feedback,
                $recommendation
            ]);
            $new_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            // DB fallback
        }
    }

    // Save to Session mock
    if (!isset($_SESSION['mock_beta_test_submissions'])) {
        $_SESSION['mock_beta_test_submissions'] = [];
    }
    if (!$new_id) {
        $new_id = count($_SESSION['mock_beta_test_submissions']) + 1;
    }

    $_SESSION['mock_beta_test_submissions'][] = [
        'id' => (int)$new_id,
        'user_id' => $user_id,
        'tester_name' => $tester_name,
        'tester_email' => $tester_email,
        'category_id' => $category_id,
        'category_title' => $category_title,
        'overall_rating' => $rating,
        'completed_checklist' => $checklist_items,
        'checked_count' => $checked_count,
        'total_items' => $total_items,
        'feedback' => $feedback,
        'recommendation' => $recommendation,
        'submitted_at' => date('Y-m-d H:i:s')
    ];

    // Mark tester session as submitted/completed
    if (isset($_SESSION['beta_tester'])) {
        $_SESSION['beta_tester']['submitted'] = true;
        $_SESSION['beta_tester']['submission_id'] = $new_id;
    }

    echo json_encode([
        'success' => true,
        'submission_id' => $new_id,
        'message' => 'Thank you! Your beta testing review has been recorded successfully.'
    ]);
    exit;
}

// 4. Reset Tester Session (allows switching or retesting)
elseif ($action === 'reset_tester') {
    unset($_SESSION['beta_tester']);
    echo json_encode(['success' => true]);
    exit;
}

// Default state check
else {
    $tester_data = $_SESSION['beta_tester'] ?? null;
    echo json_encode([
        'success' => true,
        'active' => !empty($tester_data['active']),
        'tester' => $tester_data
    ]);
    exit;
}
