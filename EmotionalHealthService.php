<?php
/**
 * Tet Wellbeing Group - Emotional Health Index & Safety Service (EmotionalHealthService.php)
 * Dynamic mood analytics, clinical safety triage net (RegEx), and trend analysis queries.
 */

class EmotionalHealthService {
    
    private static $mood_values = [
        'terrible' => 20,
        'bad' => 40,
        'okay' => 60,
        'good' => 80,
        'great' => 100
    ];

    /**
     * Calculate Emotional Health Index (0-100)
     */
    public static function calculateIndex($userId) {
        global $db_connected, $pdo;
        
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $mood_scores = [];
        
        $checkins_count = 0;
        $respite_count = 0;
        $posts_count = 0;
        $burnout_count = 0;

        // 1. FETCH FROM MYSQL IF CONNECTED
        if ($db_connected && $pdo) {
            try {
                // Fetch checkins
                $stmt = $pdo->prepare("SELECT mood FROM daily_checkins WHERE user_id = ? AND created_at >= ?");
                $stmt->execute([$userId, $seven_days_ago]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $m) {
                    if (isset(self::$mood_values[strtolower($m)])) {
                        $mood_scores[] = self::$mood_values[strtolower($m)];
                    }
                }
                $checkins_count = count($mood_scores);

                // Fetch caregiver breaks
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM caregiver_respite_breaks WHERE user_id = ? AND created_at >= ?");
                $stmt->execute([$userId, $seven_days_ago]);
                $respite_count = (int)$stmt->fetchColumn();

                // Fetch community posts
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ? AND created_at >= ?");
                $stmt->execute([$userId, $seven_days_ago]);
                $posts_count = (int)$stmt->fetchColumn();

                // Fetch burnout tests
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM caregiver_burnout_logs WHERE user_id = ? AND created_at >= ?");
                $stmt->execute([$userId, $seven_days_ago]);
                $burnout_count = (int)$stmt->fetchColumn();

            } catch (PDOException $ex) {
                // Fall back to session
            }
        }

        // 2. FALLBACK TO SESSION MOCK STORAGE
        if (!$db_connected || empty($mood_scores)) {
            // Checkins fallback
            if (isset($_SESSION['mock_checkins'])) {
                foreach ($_SESSION['mock_checkins'] as $c) {
                    if ($c['user_id'] == $userId && $c['created_at'] >= $seven_days_ago) {
                        $m = $c['mood'];
                        if (isset(self::$mood_values[strtolower($m)])) {
                            $mood_scores[] = self::$mood_values[strtolower($m)];
                        }
                    }
                }
                $checkins_count = count($mood_scores);
            }

            // Respite fallback
            if (isset($_SESSION['mock_respite_breaks'])) {
                foreach ($_SESSION['mock_respite_breaks'] as $r) {
                    if ($r['user_id'] == $userId && $r['created_at'] >= $seven_days_ago) {
                        $respite_count++;
                    }
                }
            }

            // Posts fallback
            if (isset($_SESSION['mock_community_posts'])) {
                foreach ($_SESSION['mock_community_posts'] as $p) {
                    if ($p['user_id'] == $userId && $p['created_at'] >= $seven_days_ago) {
                        $posts_count++;
                    }
                }
            }

            // Burnout logs fallback
            if (isset($_SESSION['mock_burnout_logs'])) {
                foreach ($_SESSION['mock_burnout_logs'] as $b) {
                    if ($b['user_id'] == $userId && $b['created_at'] >= $seven_days_ago) {
                        $burnout_count++;
                    }
                }
            }
        }

        // 3. COMPUTE AVERAGE MOOD
        if ($checkins_count > 0) {
            $average_mood = array_sum($mood_scores) / $checkins_count;
        } else {
            $average_mood = 50; // default baseline
        }

        // 4. platform ACTIVITY BONUS
        // Each check-in adds +2.5 points, respite break adds +5, community post adds +3, burnout test adds +5
        $activity_bonus = ($checkins_count * 2.5) + ($respite_count * 5) + ($posts_count * 3) + ($burnout_count * 5);
        
        $composite_score = round($average_mood * 0.8 + min(20, $activity_bonus));
        
        // Clamp between 0 and 100
        $composite_score = max(0, min(100, $composite_score));

        // Determine label
        if ($composite_score < 40) {
            $mood_label = 'Vulnerable';
            $mood_color = 'brand-coral';
        } elseif ($composite_score < 60) {
            $mood_label = 'Stressed';
            $mood_color = 'brand-sky';
        } elseif ($composite_score < 85) {
            $mood_label = 'Stable';
            $mood_color = 'brand-sage';
        } else {
            $mood_label = 'Flourishing';
            $mood_color = 'brand-sage';
        }

        return [
            'score' => $composite_score,
            'average_mood' => round($average_mood),
            'checkins_count' => $checkins_count,
            'respite_count' => $respite_count,
            'posts_count' => $posts_count,
            'burnout_count' => $burnout_count,
            'label' => $mood_label,
            'color' => $mood_color
        ];
    }

    /**
     * Sprint 2.2 - Clinical Safety Triage Net
     * Scans submitted note for distress terms
     */
    public static function checkDistress($text) {
        if (empty($text)) return false;
        
        $distress_keywords = [
            'suicide', 'kill myself', 'end my life', 'self-harm', 'harm myself',
            'want to die', 'cutting myself', 'overdose', 'commit suicide',
            'better off dead', 'wish i was dead', 'end it all', 'hurt myself'
        ];
        
        $pattern = '/' . implode('|', array_map('preg_quote', $distress_keywords)) . '/i';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * Sprint 2.3 - Predictive Analytics Warning
     * Detects consecutive downward mood trajectories (3 checks where mood is strictly declining)
     */
    public static function checkDownwardTrend($userId) {
        global $db_connected, $pdo;
        $checkin_moods = [];

        // 1. FETCH FROM MYSQL
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT mood FROM daily_checkins WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
                $stmt->execute([$userId]);
                $checkin_moods = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $ex) {}
        }

        // 2. FETCH FROM SESSION
        if (!$db_connected || count($checkin_moods) < 3) {
            if (isset($_SESSION['mock_checkins'])) {
                // Filter and sort chronologically DESC
                $user_checkins = [];
                foreach ($_SESSION['mock_checkins'] as $c) {
                    if ($c['user_id'] == $userId) {
                        $user_checkins[] = $c;
                    }
                }
                // Sort by created_at DESC
                usort($user_checkins, function($a, $b) {
                    return strcmp($b['created_at'], $a['created_at']);
                });
                
                $checkin_moods = [];
                for ($i = 0; $i < min(3, count($user_checkins)); $i++) {
                    $checkin_moods[] = $user_checkins[$i]['mood'];
                }
            }
        }

        // We need at least 3 checkins to determine a downward trend
        if (count($checkin_moods) < 3) {
            return false;
        }

        // Convert mood names to scores
        // Note: the order in $checkin_moods is from NEWEST (index 0) to OLDEST (index 2)
        // Downward trend means: Oldest > Middle > Newest
        // e.g. checkin_moods = [Okay (60), Good (80), Great (100)] (Newest to Oldest)
        // Which means chronologically: Great -> Good -> Okay. This is declining!
        $score_newest = self::$mood_values[strtolower($checkin_moods[0])] ?? 50;
        $score_middle = self::$mood_values[strtolower($checkin_moods[1])] ?? 50;
        $score_oldest = self::$mood_values[strtolower($checkin_moods[2])] ?? 50;

        return ($score_oldest > $score_middle) && ($score_middle > $score_newest);
    }

    /**
     * Log Telemetry Log
     */
    public static function logTelemetry($userId, $data) {
        global $db_connected, $pdo;
        $log_date = $data['log_date'] ?? date('Y-m-d');
        $sleep_hours = (float)($data['sleep_hours'] ?? 0);
        $sleep_quality = (int)($data['sleep_quality'] ?? 0);
        $steps = (int)($data['steps'] ?? 0);
        $active_minutes = (int)($data['active_minutes'] ?? 0);
        $hrv = (int)($data['hrv'] ?? 50);
        $resting_hr = (int)($data['resting_hr'] ?? 70);
        $social_interaction = (int)($data['social_interaction'] ?? 5);
        $voice_stress_score = isset($data['voice_stress_score']) ? (int)$data['voice_stress_score'] : null;

        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO user_telemetry_logs 
                    (user_id, log_date, sleep_hours, sleep_quality, steps, active_minutes, hrv, resting_hr, social_interaction, voice_stress_score) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    sleep_hours = VALUES(sleep_hours), 
                    sleep_quality = VALUES(sleep_quality), 
                    steps = VALUES(steps), 
                    active_minutes = VALUES(active_minutes), 
                    hrv = VALUES(hrv), 
                    resting_hr = VALUES(resting_hr), 
                    social_interaction = VALUES(social_interaction), 
                    voice_stress_score = COALESCE(VALUES(voice_stress_score), voice_stress_score)");
                $stmt->execute([$userId, $log_date, $sleep_hours, $sleep_quality, $steps, $active_minutes, $hrv, $resting_hr, $social_interaction, $voice_stress_score]);
                $saved = true;
            } catch (PDOException $ex) {}
        }

        if (!$saved && isset($_SESSION['mock_telemetry_logs'])) {
            $found = false;
            foreach ($_SESSION['mock_telemetry_logs'] as &$log) {
                if ($log['user_id'] == $userId && $log['log_date'] === $log_date) {
                    $log['sleep_hours'] = $sleep_hours;
                    $log['sleep_quality'] = $sleep_quality;
                    $log['steps'] = $steps;
                    $log['active_minutes'] = $active_minutes;
                    $log['hrv'] = $hrv;
                    $log['resting_hr'] = $resting_hr;
                    $log['social_interaction'] = $social_interaction;
                    if ($voice_stress_score !== null) {
                        $log['voice_stress_score'] = $voice_stress_score;
                    }
                    $found = true;
                    break;
                }
            }
            unset($log);

            if (!$found) {
                $_SESSION['mock_telemetry_logs'][] = [
                    'user_id' => $userId,
                    'log_date' => $log_date,
                    'sleep_hours' => $sleep_hours,
                    'sleep_quality' => $sleep_quality,
                    'steps' => $steps,
                    'active_minutes' => $active_minutes,
                    'hrv' => $hrv,
                    'resting_hr' => $resting_hr,
                    'social_interaction' => $social_interaction,
                    'voice_stress_score' => $voice_stress_score,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            $saved = true;
        }

        // Trigger dynamic twin profile updates when telemetry is updated
        self::recalculateDigitalTwin($userId);

        return $saved;
    }

    /**
     * Fetch Telemetry History
     */
    public static function getTelemetryHistory($userId, $limit = 7) {
        global $db_connected, $pdo;
        $history = [];

        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM user_telemetry_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT ?");
                $stmt->bindValue(1, $userId, PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $ex) {}
        }

        if (empty($history) && isset($_SESSION['mock_telemetry_logs'])) {
            $filtered = array_filter($_SESSION['mock_telemetry_logs'], function($log) use ($userId) {
                return $log['user_id'] == $userId;
            });
            usort($filtered, function($a, $b) {
                return strcmp($b['log_date'], $a['log_date']);
            });
            $history = array_slice($filtered, 0, $limit);
        }

        // Sort chronological ASC for chart plotting
        usort($history, function($a, $b) {
            return strcmp($a['log_date'], $b['log_date']);
        });

        return $history;
    }

    /**
     * Detect early warnings based on telemetry history
     */
    public static function detectEarlyWarnings($userId) {
        // Fetch last 3 days of telemetry
        $history = self::getTelemetryHistory($userId, 3);
        $warnings = [];

        if (count($history) >= 1) {
            $latest = end($history);
            
            // Anxiety Spike Warning (HRV < 30ms and resting heart rate > 85 bpm)
            if (($latest['hrv'] ?? 50) < 30 && ($latest['resting_hr'] ?? 70) > 85) {
                $warnings['anxiety'] = [
                    'title' => 'Anxiety Spike Warning',
                    'desc' => 'High resting heart rate and low HRV indicate severe autonomic stress. Try a Box Breathing session or rest.',
                    'action' => 'Go to AI Companion',
                    'link' => 'ai_companion.php'
                ];
            }

            // Emotional Overload Warning (high voice stress > 70% and low sleep < 5.5h)
            if (($latest['voice_stress_score'] ?? 0) > 70 && ($latest['sleep_hours'] ?? 8) < 5.5) {
                $warnings['overload'] = [
                    'title' => 'Emotional Overload Alert',
                    'desc' => 'Your voice stress rating is elevated, combined with sleep deprivation. We suggest pausing for a 10-Minute walk.',
                    'action' => 'Try Recovery Tools',
                    'link' => 'caregiver_hub.php?tab=recovery'
                ];
            }
        }

        if (count($history) >= 3) {
            // Burnout warning (sleep quality < 75% and steps < 3000 for 3 consecutive days)
            $burnout_streak = true;
            foreach ($history as $h) {
                if ($h['sleep_quality'] >= 75 || $h['steps'] >= 3000) {
                    $burnout_streak = false;
                }
            }
            if ($burnout_streak) {
                $warnings['burnout'] = [
                    'title' => 'Burnout Vulnerability Warning',
                    'desc' => 'Consecutive days of poor sleep and low physical activity detected. Plan a respite break immediately.',
                    'action' => 'Open Respite Tracker',
                    'link' => 'caregiver_hub.php?tab=respite'
                ];
            }

            // Depression Warning / Social Isolation (steps < 2000 and social score < 3 for 3 consecutive days)
            $depression_streak = true;
            foreach ($history as $h) {
                if ($h['steps'] >= 2000 || $h['social_interaction'] >= 3) {
                    $depression_streak = false;
                }
            }
            if ($depression_streak) {
                $warnings['depression'] = [
                    'title' => 'Social Isolation & Withdrawal Warning',
                    'desc' => 'Prolonged low activity and low social contact detected. Consider connecting with your matched caregiver peer.',
                    'action' => 'View Matched Peers',
                    'link' => 'caregiver_hub.php?tab=matching'
                ];
            }
        }

        return $warnings;
    }

    /**
     * Get Digital Twin Profile
     */
    public static function getDigitalTwinProfile($userId) {
        global $db_connected, $pdo;
        $profile = null;

        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM digital_twin_profiles WHERE user_id = ?");
                $stmt->execute([$userId]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $ex) {}
        }

        if (!$profile && isset($_SESSION['mock_digital_twin_profiles'][$userId])) {
            $profile = $_SESSION['mock_digital_twin_profiles'][$userId];
        }

        if (!$profile) {
            // Recalculate/create profile
            self::recalculateDigitalTwin($userId);
            return self::getDigitalTwinProfile($userId);
        }

        return $profile;
    }

    /**
     * Recalculate Digital Twin metrics
     */
    public static function recalculateDigitalTwin($userId) {
        global $db_connected, $pdo;

        $history = self::getTelemetryHistory($userId, 7);
        
        // Default baselines
        $anxiety_resilience = 60;
        $depression_resistance = 60;
        $burnout_buffer = 50;

        $learned_triggers = [];
        $coping_styles = ["Sensory Grounding (5-4-3-2-1)", "Stretching Exercises"];

        if (count($history) > 0) {
            $avg_sleep_quality = 0;
            $avg_sleep_hours = 0;
            $avg_steps = 0;
            $avg_hrv = 0;
            $avg_resting_hr = 0;
            $avg_social = 0;
            $low_sleep_days = 0;
            $low_steps_days = 0;
            $high_stress_voice_days = 0;

            foreach ($history as $h) {
                $avg_sleep_quality += $h['sleep_quality'];
                $avg_sleep_hours += $h['sleep_hours'];
                $avg_steps += $h['steps'];
                $avg_hrv += $h['hrv'];
                $avg_resting_hr += $h['resting_hr'];
                $avg_social += $h['social_interaction'];

                if ($h['sleep_hours'] < 6.0) $low_sleep_days++;
                if ($h['steps'] < 3000) $low_steps_days++;
                if (($h['voice_stress_score'] ?? 0) > 60) $high_stress_voice_days++;
            }

            $count = count($history);
            $avg_sleep_quality /= $count;
            $avg_sleep_hours /= $count;
            $avg_steps /= $count;
            $avg_hrv /= $count;
            $avg_resting_hr /= $count;
            $avg_social /= $count;

            // Trigger detection
            if ($low_sleep_days >= 3) {
                $learned_triggers[] = "Low sleep duration (<6 hours)";
            }
            if ($low_steps_days >= 3) {
                $learned_triggers[] = "Sedentary routines (<3000 steps)";
            }
            if ($avg_resting_hr > 78) {
                $learned_triggers[] = "Elevated heart rate indicating chronic physical stress";
            }
            if ($high_stress_voice_days >= 2) {
                $learned_triggers[] = "Vocal stress patterns detected in audio logs";
            }

            // Coping styles correlation
            // Fetch if user has logged respite breaks
            $respite_count = 0;
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM caregiver_respite_breaks WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $respite_count = (int)$stmt->fetchColumn();
                } catch (PDOException $ex) {}
            } else if (isset($_SESSION['mock_respite_breaks'])) {
                foreach ($_SESSION['mock_respite_breaks'] as $r) {
                    if ($r['user_id'] == $userId) $respite_count++;
                }
            }
            if ($respite_count > 0) {
                $coping_styles[] = "Scheduled Respite Downtime";
            }

            // Resilience ratings computations
            $anxiety_resilience = round(($avg_hrv * 0.7) + ($avg_sleep_quality * 0.3));
            $depression_resistance = round(($avg_steps / 150) + ($avg_social * 4) + ($avg_sleep_hours * 2));
            $burnout_buffer = round(($avg_sleep_quality * 0.6) + (($avg_steps > 5000 ? 40 : ($avg_steps / 125))));
        } else {
            $learned_triggers = ["Insufficient telemetry history - syncing wearables recommended"];
        }

        // Clamp
        $anxiety_resilience = max(10, min(100, (int)$anxiety_resilience));
        $depression_resistance = max(10, min(100, (int)$depression_resistance));
        $burnout_buffer = max(10, min(100, (int)$burnout_buffer));

        $triggers_json = json_encode($learned_triggers);
        $coping_json = json_encode($coping_styles);

        $saved = false;
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO digital_twin_profiles 
                    (user_id, learned_triggers, coping_styles, anxiety_resilience, depression_resistance, burnout_buffer) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    learned_triggers = VALUES(learned_triggers), 
                    coping_styles = VALUES(coping_styles), 
                    anxiety_resilience = VALUES(anxiety_resilience), 
                    depression_resistance = VALUES(depression_resistance), 
                    burnout_buffer = VALUES(burnout_buffer)");
                $stmt->execute([$userId, $triggers_json, $coping_json, $anxiety_resilience, $depression_resistance, $burnout_buffer]);
                $saved = true;
            } catch (PDOException $ex) {}
        }

        if (!$saved) {
            $_SESSION['mock_digital_twin_profiles'][$userId] = [
                'user_id' => $userId,
                'learned_triggers' => $triggers_json,
                'coping_styles' => $coping_json,
                'anxiety_resilience' => $anxiety_resilience,
                'depression_resistance' => $depression_resistance,
                'burnout_buffer' => $burnout_buffer,
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
}
?>
