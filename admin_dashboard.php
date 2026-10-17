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
        $name = filter_input(INPUT_POST, 'name', FILTER_DEFAULT);
        $role = filter_input(INPUT_POST, 'role', FILTER_DEFAULT);
        $archetype = filter_input(INPUT_POST, 'archetype', FILTER_DEFAULT);
        if (empty($archetype)) { $archetype = null; }
        
        $license_no = filter_input(INPUT_POST, 'license_no', FILTER_DEFAULT);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_DEFAULT);
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
    

    elseif ($action === 'add_caregiver_video') {
        $video_title = filter_input(INPUT_POST, 'title', FILTER_DEFAULT);
        $video_embed = filter_input(INPUT_POST, 'embed_url', FILTER_DEFAULT);
        
        if (!empty($video_title) && !empty($video_embed)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO caregiver_videos (title, embed_url) VALUES (?, ?)");
                    $stmt->execute([$video_title, $video_embed]);
                    $action_success = "Video added successfully.";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (isset($_SESSION['mock_caregiver_videos'])) {
                $new_id = empty($_SESSION['mock_caregiver_videos']) ? 1 : max(array_column($_SESSION['mock_caregiver_videos'], 'id')) + 1;
                $_SESSION['mock_caregiver_videos'][] = [
                    'id' => $new_id,
                    'title' => $video_title,
                    'embed_url' => $video_embed
                ];
                $action_success = "Video added successfully (Session Mock).";
            }
        } else {
            $action_error = "Title and Embed URL are required.";
        }
    }

    elseif ($action === 'update_api_settings') {
        $new_key = filter_input(INPUT_POST, 'gemini_api_key', FILTER_DEFAULT);
        $settings_file = __DIR__ . '/storage/settings.json';
        $settings = [];
        if (file_exists($settings_file)) {
            $settings = json_decode(file_get_contents($settings_file), true) ?: [];
        }
        $settings['gemini_api_key'] = $new_key;
        if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT))) {
            $action_success = "API Settings updated successfully.";
        } else {
            $action_error = "Failed to write settings to storage/settings.json.";
        }
    }

    elseif ($action === 'delete_caregiver_video' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM caregiver_videos WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "Video deleted successfully.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_caregiver_videos'])) {
            foreach ($_SESSION['mock_caregiver_videos'] as $index => $vid) {
                if ($vid['id'] == $target_id) {
                    unset($_SESSION['mock_caregiver_videos'][$index]);
                    $_SESSION['mock_caregiver_videos'] = array_values($_SESSION['mock_caregiver_videos']);
                    $action_success = "Video deleted successfully (Session Mock).";
                    break;
                }
            }
        }
    }

    // Caregiver Audio Direct Upload & URL Manager
    elseif ($action === 'add_caregiver_audio') {
        $audio_title = trim($_POST['title'] ?? '');
        $audio_desc = trim($_POST['description'] ?? '');
        $audio_duration = trim($_POST['duration'] ?? '05:00');
        $audio_archetype = trim($_POST['archetype'] ?? 'all');
        $audio_url = trim($_POST['audio_url'] ?? '');

        // Handle direct audio file upload if present
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/audio/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_tmp = $_FILES['audio_file']['tmp_name'];
            $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['audio_file']['name']));
            $target_file = $upload_dir . time() . '_' . $file_name;
            $allowed_exts = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_exts)) {
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $audio_url = 'uploads/audio/' . basename($target_file);
                } else {
                    $action_error = "Failed to upload audio file. Check directory permissions.";
                }
            } else {
                $action_error = "Invalid audio format. Allowed formats: " . implode(', ', $allowed_exts);
            }
        }

        if (empty($action_error)) {
            if (!empty($audio_title) && !empty($audio_url)) {
                if ($db_connected && $pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO caregiver_audio (title, description, duration, audio_url, archetype) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$audio_title, $audio_desc, $audio_duration, $audio_url, $audio_archetype]);
                        $action_success = "Caregiver audio track published successfully!";
                    } catch (PDOException $e) {
                        $action_error = "Database error: " . $e->getMessage();
                    }
                }
                if (!isset($_SESSION['mock_caregiver_audio'])) {
                    $_SESSION['mock_caregiver_audio'] = [];
                }
                $new_id = empty($_SESSION['mock_caregiver_audio']) ? 1 : max(array_column($_SESSION['mock_caregiver_audio'], 'id')) + 1;
                $_SESSION['mock_caregiver_audio'][] = [
                    'id' => $new_id,
                    'title' => $audio_title,
                    'description' => $audio_desc,
                    'duration' => $audio_duration,
                    'audio_url' => $audio_url,
                    'archetype' => $audio_archetype
                ];
                $action_success = "Caregiver audio track published successfully (Session Mock synced)!";
            } else {
                $action_error = "Audio Title and either an audio file upload or audio URL are required.";
            }
        }
    }

    elseif ($action === 'delete_caregiver_audio' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM caregiver_audio WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_caregiver_audio'])) {
            foreach ($_SESSION['mock_caregiver_audio'] as $idx => $a) {
                if ($a['id'] == $target_id) {
                    unset($_SESSION['mock_caregiver_audio'][$idx]);
                    $_SESSION['mock_caregiver_audio'] = array_values($_SESSION['mock_caregiver_audio']);
                    break;
                }
            }
        }
        $action_success = "Caregiver audio track removed.";
    }

    // Learning Center: Academy Lesson Manager (YouTube Links)
    elseif ($action === 'add_academy_lesson') {
        $lesson_title = trim($_POST['title'] ?? '');
        $lesson_cat = trim($_POST['category'] ?? 'Anxiety');
        $lesson_desc = trim($_POST['description'] ?? '');
        $lesson_duration = trim($_POST['duration'] ?? '5 mins');
        $raw_youtube = trim($_POST['youtube_url'] ?? '');
        $lesson_icon = trim($_POST['icon_name'] ?? 'book-open');

        // Normalize YouTube link to standard embed URL
        $embed_url = $raw_youtube;
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $raw_youtube, $matches)) {
            $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (!empty($lesson_title) && !empty($embed_url)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO academy_lessons (category, title, description, duration, youtube_url, icon_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$lesson_cat, $lesson_title, $lesson_desc, $lesson_duration, $embed_url, $lesson_icon]);
                    $action_success = "TET Academy video lesson published successfully!";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (!isset($_SESSION['mock_academy_lessons'])) {
                $_SESSION['mock_academy_lessons'] = [];
            }
            $new_id = empty($_SESSION['mock_academy_lessons']) ? 1 : max(array_column($_SESSION['mock_academy_lessons'], 'id')) + 1;
            $_SESSION['mock_academy_lessons'][] = [
                'id' => $new_id,
                'category' => $lesson_cat,
                'title' => $lesson_title,
                'description' => $lesson_desc,
                'duration' => $lesson_duration,
                'youtube_url' => $embed_url,
                'icon_name' => $lesson_icon,
                'sort_order' => $new_id
            ];
            $action_success = "TET Academy video lesson published successfully (Session Mock synced)!";
        } else {
            $action_error = "Lesson title and valid YouTube URL are required.";
        }
    }

    elseif ($action === 'edit_academy_lesson' && $target_id) {
        $lesson_title = trim($_POST['title'] ?? '');
        $lesson_cat = trim($_POST['category'] ?? 'Anxiety');
        $lesson_desc = trim($_POST['description'] ?? '');
        $lesson_duration = trim($_POST['duration'] ?? '');
        $raw_youtube = trim($_POST['youtube_url'] ?? '');

        // Normalize YouTube link to standard embed URL
        $embed_url = $raw_youtube;
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $raw_youtube, $matches)) {
            $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
        }

        if (!empty($lesson_title) && !empty($embed_url)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE academy_lessons SET category = ?, title = ?, description = ?, duration = ?, youtube_url = ? WHERE id = ?");
                    $stmt->execute([$lesson_cat, $lesson_title, $lesson_desc, $lesson_duration, $embed_url, $target_id]);
                    $action_success = "TET Academy lesson updated successfully!";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (isset($_SESSION['mock_academy_lessons'])) {
                foreach ($_SESSION['mock_academy_lessons'] as &$l) {
                    if ($l['id'] == $target_id) {
                        $l['category'] = $lesson_cat;
                        $l['title'] = $lesson_title;
                        $l['description'] = $lesson_desc;
                        $l['duration'] = $lesson_duration;
                        $l['youtube_url'] = $embed_url;
                        break;
                    }
                }
            }
            $action_success = "TET Academy lesson updated successfully!";
        } else {
            $action_error = "Lesson title and valid YouTube URL are required.";
        }
    }

    elseif ($action === 'delete_academy_lesson' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM academy_lessons WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_academy_lessons'])) {
            foreach ($_SESSION['mock_academy_lessons'] as $idx => $l) {
                if ($l['id'] == $target_id) {
                    unset($_SESSION['mock_academy_lessons'][$idx]);
                    $_SESSION['mock_academy_lessons'] = array_values($_SESSION['mock_academy_lessons']);
                    break;
                }
            }
        }
        $action_success = "Academy lesson removed.";
    }

    // Step 8: Media Upload & External Linking Handler
    elseif ($action === 'add_streaming_media') {
        $media_title = trim($_POST['title'] ?? '');
        $media_desc = trim($_POST['description'] ?? '');
        $media_cat = intval($_POST['category_id'] ?? 1);
        $media_url = trim($_POST['media_url'] ?? '');
        $media_duration = trim($_POST['duration'] ?? '10 Min');
        $media_tags = trim($_POST['tags'] ?? 'Audio');
        
        if (!empty($media_title) && !empty($media_url)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO streaming_content (title, description, category_id, media_url, duration, tags, plays_count) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$media_title, $media_desc, $media_cat, $media_url, $media_duration, $media_tags]);
                    $action_success = "Media content published successfully to Streaming Hub!";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (!isset($_SESSION['mock_streaming_content'])) {
                $_SESSION['mock_streaming_content'] = [];
            }
            $new_id = count($_SESSION['mock_streaming_content']) + 1;
            $_SESSION['mock_streaming_content'][] = [
                'id' => $new_id,
                'title' => $media_title,
                'description' => $media_desc,
                'category_id' => $media_cat,
                'media_url' => $media_url,
                'duration' => $media_duration,
                'tags' => $media_tags,
                'plays_count' => 0
            ];
            $action_success = "Media content published successfully to Streaming Hub!";
        } else {
            $action_error = "Title and Media URL are required.";
        }
    }

    elseif ($action === 'delete_streaming_media' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM streaming_content WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_streaming_content'])) {
            foreach ($_SESSION['mock_streaming_content'] as $idx => $m) {
                if ($m['id'] == $target_id) {
                    unset($_SESSION['mock_streaming_content'][$idx]);
                    $_SESSION['mock_streaming_content'] = array_values($_SESSION['mock_streaming_content']);
                    break;
                }
            }
        }
        $action_success = "Streaming media item removed.";
    }

    // Step 9: Research Study Vetting Workflow Handler
    elseif ($action === 'create_research_study') {
        $rs_title = trim($_POST['title'] ?? '');
        $rs_desc = trim($_POST['description'] ?? '');
        $rs_target = intval($_POST['target_participants'] ?? 50);
        $rs_video = trim($_POST['video_url'] ?? '');

        // Normalize YouTube embed URL
        if (!empty($rs_video)) {
            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $rs_video, $m)) {
                $rs_video = 'https://www.youtube.com/embed/' . $m[1];
            }
        }

        if (!empty($rs_title) && !empty($rs_desc)) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO research_studies (title, description, status, target_participants, video_url) VALUES (?, ?, 'active', ?, ?)");
                    $stmt->execute([$rs_title, $rs_desc, $rs_target, $rs_video ?: null]);
                    $action_success = "Clinical research trial launched & published with video briefing!";
                } catch (PDOException $e) {
                    $action_error = "Database error: " . $e->getMessage();
                }
            }
            if (!isset($_SESSION['mock_research_studies'])) {
                $_SESSION['mock_research_studies'] = [];
            }
            $new_id = empty($_SESSION['mock_research_studies']) ? 1 : max(array_column($_SESSION['mock_research_studies'], 'id')) + 1;
            $_SESSION['mock_research_studies'][] = [
                'id' => $new_id,
                'title' => $rs_title,
                'description' => $rs_desc,
                'status' => 'active',
                'target_participants' => $rs_target,
                'video_url' => $rs_video ?: null
            ];
            $action_success = "Clinical research trial launched & published with video briefing!";
        } else {
            $action_error = "Title and description are required.";
        }
    }

    elseif ($action === 'delete_research_study' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM research_studies WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_research_studies'])) {
            foreach ($_SESSION['mock_research_studies'] as $idx => $st) {
                if ($st['id'] == $target_id) {
                    unset($_SESSION['mock_research_studies'][$idx]);
                    $_SESSION['mock_research_studies'] = array_values($_SESSION['mock_research_studies']);
                    break;
                }
            }
        }
        $action_success = "Research study removed.";
    }

    elseif ($action === 'approve_research_study' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE research_studies SET status = 'active' WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_research_studies'])) {
            foreach ($_SESSION['mock_research_studies'] as &$st) {
                if ($st['id'] == $target_id) {
                    $st['status'] = 'active';
                    break;
                }
            }
        }
        $action_success = "Research study approved and published to Learning Center!";
    }

    elseif ($action === 'reject_research_study' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE research_studies SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$target_id]);
            } catch (PDOException $e) {}
        }
        if (isset($_SESSION['mock_research_studies'])) {
            foreach ($_SESSION['mock_research_studies'] as &$st) {
                if ($st['id'] == $target_id) {
                    $st['status'] = 'rejected';
                    break;
                }
            }
        }
        $action_success = "Research study rejected.";
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
        $commission_rate = 0.15;
        if ($db_connected && $pdo) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT b.*, u.name as specialist_name FROM teletherapy_bookings b LEFT JOIN users u ON b.therapist_id = u.id WHERE b.id = ? AND b.payment_status = 'escrow'");
                $stmt->execute([$target_id]);
                $bk = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($bk) {
                    $gross     = (float)$bk['amount_paid'];
                    $comm_amt  = round($gross * $commission_rate, 2);
                    $net_pay   = round($gross - $comm_amt, 2);
                    $t_id      = $bk['therapist_id'];
                    $t_name    = $bk['specialist_name'] ?? 'Unknown Specialist';

                    $pdo->prepare("UPDATE teletherapy_bookings SET payment_status = 'released' WHERE id = ?")->execute([$target_id]);

                    if ($t_id) {
                        // Credit only 85% net to specialist
                        $pdo->prepare("UPDATE users SET escrow_balance = GREATEST(0, escrow_balance - ?), clearance_balance = clearance_balance + ? WHERE id = ?")->execute([$gross, $net_pay, $t_id]);
                    }

                    // Log commission
                    $pdo->prepare("INSERT INTO platform_commission_logs (booking_id, specialist_id, specialist_name, gross_amount, commission_rate, commission_amount, net_payout) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$target_id, $t_id ?? 0, $t_name, $gross, $commission_rate * 100, $comm_amt, $net_pay]);

                    $pdo->commit();
                    $action_success = "Escrow released. Net £" . number_format($net_pay, 2) . " credited to specialist (15% platform fee of £" . number_format($comm_amt, 2) . " deducted).";
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
                    $gross    = (float)$bk['amount_paid'];
                    $comm_amt = round($gross * $commission_rate, 2);
                    $net_pay  = round($gross - $comm_amt, 2);
                    $bk['payment_status'] = 'released';
                    $t_id = $bk['therapist_id'];
                    $t_name = $bk['therapist_name'];
                    if ($t_id && isset($_SESSION['mock_users'])) {
                        foreach ($_SESSION['mock_users'] as $email => &$u) {
                            if ($u['id'] == $t_id) {
                                $u['escrow_balance']  = max(0, ($u['escrow_balance'] ?? 0.00) - $gross);
                                $u['clearance_balance'] = ($u['clearance_balance'] ?? 0.00) + $net_pay;
                            }
                        }
                        unset($u);
                    }
                    // Log to session mock
                    if (!isset($_SESSION['mock_platform_commission_logs'])) $_SESSION['mock_platform_commission_logs'] = [];
                    $_SESSION['mock_platform_commission_logs'][] = [
                        'id' => count($_SESSION['mock_platform_commission_logs']) + 1,
                        'booking_id' => $idx + 1,
                        'specialist_id' => $t_id ?? 0,
                        'specialist_name' => $t_name,
                        'gross_amount' => $gross,
                        'commission_rate' => 15.00,
                        'commission_amount' => $comm_amt,
                        'net_payout' => $net_pay,
                        'logged_at' => date('Y-m-d H:i:s')
                    ];
                    $action_success = "Escrow released (Session Mock). Net £" . number_format($net_pay, 2) . " credited (15% fee: £" . number_format($comm_amt, 2) . ").";
                    break;
                }
            }
            unset($bk);
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

    elseif ($action === 'update_contact_email') {
        $new_email = trim(filter_input(INPUT_POST, 'contact_inquiry_email', FILTER_VALIDATE_EMAIL) ?? '');
        if ($new_email) {
            set_platform_setting('contact_inquiry_email', $new_email);
            $action_success = "Homepage contact inquiry destination email updated successfully to: " . htmlspecialchars($new_email);
        } else {
            $action_error = "Please provide a valid email address.";
        }
    }

    elseif ($action === 'delete_contact_inquiry' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "Contact inquiry removed from history.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_contact_inquiries'])) {
            $_SESSION['mock_contact_inquiries'] = array_values(array_filter($_SESSION['mock_contact_inquiries'], fn($inq) => ($inq['id'] ?? 0) != $target_id));
            $action_success = "Contact inquiry removed from history (Session Mock).";
        }
    }

    elseif ($action === 'delete_beta_submission' && $target_id) {
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM beta_test_submissions WHERE id = ?");
                $stmt->execute([$target_id]);
                $action_success = "Beta tester submission removed.";
            } catch (PDOException $e) {
                $action_error = "Database error: " . $e->getMessage();
            }
        }
        if (isset($_SESSION['mock_beta_test_submissions'])) {
            $_SESSION['mock_beta_test_submissions'] = array_values(array_filter($_SESSION['mock_beta_test_submissions'], fn($sub) => ($sub['id'] ?? 0) != $target_id));
            $action_success = "Beta tester submission removed (Session Mock).";
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

// Fetch Commission Logs & Platform Revenue
$commission_logs = [];
$platform_revenue_total = 0.00;
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM platform_commission_logs ORDER BY logged_at DESC LIMIT 50");
        $commission_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($commission_logs as $cl) { $platform_revenue_total += (float)$cl['commission_amount']; }
    } catch (PDOException $e) {}
} else {
    $commission_logs = $_SESSION['mock_platform_commission_logs'] ?? [];
    foreach ($commission_logs as $cl) { $platform_revenue_total += (float)($cl['commission_amount'] ?? 0); }
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

// Fetch Caregiver Videos
$caregiver_videos = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM caregiver_videos ORDER BY created_at DESC");
        $caregiver_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} else {
    $caregiver_videos = $_SESSION['mock_caregiver_videos'] ?? [];
}

// Fetch Caregiver Audio Tracks
$caregiver_audio_list = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM caregiver_audio ORDER BY id DESC");
        $caregiver_audio_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($caregiver_audio_list) && isset($_SESSION['mock_caregiver_audio'])) {
    $caregiver_audio_list = $_SESSION['mock_caregiver_audio'];
}

// Fetch Academy Lessons
$academy_lessons_list = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM academy_lessons ORDER BY sort_order ASC, id ASC");
        $academy_lessons_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($academy_lessons_list) && isset($_SESSION['mock_academy_lessons'])) {
    $academy_lessons_list = $_SESSION['mock_academy_lessons'];
}

// Fetch Streaming Media Library (Step 8)
$streaming_media = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM streaming_content ORDER BY id DESC");
        $streaming_media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($streaming_media) && isset($_SESSION['mock_streaming_content'])) {
    $streaming_media = $_SESSION['mock_streaming_content'];
}

// Fetch Research Studies for Vetting Queue
$all_research_studies = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM research_studies ORDER BY id DESC");
        $all_research_studies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($all_research_studies) && isset($_SESSION['mock_research_studies'])) {
    $all_research_studies = $_SESSION['mock_research_studies'];
}

// Fetch Contact Inquiry Email setting
$contact_inquiry_email = get_platform_setting('contact_inquiry_email', 'tetwellbeinggroup@gmail.com');

// Fetch Contact Inquiries list
$contact_inquiries = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC LIMIT 50");
        $contact_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($contact_inquiries) && isset($_SESSION['mock_contact_inquiries'])) {
    $contact_inquiries = $_SESSION['mock_contact_inquiries'];
    usort($contact_inquiries, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
}

// Fetch Beta Tester Checklist Submissions
$beta_submissions = [];
if ($db_connected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM beta_test_submissions ORDER BY submitted_at DESC LIMIT 100");
        $beta_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}
if (empty($beta_submissions) && isset($_SESSION['mock_beta_test_submissions'])) {
    $beta_submissions = $_SESSION['mock_beta_test_submissions'];
    usort($beta_submissions, fn($a, $b) => strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''));
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
                    <div class="absolute right-0 mt-2 before:absolute before:-top-2 before:left-0 before:h-2 before:w-full before:content-[''] w-48 origin-top-right rounded-2xl bg-white p-2 shadow-2xl border border-gray-100 opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
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
            <div class="flex items-center gap-3">
                <button onclick="startSectionTour()" class="px-4 py-2.5 rounded-2xl bg-brand-sageLight text-brand-sage hover:bg-brand-sage hover:text-white text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 active:scale-95">
                    <i class="fa-solid fa-compass"></i>
                    <span>Show Me Around</span>
                </button>
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
                    <h3 class="text-2xl font-bold font-outfit text-brand-slate mt-0.5">£<?php echo number_format($escrow_balance_total, 2); ?></h3>
                </div>
            </div>

            <!-- Platform Commission Revenue -->
            <div class="bg-gradient-to-br from-brand-sage to-brand-sageHover rounded-3xl p-6 shadow-soft border border-brand-sage/20 flex items-center gap-5">
                <div class="h-12 w-12 rounded-2xl bg-white/20 text-white flex items-center justify-center shrink-0">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] text-white/70 font-bold uppercase tracking-wider">Platform Commission Revenue</p>
                    <h3 class="text-2xl font-bold font-outfit text-white mt-0.5">£<?php echo number_format($platform_revenue_total, 2); ?></h3>
                    <p class="text-[10px] text-white/60 mt-0.5">15% on all cleared bookings</p>
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

        <!-- COMMISSION LEDGER -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">Platform Commission Ledger</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Automated 15% platform fee deducted on each escrow release. Net 85% credited to specialist.</p>
                </div>
                <div class="flex items-center gap-2 bg-brand-sageLight rounded-2xl px-4 py-2">
                    <svg class="h-4 w-4 text-brand-sage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    <span class="text-xs font-bold text-brand-sage">Total Commission: £<?php echo number_format($platform_revenue_total, 2); ?></span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Booking #</th>
                            <th class="p-4">Specialist</th>
                            <th class="p-4">Gross Amount</th>
                            <th class="p-4">Commission (15%)</th>
                            <th class="p-4">Net Payout (85%)</th>
                            <th class="p-4 pr-6">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($commission_logs)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 font-medium">No commission records yet. Release an escrow hold to generate commission logs.</td>
                        </tr>
                        <?php else: foreach ($commission_logs as $cl): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-mono text-brand-slate">#<?php echo htmlspecialchars($cl['booking_id']); ?></td>
                            <td class="p-4 font-bold text-brand-slate"><?php echo htmlspecialchars($cl['specialist_name']); ?></td>
                            <td class="p-4 font-bold text-gray-700">£<?php echo number_format((float)$cl['gross_amount'], 2); ?></td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-brand-sageLight text-brand-sage font-bold text-[10px]">
                                    £<?php echo number_format((float)$cl['commission_amount'], 2); ?>
                                    <span class="text-brand-sage/60">(<?php echo number_format((float)$cl['commission_rate'], 0); ?>%)</span>
                                </span>
                            </td>
                            <td class="p-4 font-bold text-brand-sage">£<?php echo number_format((float)$cl['net_payout'], 2); ?></td>
                            <td class="p-4 pr-6 font-mono text-[10px] text-gray-400"><?php echo htmlspecialchars(substr($cl['logged_at'], 0, 16)); ?></td>
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

        <!-- CAREGIVER VIDEO MANAGEMENT -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold font-outfit text-brand-slate">Caregiver Video Management</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Add embedded therapeutic videos (YouTube, Vimeo) to the Caregiver Hub.</p>
                </div>
                <div class="w-full sm:w-auto">
                    <form method="POST" class="flex gap-2 items-center w-full">
                        <input type="hidden" name="action" value="add_caregiver_video">
                        <input type="text" name="title" placeholder="Video Title" required class="flex-1 w-32 px-3 py-2 text-xs rounded-xl border border-gray-200 bg-brand-inputBg focus:border-brand-sage focus:outline-none">
                        <input type="text" name="embed_url" placeholder="Embed URL (e.g., https://youtube.com/embed/...)" required class="flex-1 w-48 px-3 py-2 text-xs rounded-xl border border-gray-200 bg-brand-inputBg focus:border-brand-sage focus:outline-none">
                        <button type="submit" class="shrink-0 px-4 py-2 bg-brand-sage hover:bg-brand-sageHover text-white rounded-xl text-xs font-bold transition-colors font-outfit">
                            Add Video
                        </button>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">ID</th>
                            <th class="p-4">Title</th>
                            <th class="p-4">Embed URL</th>
                            <th class="p-4 pr-6 text-right font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($caregiver_videos)): ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500 font-medium">No videos found.</td>
                        </tr>
                        <?php else: foreach ($caregiver_videos as $cv): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-bold text-brand-slate">#<?php echo htmlspecialchars($cv['id']); ?></td>
                            <td class="p-4 text-brand-slate font-medium"><?php echo htmlspecialchars($cv['title']); ?></td>
                            <td class="p-4 font-mono text-[10px] text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($cv['embed_url']); ?></td>
                            <td class="p-4 pr-6 text-right font-outfit">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this video?');">
                                    <input type="hidden" name="action" value="delete_caregiver_video">
                                    <input type="hidden" name="target_id" value="<?php echo $cv['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral font-bold text-[10px] transition-colors font-outfit">
                                        Delete
                                    </button>
                                
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CAREGIVER DIRECT AUDIO CONTENT MANAGEMENT -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-sky/20 text-brand-slate flex items-center justify-center font-bold">
                        <i class="fa-solid fa-volume-high"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold font-outfit text-brand-slate">Caregiver Audio Content Management</h2>
                        <p class="text-xs text-gray-500">Upload audio files (.mp3, .wav, .ogg) or provide direct audio streams for the Caregiver Hub Mindfulness Player.</p>
                    </div>
                </div>
            </div>

            <!-- Upload & Publish Form -->
            <form method="POST" enctype="multipart/form-data" class="bg-brand-bg/50 p-6 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-4">
                <input type="hidden" name="action" value="add_caregiver_audio">
                
                <div class="md:col-span-4">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Track Title</label>
                    <input type="text" name="title" required placeholder="e.g. 5-Minute Caregiver Decompression" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Direct Audio File (.mp3, .wav)</label>
                    <input type="file" name="audio_file" accept=".mp3,.wav,.ogg,.m4a,.aac" class="w-full text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-sageLight file:text-brand-sage border border-gray-200 rounded-xl p-1 bg-white focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Or Audio Stream URL</label>
                    <input type="text" name="audio_url" placeholder="https://.../audio.mp3" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Duration</label>
                    <input type="text" name="duration" value="05:00" placeholder="05:00" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Target Caregiver Archetype</label>
                    <select name="archetype" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                        <option value="all">🌟 All Caregivers</option>
                        <option value="dementia_carer">🧠 Dementia & Alzheimer's Carers</option>
                        <option value="stressed_student">🎓 Stressed Students</option>
                    </select>
                </div>

                <div class="md:col-span-6">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Brief Description / Respite Prompt</label>
                    <input type="text" name="description" placeholder="Restorative breathing prompt to lower sympathetic heart rate..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl text-xs shadow-sm transition-all font-outfit">
                        Upload & Publish
                    </button>
                </div>
            </form>

            <!-- Audio Roster Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">ID</th>
                            <th class="p-4">Title</th>
                            <th class="p-4">Archetype</th>
                            <th class="p-4">Duration</th>
                            <th class="p-4">Audio Preview</th>
                            <th class="p-4 pr-6 text-right font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($caregiver_audio_list)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 font-medium">No audio files uploaded yet.</td>
                        </tr>
                        <?php else: foreach ($caregiver_audio_list as $ca): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-bold text-brand-slate">#<?php echo htmlspecialchars($ca['id']); ?></td>
                            <td class="p-4 font-bold text-brand-slate">
                                <?php echo htmlspecialchars($ca['title']); ?>
                                <p class="text-[10px] text-gray-400 font-normal"><?php echo htmlspecialchars($ca['description'] ?? ''); ?></p>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-sageLight text-brand-sage">
                                    <?php echo htmlspecialchars($ca['archetype'] ?? 'all'); ?>
                                </span>
                            </td>
                            <td class="p-4 font-mono text-[11px] text-gray-500"><?php echo htmlspecialchars($ca['duration'] ?? '05:00'); ?></td>
                            <td class="p-4">
                                <audio controls preload="none" class="h-8 w-48">
                                    <source src="<?php echo htmlspecialchars($ca['audio_url']); ?>">
                                </audio>
                            </td>
                            <td class="p-4 pr-6 text-right font-outfit">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this audio track?');">
                                    <input type="hidden" name="action" value="delete_caregiver_audio">
                                    <input type="hidden" name="target_id" value="<?php echo $ca['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral font-bold text-[10px] transition-colors font-outfit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TET ACADEMY LESSON MANAGEMENT (YOUTUBE EMBED ENGINE) -->
        <div class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-coralLight text-brand-coral flex items-center justify-center font-bold">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold font-outfit text-brand-slate">Learning Center - TET Academy Lesson Manager</h2>
                        <p class="text-xs text-gray-500">Publish YouTube video lessons to the Learning Center (TET Academy). Paste any standard YouTube link or embed URL.</p>
                    </div>
                </div>
            </div>

            <!-- Lesson Add Form -->
            <form method="POST" class="bg-brand-bg/50 p-6 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-4">
                <input type="hidden" name="action" value="add_academy_lesson">

                <div class="md:col-span-4">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Lesson Title</label>
                    <input type="text" name="title" required placeholder="e.g. Understanding Panic & Anxiety" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Module Category</label>
                    <select name="category" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                        <option value="Anxiety">Anxiety & Grounding</option>
                        <option value="Sleep">Sleep Science & Rest</option>
                        <option value="Boundaries">Boundaries & Self-Care</option>
                        <option value="Regulation">Emotional Regulation</option>
                        <option value="Trauma">Trauma & Resilience</option>
                        <option value="Grief">Grief & Adaptation</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">YouTube Link / Embed URL</label>
                    <input type="text" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=... or youtu.be/..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Duration</label>
                    <input type="text" name="duration" value="5 mins" placeholder="5 mins" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-9">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Lesson Description / Core Takeaway</label>
                    <input type="text" name="description" placeholder="What the learner will discover in this lesson..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-3 flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-brand-coral hover:bg-brand-coralHover text-white font-bold rounded-xl text-xs shadow-sm transition-all font-outfit">
                        Publish Video Lesson
                    </button>
                </div>
            </form>

            <!-- Lesson Roster Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">ID</th>
                            <th class="p-4">Category</th>
                            <th class="p-4">Lesson Title</th>
                            <th class="p-4">YouTube Embed URL</th>
                            <th class="p-4">Duration</th>
                            <th class="p-4 pr-6 text-right font-outfit">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($academy_lessons_list)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500 font-medium">No video lessons configured yet.</td>
                        </tr>
                        <?php else: foreach ($academy_lessons_list as $al): ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 font-bold text-brand-slate">#<?php echo htmlspecialchars($al['id']); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-coralLight text-brand-coral">
                                    <?php echo htmlspecialchars($al['category']); ?>
                                </span>
                            </td>
                            <td class="p-4 font-bold text-brand-slate">
                                <?php echo htmlspecialchars($al['title']); ?>
                                <p class="text-[10px] text-gray-400 font-normal truncate max-w-sm"><?php echo htmlspecialchars($al['description']); ?></p>
                            </td>
                            <td class="p-4 font-mono text-[10px] text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($al['youtube_url']); ?></td>
                            <td class="p-4 font-mono text-[11px] text-gray-500"><?php echo htmlspecialchars($al['duration']); ?></td>
                            <td class="p-4 pr-6 text-right font-outfit space-x-1 whitespace-nowrap">
                                <button type="button" onclick="openEditLessonModal(<?php echo htmlspecialchars(json_encode($al)); ?>)" class="px-2.5 py-1.5 rounded-xl bg-brand-bg hover:bg-brand-sageLight text-brand-slate hover:text-brand-sage font-bold text-[10px] transition-colors font-outfit border border-gray-200">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this lesson?');">
                                    <input type="hidden" name="action" value="delete_academy_lesson">
                                    <input type="hidden" name="target_id" value="<?php echo $al['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl hover:bg-brand-coralLight text-gray-400 hover:text-brand-coral font-bold text-[10px] transition-colors font-outfit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STEP 8: ADMIN MEDIA UPLOAD & STREAMING PLATFORM MANAGEMENT -->
        <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 mb-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-sky/20 text-brand-slate flex items-center justify-center font-bold">
                        <i class="fa-solid fa-photo-film"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold font-outfit text-brand-slate">Media & Streaming Hub Content Management</h3>
                        <p class="text-xs text-gray-500">Publish third-party video and audio content (YouTube unlisted, Vimeo, SoundCloud) into the Learning Center.</p>
                    </div>
                </div>
            </div>

            <!-- Upload / Embedding Form -->
            <form method="POST" class="bg-brand-bg/50 p-5 rounded-2xl border border-gray-100 mb-6 grid grid-cols-1 md:grid-cols-12 gap-4">
                <input type="hidden" name="action" value="add_streaming_media">
                
                <div class="md:col-span-4">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Title</label>
                    <input type="text" name="title" required placeholder="e.g. Deep Rest Sleep Soundscape" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Category</label>
                    <select name="category_id" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                        <option value="1">🧘 Guided Meditation</option>
                        <option value="2">🌙 Sleep Therapy</option>
                        <option value="3">🌱 Caregiver Wellbeing</option>
                        <option value="4">👨‍👩‍👧 Parenting De-escalation</option>
                        <option value="5">⚡ Anxiety Management</option>
                    </select>
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Duration & Tags</label>
                    <div class="flex gap-2">
                        <input type="text" name="duration" placeholder="15 Min" value="15 Min" class="w-1/2 text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                        <input type="text" name="tags" placeholder="Sleep, Audio" value="Audio" class="w-1/2 text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                    </div>
                </div>

                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl text-xs shadow-sm transition-all">
                        Publish Content
                    </button>
                </div>

                <div class="md:col-span-8">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Third-Party Embed URL / Audio Link</label>
                    <input type="text" name="media_url" required placeholder="https://www.youtube.com/embed/... or https://player.vimeo.com/video/..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Short Description</label>
                    <input type="text" name="description" placeholder="Brief summary of the session..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>
            </form>

            <!-- Media Library Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-3 pl-4">Title</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Duration</th>
                            <th class="p-3">Plays</th>
                            <th class="p-3 pr-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($streaming_media)): ?>
                        <tr><td colspan="5" class="p-6 text-center text-gray-400">No streaming media found.</td></tr>
                        <?php else: foreach ($streaming_media as $sm): ?>
                        <tr class="hover:bg-brand-bg/30">
                            <td class="p-3 pl-4 font-bold text-brand-slate">
                                <?php echo htmlspecialchars($sm['title']); ?>
                                <span class="block font-normal text-[10px] text-gray-400 truncate max-w-xs font-mono"><?php echo htmlspecialchars($sm['media_url'] ?? 'Audio Synthesizer'); ?></span>
                            </td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded-full bg-brand-sageLight text-brand-sage text-[10px] font-bold">Category #<?php echo htmlspecialchars($sm['category_id'] ?? 1); ?></span></td>
                            <td class="p-3 text-gray-500 font-medium"><?php echo htmlspecialchars($sm['duration'] ?? '10 Min'); ?></td>
                            <td class="p-3 font-semibold text-brand-slate"><?php echo intval($sm['plays_count'] ?? 0); ?></td>
                            <td class="p-3 pr-4 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this media item?');">
                                    <input type="hidden" name="action" value="delete_streaming_media">
                                    <input type="hidden" name="target_id" value="<?php echo $sm['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1 text-brand-coral hover:bg-brand-coralLight rounded-lg text-xs font-bold transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STEP 9: RESEARCH STUDY SUBMISSION & VETTING WORKFLOW -->
        <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 mb-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold">
                        <i class="fa-solid fa-microscope"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold font-outfit text-brand-slate">Clinical Research Study Management & Vetting Queue</h3>
                        <p class="text-xs text-gray-500">Launch clinical trials with YouTube video briefings and review studies before publishing to the Research Hub.</p>
                    </div>
                </div>
            </div>

            <!-- Launch Study Form -->
            <form method="POST" class="bg-brand-bg/50 p-5 rounded-2xl border border-gray-100 mb-6 grid grid-cols-1 md:grid-cols-12 gap-4">
                <input type="hidden" name="action" value="create_research_study">

                <div class="md:col-span-5">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Study Title</label>
                    <input type="text" name="title" required placeholder="e.g. Caregiver Burnout Somatic Efficacy" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Target Participants</label>
                    <input type="number" name="target_participants" min="5" value="50" required class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="md:col-span-4 flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-brand-sage hover:bg-brand-sageHover text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-plus text-xs"></i> Launch & Publish Study
                    </button>
                </div>

                <div class="md:col-span-6">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">YouTube Video Briefing URL (Optional)</label>
                    <input type="text" name="video_url" placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                </div>

                <div class="md:col-span-6">
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Clinical Scope & Description</label>
                    <input type="text" name="description" required placeholder="Detail the trial parameters, target demographics, and hypotheses..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-3 pl-4">Study Title</th>
                            <th class="p-3">Video Briefing</th>
                            <th class="p-3">Target Participants</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 pr-4 text-right">Vetting Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($all_research_studies)): ?>
                        <tr><td colspan="5" class="p-6 text-center text-gray-400">No research studies found.</td></tr>
                        <?php else: foreach ($all_research_studies as $rs): 
                            $status = $rs['status'] ?? 'active';
                            $badgeClass = ($status === 'active' || $status === 'approved') ? 'bg-brand-sageLight text-brand-sage' : (($status === 'rejected') ? 'bg-brand-coralLight text-brand-coral' : 'bg-amber-100 text-amber-800');
                            $has_video = !empty($rs['video_url']);
                        ?>
                        <tr class="hover:bg-brand-bg/30">
                            <td class="p-3 pl-4 font-bold text-brand-slate">
                                <?php echo htmlspecialchars($rs['title']); ?>
                                <span class="block font-normal text-[11px] text-gray-500 mt-0.5"><?php echo htmlspecialchars($rs['description']); ?></span>
                            </td>
                            <td class="p-3">
                                <?php if ($has_video): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 font-semibold text-[10px]">
                                        <i class="fa-brands fa-youtube text-red-600"></i> Video Briefing
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-[10px]">No video</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-gray-600 font-semibold"><?php echo intval($rs['target_participants'] ?? 100); ?> Participants</td>
                            <td class="p-3">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="p-3 pr-4 text-right space-x-1 whitespace-nowrap">
                                <?php if ($status !== 'active' && $status !== 'approved'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="approve_research_study">
                                    <input type="hidden" name="target_id" value="<?php echo $rs['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-brand-sage hover:bg-brand-sageHover text-white text-xs font-bold transition-all shadow-sm">
                                        Approve
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($status !== 'rejected'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="reject_research_study">
                                    <input type="hidden" name="target_id" value="<?php echo $rs['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-brand-coralLight text-gray-500 hover:text-brand-coral text-xs font-semibold transition-all">
                                        Reject
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this research trial permanently?');">
                                    <input type="hidden" name="action" value="delete_research_study">
                                    <input type="hidden" name="target_id" value="<?php echo $rs['id']; ?>">
                                    <button type="submit" class="px-2 py-1 text-brand-coral hover:bg-brand-coralLight rounded-lg text-xs font-bold transition-colors">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HOMEPAGE CONTACT INQUIRIES & EMAIL CONFIGURATION -->
        <div class="bg-white rounded-3xl p-6 shadow-soft border border-gray-100 mb-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold font-outfit text-brand-slate">Homepage Inquiries & Contact Routing</h3>
                            <span class="px-2 py-0.5 rounded-full bg-brand-sageLight text-brand-sage text-[10px] font-bold uppercase tracking-wider">Active</span>
                        </div>
                        <p class="text-xs text-gray-500">Configure which email receives questions submitted on the homepage ("Have Questions? Reach Out") and manage incoming inquiries.</p>
                    </div>
                </div>
            </div>

            <!-- Email Destination Setting Box -->
            <div class="bg-brand-bg/60 p-5 rounded-2xl border border-gray-100 mb-8">
                <form method="POST" class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <input type="hidden" name="action" value="update_contact_email">
                    <div class="flex-grow">
                        <label class="block text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider mb-1.5">
                            Destination Email Address for Homepage Questions
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-at text-xs"></i>
                            </div>
                            <input type="email" name="contact_inquiry_email" required value="<?php echo htmlspecialchars($contact_inquiry_email); ?>" placeholder="e.g. tetwellbeinggroup@gmail.com" class="w-full pl-9 pr-4 py-3 rounded-xl border border-gray-200 bg-white text-xs font-medium text-brand-slate focus:border-brand-sage focus:ring-2 focus:ring-brand-sage/10 outline-none transition-all">
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Default: <span class="font-mono text-brand-slate font-bold">tetwellbeinggroup@gmail.com</span>. Any inquiries sent via the homepage will be dispatched immediately to this address.</p>
                    </div>
                    <button type="submit" class="px-6 py-3 rounded-xl bg-brand-sage hover:bg-brand-sageHover text-white font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-2 shrink-0">
                        <i class="fa-solid fa-floppy-disk"></i> Save Destination Email
                    </button>
                </form>
            </div>

            <!-- Inquiries History Table -->
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold font-outfit text-brand-slate uppercase tracking-wider">
                    Recent Questions Received (<?php echo count($contact_inquiries); ?>)
                </h4>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-gray-100">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-3.5 pl-4">Sender</th>
                            <th class="p-3.5">Message / Question</th>
                            <th class="p-3.5">Dispatched To</th>
                            <th class="p-3.5">Date</th>
                            <th class="p-3.5 pr-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($contact_inquiries)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-400">
                                <i class="fa-regular fa-envelope text-3xl mb-2 text-gray-300 block"></i>
                                No inquiries received yet. When visitors ask questions on the homepage, they will appear here and be emailed to <strong class="text-brand-slate"><?php echo htmlspecialchars($contact_inquiry_email); ?></strong>.
                            </td>
                        </tr>
                        <?php else: foreach ($contact_inquiries as $inq): ?>
                        <tr class="hover:bg-brand-bg/30 transition-colors">
                            <td class="p-3.5 pl-4 align-top">
                                <span class="font-bold text-brand-slate block"><?php echo htmlspecialchars($inq['name']); ?></span>
                                <a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>" class="text-[11px] text-brand-sage hover:underline block mt-0.5">
                                    <?php echo htmlspecialchars($inq['email']); ?>
                                </a>
                            </td>
                            <td class="p-3.5 align-top max-w-md">
                                <div class="text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 text-xs leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($inq['message'])); ?>
                                </div>
                            </td>
                            <td class="p-3.5 align-top">
                                <span class="px-2 py-0.5 rounded-md bg-white border border-gray-200 text-[10px] font-mono text-gray-600 block w-max">
                                    <?php echo htmlspecialchars($inq['recipient_email'] ?? $contact_inquiry_email); ?>
                                </span>
                            </td>
                            <td class="p-3.5 align-top text-gray-500 font-medium whitespace-nowrap text-[11px]">
                                <?php echo date('M j, Y g:i a', strtotime($inq['created_at'] ?? 'now')); ?>
                            </td>
                            <td class="p-3.5 pr-4 align-top text-right whitespace-nowrap space-x-2">
                                <a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>?subject=Re: Your Question to Tet Wellbeing Group" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-sageLight text-brand-sage hover:bg-brand-sage hover:text-white text-xs font-bold transition-colors shadow-sm">
                                    <i class="fa-solid fa-reply text-[10px]"></i> Reply
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this inquiry from history?');">
                                    <input type="hidden" name="action" value="delete_contact_inquiry">
                                    <input type="hidden" name="target_id" value="<?php echo $inq['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg text-gray-400 hover:text-brand-coral hover:bg-brand-coralLight text-xs font-semibold transition-colors">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
        <!-- BETA TESTER CHECKLIST SUBMISSIONS & JUDGMENT REPOSITORIES -->
        <div id="beta-tester-records" class="bg-white rounded-3xl shadow-card border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-brand-sageLight text-brand-sage flex items-center justify-center font-bold">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold font-outfit text-brand-slate">Beta Tester Checklist Submissions & Judgments</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-sage text-white">
                                <?php echo count($beta_submissions); ?> Submissions
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Live records of testing evaluations, checklist item verifications, and qualitative reviews submitted across all LinkedIn tester categories.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-brand-bg text-[10px] font-bold text-brand-slate uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4 pl-6">Tester & Role</th>
                            <th class="p-4">Category Tested</th>
                            <th class="p-4">Checklist Score</th>
                            <th class="p-4">Rating & Recommend</th>
                            <th class="p-4">Tester Judgment & Notes</th>
                            <th class="p-4">Date</th>
                            <th class="p-4 pr-6 text-right font-outfit">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <?php if (empty($beta_submissions)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500 font-medium">No beta tester checklist submissions recorded yet.</td>
                        </tr>
                        <?php else: foreach ($beta_submissions as $bs): 
                            $checks = is_string($bs['completed_checklist'] ?? '') ? json_decode($bs['completed_checklist'], true) : ($bs['completed_checklist'] ?? []);
                            $rating = intval($bs['overall_rating'] ?? 5);
                            $rec = $bs['recommendation'] ?? 'yes';
                        ?>
                        <tr class="hover:bg-brand-bg/40 transition-colors">
                            <td class="p-4 pl-6 align-top">
                                <span class="font-bold text-brand-slate block"><?php echo htmlspecialchars($bs['tester_name']); ?></span>
                                <a href="mailto:<?php echo htmlspecialchars($bs['tester_email']); ?>" class="text-[11px] text-brand-sage hover:underline block mt-0.5 font-mono">
                                    <?php echo htmlspecialchars($bs['tester_email']); ?>
                                </a>
                            </td>
                            <td class="p-4 align-top">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-brand-coralLight text-brand-coral block w-max max-w-xs truncate">
                                    <?php echo htmlspecialchars($bs['category_title']); ?>
                                </span>
                            </td>
                            <td class="p-4 align-top whitespace-nowrap">
                                <div class="flex items-center gap-1.5 font-bold text-brand-slate">
                                    <i class="fa-solid fa-square-check text-brand-sage"></i>
                                    <span><?php echo (int)($bs['checked_count'] ?? 0); ?> / <?php echo (int)($bs['total_items'] ?? 5); ?></span>
                                </div>
                                <span class="text-[10px] text-gray-400 block mt-0.5">Verified Tasks</span>
                            </td>
                            <td class="p-4 align-top whitespace-nowrap">
                                <div class="flex items-center text-amber-400 text-xs">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-solid fa-star <?php echo $s <= $rating ? 'text-amber-400' : 'text-gray-200'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="ml-1 font-bold text-brand-slate text-xs"><?php echo $rating; ?>/5</span>
                                </div>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold <?php echo $rec === 'yes' ? 'bg-emerald-50 text-emerald-700' : ($rec === 'maybe' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'); ?>">
                                    <?php echo $rec === 'yes' ? '👍 Recommends' : ($rec === 'maybe' ? '🤔 Potential' : '👎 Needs work'); ?>
                                </span>
                            </td>
                            <td class="p-4 align-top max-w-sm">
                                <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 text-xs leading-relaxed text-gray-700 whitespace-normal">
                                    "<?php echo htmlspecialchars($bs['feedback'] ?? 'No written comment'); ?>"
                                </div>
                                <?php if (!empty($checks) && is_array($checks)): ?>
                                <details class="mt-2 text-[11px] text-gray-500 cursor-pointer">
                                    <summary class="font-bold text-brand-slate hover:text-brand-sage select-none">
                                        View Checked Items (<?php echo count($checks); ?>)
                                    </summary>
                                    <ul class="list-disc list-inside mt-1.5 space-y-1 bg-white p-2 rounded-lg border border-gray-100 text-[10px] text-gray-600">
                                        <?php foreach ($checks as $item_text => $val): ?>
                                        <li class="truncate"><?php echo htmlspecialchars(is_string($val) && strlen($val) > 1 ? $val : $item_text); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 align-top text-gray-500 font-mono text-[10px] whitespace-nowrap">
                                <?php echo date('M j, Y H:i', strtotime($bs['submitted_at'] ?? 'now')); ?>
                            </td>
                            <td class="p-4 pr-6 align-top text-right whitespace-nowrap">
                                <form method="POST" class="inline" onsubmit="return confirm('Remove this beta tester submission?');">
                                    <input type="hidden" name="action" value="delete_beta_submission">
                                    <input type="hidden" name="target_id" value="<?php echo $bs['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg text-gray-400 hover:text-brand-coral hover:bg-brand-coralLight text-xs font-semibold transition-colors" title="Delete record">
                                        <i class="fa-solid fa-trash-can"></i>
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

    <!-- EDIT ACADEMY LESSON MODAL -->
    <div id="edit-lesson-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-slate/60 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-card border border-gray-100 relative transform scale-95 transition-transform duration-300">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="h-9 w-9 rounded-xl bg-brand-coralLight text-brand-coral flex items-center justify-center font-bold">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base font-outfit text-brand-slate">Edit Academy Lesson</h3>
                        <p class="text-xs text-gray-400">Update YouTube video link, title, duration, or description</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditLessonModal()" class="h-8 w-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_academy_lesson">
                <input type="hidden" name="target_id" id="edit-lesson-id">

                <div>
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Lesson Title</label>
                    <input type="text" name="title" id="edit-lesson-title" required class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Category</label>
                        <select name="category" id="edit-lesson-category" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                            <option value="Anxiety">Anxiety & Grounding</option>
                            <option value="Sleep">Sleep Science & Rest</option>
                            <option value="Boundaries">Boundaries & Self-Care</option>
                            <option value="Regulation">Emotional Regulation</option>
                            <option value="Trauma">Trauma & Resilience</option>
                            <option value="Grief">Grief & Adaptation</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Duration</label>
                        <input type="text" name="duration" id="edit-lesson-duration" placeholder="e.g. 15:15 or 15 mins" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">YouTube Link / Embed URL</label>
                    <input type="text" name="youtube_url" id="edit-lesson-youtube" required placeholder="https://www.youtube.com/watch?v=..." class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none font-mono">
                    <p class="text-[10px] text-gray-400 mt-1">Paste standard youtube.com/watch?v=... or youtu.be/... link.</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-brand-slate uppercase mb-1">Description / Core Takeaway</label>
                    <textarea name="description" id="edit-lesson-desc" rows="3" class="w-full text-xs rounded-xl border border-gray-200 p-2.5 bg-white focus:border-brand-sage focus:outline-none resize-none"></textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" onclick="closeEditLessonModal()" class="w-1/2 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold transition-colors text-center font-outfit text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 rounded-2xl bg-brand-coral hover:bg-brand-coralHover text-white font-bold shadow-md transition-colors text-center font-outfit text-xs">
                        Update Lesson
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS SCRIPTS -->
    <script>
        const editLessonModal = document.getElementById('edit-lesson-modal');
        function openEditLessonModal(lesson) {
            document.getElementById('edit-lesson-id').value = lesson.id;
            document.getElementById('edit-lesson-title').value = lesson.title || '';
            document.getElementById('edit-lesson-category').value = lesson.category || 'Anxiety';
            document.getElementById('edit-lesson-duration').value = lesson.duration || '';
            document.getElementById('edit-lesson-youtube').value = lesson.youtube_url || '';
            document.getElementById('edit-lesson-desc').value = lesson.description || '';

            editLessonModal.classList.remove('opacity-0', 'pointer-events-none');
            editLessonModal.querySelector('.relative').classList.remove('scale-95');
            editLessonModal.querySelector('.relative').classList.add('scale-100');
        }

        function closeEditLessonModal() {
            editLessonModal.classList.add('opacity-0', 'pointer-events-none');
            editLessonModal.querySelector('.relative').classList.remove('scale-100');
            editLessonModal.querySelector('.relative').classList.add('scale-95');
        }
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
    <?php require_once __DIR__ . '/includes/section_guide.php'; ?>
</body>
</html>
