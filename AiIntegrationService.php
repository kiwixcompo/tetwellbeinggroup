<?php
/**
 * Tet Wellbeing Group - AI Integration Service
 * Wraps the Google Gemini API to provide truly intelligent responses across the platform.
 */

class AiIntegrationService {
    private static $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=';

    /**
     * Helper to make API calls to Gemini using cURL
     */
    private static function callGemini($prompt) {
        global $gemini_api_key;
        
        if (empty($gemini_api_key)) {
            return false;
        }

        $url = self::$api_url . $gemini_api_key;
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 800
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disabled for local WAMP testing

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                // Strip markdown formatting like asterisks for cleaner UI integration
                $text = trim($json['candidates'][0]['content']['parts'][0]['text']);
                $text = str_replace(['**', '*'], '', $text);
                return $text;
            }
        }
        return false;
    }

    /**
     * Generates a conversational response for the AI Companion
     */
    public static function generateChatResponse($user_message, $language = 'English', $history = []) {
        $history_text = "";
        if (!empty($history)) {
            $history_text = "Recent conversation context:\n";
            // Get last 3 messages
            $limit = min(3, count($history));
            $recent = array_slice($history, -$limit);
            foreach ($recent as $msg) {
                $sender = $msg['sender'] === 'ai' ? 'Therapist' : 'Patient';
                $history_text .= "- $sender: " . $msg['message'] . "\n";
            }
        }

        $prompt = "You are an empathetic, professional AI Cognitive Behavioral Therapy (CBT) Companion. 
Respond to the patient using compassionate, evidence-based CBT techniques. 
IMPORTANT: Your response must be in the '$language' language.
Keep your response conversational, supportive, and relatively short (2-4 sentences). Do not use markdown styling.
$history_text
Patient says: \"$user_message\"
Your response:";

        return self::callGemini($prompt);
    }

    /**
     * Generates an SBAR conflict mitigation plan for Workplace Safety
     */
    public static function generateConflictMitigationPlan($description, $severity) {
        $prompt = "Act as an expert Healthcare HR Mediator.
Generate a concise SBAR (Situation, Background, Assessment, Recommendation) mitigation plan for a workplace conflict.
Severity Level: $severity
Conflict Description: \"$description\"
Format your response as a simple numbered list of actionable steps (max 4 steps). Do not use markdown formatting.";

        return self::callGemini($prompt);
    }
}
