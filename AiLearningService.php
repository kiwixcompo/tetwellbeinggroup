<?php
/**
 * Tet Wellbeing Group - AI Learning & Personalized Memory Service (AiLearningService.php)
 * 
 * Ensures that the AI learns from every user account individually.
 * Even if two users are on the same subscription plan or sharing the same device,
 * each user account has completely isolated memory, personality adaptation,
 * emotional history, goals, and contextual recall.
 */

require_once __DIR__ . '/db.php';

class AiLearningService {

    /**
     * Build a rich, personalized learning profile for the given user ID.
     */
    public static function getUserLearningProfile($userId) {
        global $pdo, $db_connected;

        if (!$userId) return null;

        $profile = [
            'user_id' => $userId,
            'name' => 'Friend',
            'email' => '',
            'plan' => 'Basic Tier',
            'role' => 'client',
            'recent_mood' => null,
            'recent_notes' => null,
            'active_goals' => [],
            'recent_topics' => [],
            'learned_memories' => [],
            'total_interactions' => 0
        ];

        // 1. Fetch User Data
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT name, email, role, subscription_plan FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $profile['name'] = $user['name'] ?: 'Friend';
                    $profile['email'] = $user['email'] ?: '';
                    $profile['plan'] = !empty($user['subscription_plan']) ? ucfirst($user['subscription_plan']) . ' Tier' : 'Basic Tier';
                    $profile['role'] = $user['role'] ?: 'client';
                }
            } catch (Exception $e) {}
        } else if (isset($_SESSION['mock_users'])) {
            foreach ($_SESSION['mock_users'] as $u) {
                if ($u['id'] == $userId) {
                    $profile['name'] = $u['name'] ?: 'Friend';
                    $profile['email'] = $u['email'] ?: '';
                    $profile['plan'] = !empty($u['subscription_plan']) ? ucfirst($u['subscription_plan']) . ' Tier' : 'Basic Tier';
                    $profile['role'] = $u['role'] ?? 'client';
                    break;
                }
            }
        }

        // 2. Fetch Latest Mood & Daily Check-in
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT mood, notes, created_at FROM daily_checkins WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$userId]);
                $checkin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($checkin) {
                    $profile['recent_mood'] = $checkin['mood'];
                    $profile['recent_notes'] = $checkin['notes'];
                }
            } catch (Exception $e) {}
        } else if (isset($_SESSION['mock_checkins'])) {
            $userCheckins = array_filter($_SESSION['mock_checkins'], fn($c) => $c['user_id'] == $userId);
            if (!empty($userCheckins)) {
                $last = end($userCheckins);
                $profile['recent_mood'] = $last['mood'] ?? null;
                $profile['recent_notes'] = $last['notes'] ?? null;
            }
        }

        // 3. Fetch Learned AI Memories for this User
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT memory_key, memory_value, category FROM user_ai_memories WHERE user_id = ? ORDER BY updated_at DESC LIMIT 15");
                $stmt->execute([$userId]);
                $memories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($memories as $m) {
                    $profile['learned_memories'][$m['memory_key']] = $m['memory_value'];
                }
            } catch (Exception $e) {}
        }

        // Fallback or augment with session-based memories
        if (isset($_SESSION['mock_user_ai_memories'][$userId])) {
            foreach ($_SESSION['mock_user_ai_memories'][$userId] as $k => $v) {
                if (!isset($profile['learned_memories'][$k])) {
                    $profile['learned_memories'][$k] = $v;
                }
            }
        }

        // 4. Fetch Recent Chat History Topics & Interaction Count
        if ($db_connected && $pdo) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ai_chat_logs WHERE user_id = ?");
                $stmt->execute([$userId]);
                $profile['total_interactions'] = (int)($stmt->fetchColumn() ?: 0);

                $stmt = $pdo->prepare("SELECT message FROM ai_chat_logs WHERE user_id = ? AND sender = 'user' ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$userId]);
                $userMsgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $profile['recent_topics'] = array_slice($userMsgs, 0, 3);
            } catch (Exception $e) {}
        } else if (isset($_SESSION['mock_ai_chats'])) {
            $userChats = array_filter($_SESSION['mock_ai_chats'], fn($c) => $c['user_id'] == $userId);
            $profile['total_interactions'] = count($userChats);
            $userMsgs = array_column(array_filter($userChats, fn($c) => $c['sender'] === 'user'), 'message');
            $profile['recent_topics'] = array_slice(array_reverse($userMsgs), 0, 3);
        }

        return $profile;
    }

    /**
     * Formats the user's learning context for system prompt injection.
     */
    public static function buildSystemPromptContext($userId) {
        $p = self::getUserLearningProfile($userId);
        if (!$p) return "";

        $contextLines = [
            "### USER IDENTITY & LEARNING MEMORY (ACCOUNT SPECIFIC)",
            "- User Name: " . $p['name'],
            "- User Plan: " . $p['plan'],
            "- Account ID: " . $p['user_id']
        ];

        if ($p['recent_mood']) {
            $contextLines[] = "- Latest Logged Mood: " . $p['recent_mood'] . ($p['recent_notes'] ? " (\"" . substr($p['recent_notes'], 0, 100) . "\")" : "");
        }

        if (!empty($p['learned_memories'])) {
            $contextLines[] = "- Learned Insights About This User:";
            foreach ($p['learned_memories'] as $k => $v) {
                $contextLines[] = "  * " . ucfirst($k) . ": $v";
            }
        }

        if (!empty($p['recent_topics'])) {
            $contextLines[] = "- Recent Concerns/Topics Mentioned by User:";
            foreach ($p['recent_topics'] as $topic) {
                $contextLines[] = "  * \"" . substr($topic, 0, 80) . "\"";
            }
        }

        $contextLines[] = "Instructions for AI:";
        $contextLines[] = "1. Personalize all responses to {$p['name']}'s unique journey and past context.";
        $contextLines[] = "2. Build on what you know about them without sounding repetitive or clinical.";
        $contextLines[] = "3. Format cleanly using markdown (bullet points, bold text, short readable paragraphs).";
        $contextLines[] = "### END USER CONTEXT\n";

        return implode("\n", $contextLines);
    }

    /**
     * Learns from a user's incoming message and updates memories.
     */
    public static function learnFromInteraction($userId, $userMessage, $aiResponse = '') {
        global $pdo, $db_connected;

        if (!$userId || empty(trim($userMessage))) return;

        $msgLower = strtolower($userMessage);
        $extractedMemories = [];

        // Simple heuristic extraction of user life facts & topics
        if (preg_match('/(struggling with|having trouble with|stressed about|worried about|anxious about)\s+([^.,!?\n]{4,60})/i', $userMessage, $m)) {
            $extractedMemories['current_stressor'] = trim($m[2]);
        }
        if (preg_match('/(my (job|work|boss|team|career|exam|study|degree|mom|mother|dad|father|partner|husband|wife|kid|child|dog|cat|sleep|routine))/i', $userMessage, $m)) {
            $extractedMemories['life_context'] = "Mentioned " . trim($m[0]);
        }
        if (str_contains($msgLower, 'caregiver') || str_contains($msgLower, 'dementia') || str_contains($msgLower, 'respite')) {
            $extractedMemories['caregiver_role'] = "Has caregiving responsibilities";
        }
        if (str_contains($msgLower, 'cant sleep') || str_contains($msgLower, "can't sleep") || str_contains($msgLower, 'insomnia')) {
            $extractedMemories['sleep_issue'] = "Struggles with nighttime sleep or restlessness";
        }
        if (str_contains($msgLower, 'overthinking') || str_contains($msgLower, 'spiraling') || str_contains($msgLower, 'panic')) {
            $extractedMemories['cognitive_pattern'] = "Prone to overthinking loops or panic triggers";
        }

        // Save memories to DB or Session
        foreach ($extractedMemories as $key => $val) {
            if ($db_connected && $pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO user_ai_memories (user_id, memory_key, memory_value, category) 
                        VALUES (?, ?, ?, 'extracted') 
                        ON DUPLICATE KEY UPDATE memory_value = VALUES(memory_value), updated_at = CURRENT_TIMESTAMP");
                    $stmt->execute([$userId, $key, $val]);
                } catch (Exception $e) {}
            }
            $_SESSION['mock_user_ai_memories'][$userId][$key] = $val;
        }
    }

    /**
     * Generates a context-rich, personalized response for the user
     * when Puter AI is not signed in or as an intelligent local engine.
     */
    public static function generateContextualResponse($userId, $userMessage, $character = 'maya', $mode = 'befriender') {
        $profile = self::getUserLearningProfile($userId);
        $name = $profile['name'] ?: 'Friend';
        $msgLower = strtolower($userMessage);

        // Record learning
        self::learnFromInteraction($userId, $userMessage);

        $isFemale = ($character === 'maya' || $character === 'serena');
        $greeting = $isFemale ? "Hey {$name} 🌸" : "Hey {$name} ☕";
        
        // Context-aware tailoring based on user's specific history
        $contextAddon = "";
        if (!empty($profile['recent_mood'])) {
            $contextAddon = "I remember you mentioned feeling a bit **{$profile['recent_mood']}** recently. ";
        } else if (!empty($profile['learned_memories']['caregiver_role'])) {
            $contextAddon = "Knowing everything you carry in your daily caregiving routine, ";
        }

        if (str_contains($msgLower, 'overwhelm') || str_contains($msgLower, 'stress') || str_contains($msgLower, 'too much') || str_contains($msgLower, 'tired')) {
            if ($isFemale) {
                return "{$greeting}, I completely hear you. {$contextAddon}When things pile up, it's not because you're failing—it's because your nervous system is asking for a moment to breathe.\n\nHere are three gentle things I personally recommend for you today:\n- **Drop the non-essentials**: Give yourself permission to do the bare minimum for the next 2 hours.\n- **A warm sensory reset**: Sip some warm chamomile or peppermint tea without looking at a screen.\n- **60-Second Decompress**: Inhale for 4 seconds, hold for 2, and exhale slowly for 6.\n\nHow is your body feeling right this second?";
            } else {
                return "{$greeting}, that is super relatable. {$contextAddon}When the mental load gets heavy, our instinct is to try to push through, but that usually just multiplies the friction.\n\nMy personal take on how to handle this right now:\n- **Put down the to-do list**: Pick only the single next physical step.\n- **Change your physical space**: Even a 5-minute walk outside or stepping by an open window resets your mental baseline.\n- **Zero-Pressure zone**: You don't have to explain or justify yourself to anyone right now.\n\nWhat feels like the heaviest thing on your plate today?";
            }
        }

        if (str_contains($msgLower, 'sleep') || str_contains($msgLower, 'night') || str_contains($msgLower, 'insomnia')) {
            return "{$greeting}, sleep struggles can make everything during the day feel twice as challenging.\n\nHere is a simple wind-down routine that has worked wonders:\n- **Dim screens 45 minutes before bed**: Blue light tricks your brain into thinking it's midday.\n- **Brain Dump**: Write down whatever thoughts are racing onto paper so your mind knows they won't be forgotten.\n- **Try a body scan**: Starting from your toes, consciously unclench each muscle group.\n\nWould you like me to walk you through a relaxing 2-minute breathing cycle?";
        }

        if (str_contains($msgLower, 'recommend') || str_contains($msgLower, 'book') || str_contains($msgLower, 'music') || str_contains($msgLower, 'habit')) {
            return "{$greeting}, I love sharing recommendations! Based on what helps most when looking for calm:\n\n- **Music**: Lo-fi chillhop or ambient nature soundscapes (like gentle rain on cedar wood).\n- **Reading**: *The Comfort Book* by Matt Haig or *Atomic Habits* by James Clear for gentle resets.\n- **Daily Habit**: The **'2-Minute Pause'**—setting a timer twice a day just to stretch and take deep breaths.\n\nWhat kind of vibe are you in the mood for today?";
        }

        // General conversational response tailored with user name & empathy
        if ($isFemale) {
            return "{$greeting}, thank you for sharing that with me. {$contextAddon}I really appreciate you being open. Life has a way of throwing a lot of moving parts at us, and having a safe space to unpack them makes a world of difference.\n\nFrom my perspective, you are navigating this with a lot of resilience. What would feel most supportive or comforting for you right now?";
        } else {
            return "{$greeting}, thanks for talking this through with me. {$contextAddon}It makes complete sense why you're thinking about this. Sometimes just laying it out on the table helps untangle things that felt stuck in our heads.\n\nI'm in your corner. Let's tackle it one step at a time—what's the next small thing you'd like to talk through?";
        }
    }
}
