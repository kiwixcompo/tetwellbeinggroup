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
}
?>
