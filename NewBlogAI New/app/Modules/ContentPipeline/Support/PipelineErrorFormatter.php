<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Support;

class PipelineErrorFormatter
{
    /**
     * Format raw AI provider failover exceptions into a beautiful, human-readable
     * error message with exact wait times and quotas to show to the admin user.
     *
     * @param array $allErrors Cumulative errors mapped by provider key
     * @param string $runType E.g. 'Discovery' or 'Content generation'
     * @return string Human-friendly error summary
     */
    public static function format(array $allErrors, string $runType): string
    {
        $lines = ["{$runType} failed on all available AI providers. Details:"];

        foreach ($allErrors as $provider => $errors) {
            $lastError = end($errors);
            // If it's a retry log e.g. "attempt 1: ...", strip the attempt prefix for parsing
            $cleanText = preg_replace('/^attempt \d+:\s*/i', '', $lastError);
            
            $formattedError = self::parseError($cleanText);
            $providerName   = ucfirst($provider);
            $lines[]        = "• **{$providerName}**: {$formattedError}";
        }

        return implode("\n", $lines);
    }

    private static function parseError(string $error): string
    {
        // 1. Gemini Request Quota (Free Tier: 20 per day)
        if (
            str_contains($error, 'generate_content_free_tier_requests') || 
            str_contains($error, 'GenerateRequestsPerDayPerProjectPerModel-FreeTier') ||
            str_contains($error, 'quota exceeded') && str_contains($error, 'gemini')
        ) {
            $retryMsg = '';
            if (preg_match('/retry in ([\d\.]+\s*s|[\d\.]+\s*seconds|\d+\s*s)/i', $error, $m)) {
                $retryMsg = " (retry in {$m[1]})";
            }
            return "Daily request quota (20 requests/day on Gemini Free Tier) exceeded{$retryMsg}. Please wait for your daily quota to reset or update your key to a paid plan.";
        }

        // 2. Groq daily token/request limits
        if (str_contains($error, 'tokens per day') || str_contains($error, 'TPD') || str_contains($error, 'rate_limit_exceeded')) {
            if (str_contains($error, 'llama-3.3-70b-versatile') && str_contains($error, 'TPD')) {
                $timeMsg = 'soon';
                if (preg_match('/try again in ([\w\.\s]+)/i', $error, $m)) {
                    $timeMsg = trim($m[1]);
                }
                return "Daily token limit (100,000 TPD on Llama-3 70B) reached. Please wait {$timeMsg} for this model's quota to reset, or change to a different model in settings.";
            }

            // General Groq rate limits
            $timeMsg = 'soon';
            if (preg_match('/try again in ([\w\.\s]+)/i', $error, $m)) {
                $timeMsg = trim($m[1]);
            } elseif (preg_match('/retry in ([\w\.\s]+)/i', $error, $m)) {
                $timeMsg = trim($m[1]);
            }
            return "Rate limit (429) hit. Please try again in {$timeMsg}.";
        }

        // 3. Authentication Failures (Expired or invalid keys)
        if (
            str_contains($error, 'invalid_api_key') ||
            str_contains($error, 'Incorrect API key') ||
            str_contains($error, 'invalid x-api-key') ||
            str_contains($error, 'authentication_error') ||
            str_contains($error, '401') ||
            str_contains($error, 'auth failed')
        ) {
            return "Authentication failed. The API key configured in 'AI Providers' is invalid, expired, or deactivated.";
        }

        // 4. Local Connection Failures (e.g. Ollama offline)
        if (
            str_contains($error, 'Failed to connect') ||
            str_contains($error, 'cURL error 7') ||
            str_contains($error, 'Couldn\'t connect to server') ||
            str_contains($error, 'connection refused')
        ) {
            return "Connection failed. The service is offline or unreachable (e.g. your local Ollama server is not running on port 11434).";
        }

        // 5. Raw candidates count failures
        if (preg_match('/produced only (\d+) unique candidates/i', $error, $m)) {
            return "Only found {$m[1]} news candidates in the last 48 hours for this topic (minimum of 1 candidate is required).";
        }

        // Fallback for general 429 status
        if (str_contains($error, 'Status 429')) {
            return "Rate limit (429) hit. The API is temporarily busy; please wait a moment and try again.";
        }

        // Fallback to the raw exception message if no specific pattern matches
        return $error;
    }
}
