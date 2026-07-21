<?php
/**
 * Tet Wellbeing Group - AI Integration Service
 * Wraps the Google Gemini API to provide truly intelligent responses across the platform.
 */

class AiIntegrationService {
    private static $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';

    private static function callGemini($prompt) {
        // As requested by the user, Gemini API has been completely replaced by Puter.js AI on the frontend.
        // This backend service is now deprecated and returns false to trigger fallback mechanisms if accidentally called.
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
