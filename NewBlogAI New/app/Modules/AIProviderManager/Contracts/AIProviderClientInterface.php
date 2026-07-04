<?php

namespace App\Modules\AIProviderManager\Contracts;

interface AIProviderClientInterface
{
    /**
     * Test the API connection.
     */
    public function testConnection(string $apiKey, ?string $model = null): bool;

    /**
     * Get the default endpoint or custom configuration.
     */
    public function getConfig(): array;

    /**
     * Generate content from prompt.
     *
     * @return array Must return [
     *               'text'              => string,
     *               'prompt_tokens'     => int|null,
     *               'completion_tokens' => int|null,
     *               'total_tokens'      => int|null,
     *               'estimated_cost'    => float,
     *               'raw_response'      => array
     *               ]
     */
    public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array;
}
