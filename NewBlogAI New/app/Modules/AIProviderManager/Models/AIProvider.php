<?php

namespace App\Modules\AIProviderManager\Models;

use Illuminate\Database\Eloquent\Model;

class AIProvider extends Model
{
    protected $table = 'ai_providers';

    protected $fillable = [
        'provider_key',
        'name',
        'api_key',
        'default_model',
        'is_default',
        'is_enabled',
        'credits_total',
        'credits_remaining',
        'reset_at',
        'last_error',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'reset_at' => 'datetime',
        ];
    }

    /**
     * Get the masked representation of the API key.
     */
    public function getMaskedApiKey(): ?string
    {
        if (empty($this->api_key)) {
            return null;
        }

        $key = $this->api_key;
        $len = strlen($key);

        if ($len > 12) {
            return substr($key, 0, 8).'...'.substr($key, -3);
        }
        if ($len > 4) {
            return substr($key, 0, 2).'...'.substr($key, -2);
        }

        return '...';
    }

    /**
     * Accessor: auto-clear last_error when the reset window has already passed.
     * This prevents stale rate-limit errors from showing after the provider has recovered.
     */
    public function getLastErrorAttribute(?string $value): ?string
    {
        if ($value && $this->attributes['reset_at'] ?? null) {
            $resetAt = \Carbon\Carbon::parse($this->attributes['reset_at']);
            if ($resetAt->isPast()) {
                // Quietly wipe the stale error from the DB so it doesn't keep coming back
                $this->withoutEvents(fn () => $this->updateQuietly(['last_error' => null]));
                return null;
            }
        }
        return $value;
    }

    /**
     * Update credit/rate limit metrics from successful response details.
     */
    public function updateRateLimits(?int $limit, ?int $remaining, ?string $reset): void
    {
        $update = [];
        if ($limit !== null) {
            $update['credits_total'] = $limit;
        }
        if ($remaining !== null) {
            $update['credits_remaining'] = $remaining;
        }
        if ($reset !== null) {
            $seconds = 0;
            if (preg_match('/(\d+)\s*h/i', $reset, $m)) {
                $seconds += intval($m[1]) * 3600;
            }
            // Use negative lookahead to prevent matching 'ms' as minutes
            if (preg_match('/(\d+)\s*m(?!s)/i', $reset, $m)) {
                $seconds += intval($m[1]) * 60;
            }
            // Match milliseconds explicitly, or standard seconds
            if (preg_match('/(\d+\.?\d*)\s*ms/i', $reset, $m)) {
                $seconds += floatval($m[1]) / 1000.0;
            } elseif (preg_match('/(\d+\.?\d*)\s*s/i', $reset, $m)) {
                $seconds += floatval($m[1]);
            }

            $update['reset_at'] = $seconds > 0 ? now()->addSeconds(intval(ceil($seconds))) : null;
        }

        if (!empty($update)) {
            $this->update($update);
        }
    }

    /**
     * Parse errors and update key status/errors in the database.
     * Auto-disables permanent auth or quota failures, and records rate limits.
     */
    public function handleFailure(\Throwable $e): void
    {
        $message = $e->getMessage();
        $this->last_error = $message;

        if (str_contains(strtolower($message), 'rate limit') || str_contains($message, '429')) {
            $this->last_error = 'Rate limit exceeded';

            // Parse reset time from error message.
            // Groq format:   "Please try again in 1h34m7.968s"
            // Gemini format: "Please retry in 28.093431161s"
            // Generic:       "reset in 2m30s"
            $seconds = 0;
            if (preg_match('/(?:try again in|retry in|reset in)\s*([\dh m s\.]+)/i', $message, $matches)) {
                $resetStr = $matches[1];
                if (preg_match('/(\d+)\s*h/i', $resetStr, $m)) {
                    $seconds += (int) $m[1] * 3600;
                }
                if (preg_match('/(\d+)\s*m(?!s)/i', $resetStr, $m)) {
                    $seconds += (int) $m[1] * 60;
                }
                if (preg_match('/(\d+(?:\.\d+)?)\s*s/i', $resetStr, $m)) {
                    $seconds += (int) ceil((float) $m[1]);
                }
            }

            $this->reset_at = now()->addSeconds(max($seconds, 60));
        } else {
            // 401 Unauthorized or 403 Forbidden → bad/expired key → disable immediately
            // 402 Payment Required → out of credits → disable immediately
            if (preg_match('/Status\s+(401|402|403)/', $message, $m)) {
                $this->is_enabled = false;
                $this->last_error = match ((int) $m[1]) {
                    402     => 'Disabled: Payment Required / Out of Credits',
                    403     => 'Disabled: Forbidden (check key permissions)',
                    default => 'Disabled: Invalid API Key',
                };
            }
        }

        $this->save();
    }
}
