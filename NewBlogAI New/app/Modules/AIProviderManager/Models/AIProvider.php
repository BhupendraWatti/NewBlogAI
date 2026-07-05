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
}
