<?php

declare(strict_types=1);

namespace App\Modules\AIProviderManager\Support;

/**
 * Classifies AI provider failures as retryable (transient) or not.
 *
 * The failover loops previously retried EVERY error three times with
 * exponential back-off — including permanent failures like `401 invalid key`
 * or `connection refused`. On the generation side each pointless retry re-runs
 * pipeline stages and burns real tokens/quota, which is the main reason API
 * keys were exhausting so quickly. This classifier lets the loops retry only
 * errors that could plausibly succeed on a second attempt, and otherwise fail
 * over to the next provider immediately.
 *
 * Drivers throw messages shaped like:
 *   "<Provider> API error: Status <code> - <body>"
 *   "<Provider> generation failed: cURL error 7: Failed to connect ..."
 * so we parse the HTTP status when present and fall back to string heuristics.
 */
final class ProviderErrorClassifier
{
    /**
     * HTTP statuses worth retrying on the SAME provider. Everything else
     * (auth, bad request, not found, etc.) is permanent for this provider.
     */
    private const RETRYABLE_STATUSES = [408, 425, 429, 500, 502, 503, 504, 529];

    /** Substrings that indicate a transient network/timeout condition. */
    private const RETRYABLE_HINTS = [
        'timed out',
        'timeout',
        'maximum execution time',
        'cURL error 28', // operation timed out
        'cURL error 6',  // could not resolve host (often transient DNS)
        'connection reset',
        'temporarily unavailable',
        'overloaded',
        'rate limit',
    ];

    /**
     * True when retrying the same provider might succeed (rate limits,
     * overloads, timeouts, 5xx). False for permanent failures (bad/missing
     * key, connection refused, bad request) — fail over immediately instead.
     */
    public static function isRetryable(\Throwable $e): bool
    {
        $message = $e->getMessage();

        if (($status = self::extractStatus($message)) !== null) {
            return in_array($status, self::RETRYABLE_STATUSES, true);
        }

        $lower = strtolower($message);
        foreach (self::RETRYABLE_HINTS as $hint) {
            if (str_contains($lower, strtolower($hint))) {
                return true;
            }
        }

        // Unknown/unparseable error: don't waste back-off on it — move on.
        return false;
    }

    /**
     * Short human-readable reason used in the consolidated failover error so
     * admins can tell misconfiguration (fix the key) from throttling (wait).
     */
    public static function reason(\Throwable $e): string
    {
        $message = $e->getMessage();
        $status  = self::extractStatus($message);

        return match (true) {
            $status === 401 || $status === 403 => 'auth failed (check API key)',
            $status === 404                    => 'model/endpoint not found',
            $status === 429                    => 'rate limited / quota exhausted',
            $status !== null && $status >= 500 => "provider error ({$status})",
            str_contains(strtolower($message), 'curl error 7') => 'connection refused (service not running)',
            str_contains(strtolower($message), 'timeout') || str_contains(strtolower($message), 'timed out') => 'timeout',
            default => 'failed',
        };
    }

    /** Extract the HTTP status code from a driver error string, if present. */
    private static function extractStatus(string $message): ?int
    {
        if (preg_match('/Status\s+(\d{3})/', $message, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }
}
