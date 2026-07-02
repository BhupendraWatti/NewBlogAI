<?php

namespace App\Modules\AIProviderManager\Contracts;

interface AIProviderClientInterface
{
    /**
     * Test the API connection.
     *
     * @param string $apiKey
     * @param string|null $model
     * @return bool
     */
    public function testConnection(string $apiKey, ?string $model = null): bool;

    /**
     * Get the default endpoint or custom configuration.
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Generate content from prompt.
     *
     * @param string $apiKey
     * @param string $prompt
     * @param string|null $model
     * @param array $options
     * @return array Must return [
     *    'text'              => string,
     *    'prompt_tokens'     => int|null,
     *    'completion_tokens' => int|null,
     *    'total_tokens'      => int|null,
     *    'estimated_cost'    => float,
     *    'raw_response'      => array
     * ]
     */
    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array;
}
