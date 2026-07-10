<?php

namespace App\Modules\SystemSettings\Services;

use App\Modules\SystemSettings\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemSettingsService
{
    protected const CACHE_PREFIX = 'system_setting_';

    /**
     * Get a setting by key. Uses caching for performance.
     */
    public function get(string $key, $default = null)
    {
        return Cache::remember(self::CACHE_PREFIX.$key, 3600, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set/Update a single setting. Clears cache.
     */
    public function set(string $key, $value): Setting
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget(self::CACHE_PREFIX.$key);
        Cache::forget(self::CACHE_PREFIX.'all');

        return $setting;
    }

    /**
     * Get all settings in key-value map.
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'all', 3600, function () {
            $all = Setting::all();
            $mapped = [];

            // Add standard defaults
            $defaults = [
                'currency' => 'USD',
                'timezone' => 'UTC',
                'language' => 'en',
                'ai_default_provider' => 'gemini',
                'ai_default_model' => 'gemini-2.5-flash',
                'enable_image_generation' => true,
            ];

            foreach ($defaults as $k => $v) {
                $mapped[$k] = $v;
            }

            foreach ($all as $setting) {
                // Skip null values so DB nulls don't override safe defaults
                if ($setting->value !== null) {
                    $mapped[$setting->key] = $setting->value;
                }
            }

            return $mapped;
        });
    }

    /**
     * Update multiple settings inside a database transaction.
     */
    public function updateMany(array $settings): void
    {
        try {
            DB::transaction(function () use ($settings) {
                foreach ($settings as $key => $value) {
                    $this->set($key, $value);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to update system settings: '.$e->getMessage());
            throw new \RuntimeException('Database transaction failed when updating system settings.', 0, $e);
        }
    }
}
