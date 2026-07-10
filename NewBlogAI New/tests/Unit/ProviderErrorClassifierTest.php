<?php

namespace Tests\Unit;

use App\Modules\AIProviderManager\Support\ProviderErrorClassifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the failover retry policy: permanent failures (bad key, connection
 * refused) must NOT be retried, while transient ones (rate limit, 5xx,
 * timeout) must be. Retrying permanent failures wastes back-off time and,
 * on the generation path, real API tokens.
 */
class ProviderErrorClassifierTest extends TestCase
{
    public function test_auth_and_client_errors_are_not_retryable(): void
    {
        $this->assertFalse($this->retryable('OpenAI API error: Status 401 - invalid key'));
        $this->assertFalse($this->retryable('Claude API error: Status 401 - invalid x-api-key'));
        $this->assertFalse($this->retryable('OpenRouter API error: Status 401 - Missing Authentication header'));
        $this->assertFalse($this->retryable('Provider API error: Status 403 - forbidden'));
        $this->assertFalse($this->retryable('Provider API error: Status 404 - model not found'));
        $this->assertFalse($this->retryable('Provider API error: Status 400 - bad request'));
    }

    public function test_connection_refused_is_not_retryable(): void
    {
        $this->assertFalse($this->retryable(
            'Ollama generation failed: cURL error 7: Failed to connect to localhost port 11434'
        ));
    }

    public function test_rate_limit_and_server_errors_are_retryable(): void
    {
        $this->assertTrue($this->retryable('Groq API error: Status 429 - rate limit reached'));
        $this->assertTrue($this->retryable('Gemini API error: Status 503 - overloaded'));
        $this->assertTrue($this->retryable('OpenAI API error: Status 500 - internal error'));
        $this->assertTrue($this->retryable('Provider API error: Status 529 - overloaded'));
    }

    public function test_timeouts_are_retryable(): void
    {
        $this->assertTrue($this->retryable('Maximum execution time of 60 seconds exceeded'));
        $this->assertTrue($this->retryable('cURL error 28: Operation timed out'));
    }

    public function test_unknown_errors_are_not_retryable(): void
    {
        // Unparseable errors shouldn't burn back-off cycles — fail over instead.
        $this->assertFalse($this->retryable('Something completely unexpected happened'));
    }

    public function test_reason_distinguishes_config_from_throttling(): void
    {
        $this->assertStringContainsString('API key', ProviderErrorClassifier::reason(
            new RuntimeException('OpenAI API error: Status 401 - invalid')
        ));
        $this->assertStringContainsString('rate limited', ProviderErrorClassifier::reason(
            new RuntimeException('Groq API error: Status 429 - too many')
        ));
        $this->assertStringContainsString('connection refused', ProviderErrorClassifier::reason(
            new RuntimeException('Ollama generation failed: cURL error 7: refused')
        ));
    }

    private function retryable(string $message): bool
    {
        return ProviderErrorClassifier::isRetryable(new RuntimeException($message));
    }
}
