<?php

namespace App\Modules\AIProviderManager\Services;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use App\Modules\AIProviderManager\Drivers\ClaudeDriver;
use App\Modules\AIProviderManager\Drivers\GoogleGeminiDriver;
use App\Modules\AIProviderManager\Drivers\GroqDriver;
use App\Modules\AIProviderManager\Drivers\OllamaDriver;
use App\Modules\AIProviderManager\Drivers\OpenAIDriver;
use App\Modules\AIProviderManager\Drivers\OpenRouterDriver;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AIProviderService
{
    /**
     * Resolve the corresponding driver for a provider key.
     */
    public function getDriver(string $providerKey): AIProviderClientInterface
    {
        return match (strtolower($providerKey)) {
            'gemini' => new GoogleGeminiDriver,
            'openai' => new OpenAIDriver,
            'claude' => new ClaudeDriver,
            'groq' => new GroqDriver,
            'openrouter' => new OpenRouterDriver,
            'ollama' => new OllamaDriver,
            default => throw new InvalidArgumentException("Unsupported AI provider client: {$providerKey}"),
        };
    }

    /**
     * Create a new provider config.
     */
    public function createProvider(array $data): AIProvider
    {
        try {
            return DB::transaction(function () use ($data) {
                // If this is set as default, unset others first
                if (! empty($data['is_default'])) {
                    AIProvider::where('is_default', true)->update(['is_default' => false]);
                }

                return AIProvider::create($data);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create AI provider: '.$e->getMessage());
            throw new \RuntimeException('Could not save AI provider.', 0, $e);
        }
    }

    /**
     * Update an existing provider configuration.
     */
    public function updateProvider(AIProvider $provider, array $data): AIProvider
    {
        try {
            return DB::transaction(function () use ($provider, $data) {
                if (array_key_exists('api_key', $data)) {
                    $submittedKey = $data['api_key'];
                    $currentMasked = $provider->getMaskedApiKey();
                    if ($submittedKey === '' || is_null($submittedKey) || $submittedKey === '••••••••••••••••••••' || ($currentMasked !== null && $submittedKey === $currentMasked)) {
                        unset($data['api_key']);
                    }
                }

                if (! empty($data['is_default'])) {
                    AIProvider::where('id', '!=', $provider->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $provider->update($data);

                return $provider;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update AI provider: '.$e->getMessage());
            throw new \RuntimeException('Could not update AI provider configuration.', 0, $e);
        }
    }

    /**
     * Toggle provider enabled state.
     */
    public function toggleStatus(AIProvider $provider, bool $isEnabled): AIProvider
    {
        if (! $isEnabled && $provider->is_default) {
            throw new InvalidArgumentException('Cannot disable the default AI provider. Set another default provider first.');
        }

        $provider->update(['is_enabled' => $isEnabled]);

        return $provider;
    }

    /**
     * Set default provider.
     */
    public function setDefault(AIProvider $provider): AIProvider
    {
        if (! $provider->is_enabled) {
            throw new InvalidArgumentException('Cannot set a disabled provider as default.');
        }

        try {
            return DB::transaction(function () use ($provider) {
                AIProvider::where('is_default', true)->update(['is_default' => false]);
                $provider->update(['is_default' => true]);

                return $provider;
            });
        } catch (\Exception $e) {
            Log::error('Failed to set default provider: '.$e->getMessage());
            throw new \RuntimeException('Could not mark AI provider as default.', 0, $e);
        }
    }

    /**
     * Test connection using decrypted credentials.
     */
    public function testConnection(string $providerKey, string $apiKey, ?string $model = null): bool
    {
        $driver = $this->getDriver($providerKey);

        return $driver->testConnection($apiKey, $model);
    }
}
