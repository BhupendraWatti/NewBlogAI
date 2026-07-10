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
     * Update credit/rate limit metrics from successful response details.
     */
    public function updateRateLimits(?int $limit, ?int $remaining, ?string $reset, ?string $error = null): void
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
            if (preg_match('/(\d+)\s*m/i', $reset, $m)) {
                $seconds += intval($m[1]) * 60;
            }
            if (preg_match('/(\d+\.?\d*)\s*s/i', $reset, $m)) {
                $seconds += intval(round(floatval($m[1])));
            }
            
            $update['reset_at'] = $seconds > 0 ? now()->addSeconds($seconds) : null;
        }
        
        $update['last_error'] = $error;
        $this->update($update);
    }

    /**
     * Parse errors and update key status/errors in the database.
     * Auto-disables permanent auth or quota failures, and records rate limits.
     */
    public function handleFailure(\Throwable $e): void
    {
        $message = $e->getMessage();
        $this->last_error = $message;

        if (str_contains(strtolower($message), 'rate limit') || str_contains(strtolower($message), '429')) {
            $this->last_error = 'API rate Limit exceed';
            
            $seconds = 0;
            if (preg_match('/(?:try again in|reset in)\s+([\w\.\s]+)/i', $message, $matches)) {
                $resetStr = $matches[1];
                if (preg_match('/(\d+)\s*h/i', $resetStr, $m)) {
                    $seconds += intval($m[1]) * 3600;
                }
                if (preg_match('/(\d+)\s*m/i', $resetStr, $m)) {
                    $seconds += intval($m[1]) * 60;
                }
                if (preg_match('/(\d+\.?\d*)\s*s/i', $resetStr, $m)) {
                    $seconds += intval(round(floatval($m[1])));
                }
            }
            
            $this->reset_at = $seconds > 0 ? now()->addSeconds($seconds) : now()->addSeconds(60);
        } else {
            $isPermanent = false;
            // Check for 401 Unauthorized, 402 Payment Required / Out of Credits, or 403 Forbidden
            if (preg_match('/Status\s+(401|402|403)/', $message)) {
                $isPermanent = true;
            }

            if ($isPermanent) {
                $this->is_enabled = false;
                $this->last_error = 'Disabled: ' . (preg_match('/Status\s+402/', $message) ? 'Payment Required / Out of Credits' : 'Invalid API Key');
            }
        }

        $this->save();
    }
}
