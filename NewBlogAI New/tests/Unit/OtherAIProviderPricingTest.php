<?php

namespace Tests\Unit;

use App\Modules\AIProviderManager\Drivers\ClaudeDriver;
use App\Modules\AIProviderManager\Drivers\GroqDriver;
use App\Modules\AIProviderManager\Drivers\OpenAIDriver;
use App\Modules\AIProviderManager\Drivers\OpenRouterDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtherAIProviderPricingTest extends TestCase
{
    public function test_openai_applies_cache_read_and_write_pricing(): void
    {
        Http::fake(['*' => Http::response($this->openAIResponse([
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 1500,
            'prompt_tokens_details' => ['cached_tokens' => 400, 'cache_write_tokens' => 200],
        ]))]);

        $result = (new OpenAIDriver)->generate('key', 'prompt', 'gpt-5.6-luna');

        $this->assertEqualsWithDelta(0.000738, $result['estimated_cost'], 0.0000001);
    }

    public function test_claude_prices_cache_reads_and_writes(): void
    {
        Http::fake(['*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'done']],
            'usage' => [
                'input_tokens' => 1000,
                'output_tokens' => 500,
                'cache_creation_input_tokens' => 300,
                'cache_read_input_tokens' => 400,
                'cache_creation' => [
                    'ephemeral_5m_input_tokens' => 200,
                    'ephemeral_1h_input_tokens' => 100,
                ],
            ],
        ])]);

        $result = (new ClaudeDriver)->generate('key', 'prompt', 'claude-3-5-sonnet-20241022');

        $this->assertSame(1700, $result['prompt_tokens']);
        $this->assertEqualsWithDelta(0.01197, $result['estimated_cost'], 0.0000001);
    }

    public function test_groq_applies_automatic_cache_discount(): void
    {
        Http::fake(['*' => Http::response($this->openAIResponse([
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 1500,
            'prompt_tokens_details' => ['cached_tokens' => 400],
        ]))]);

        $result = (new GroqDriver)->generate('key', 'prompt', 'openai/gpt-oss-120b', ['max_retries' => 0]);

        $this->assertEqualsWithDelta(0.00042, $result['estimated_cost'], 0.0000001);
    }

    public function test_openrouter_uses_native_charged_cost(): void
    {
        Http::fake(['*' => Http::response($this->openAIResponse([
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 1500,
            'cost' => 0.1234,
        ]))]);

        $result = (new OpenRouterDriver)->generate('key', 'prompt', 'openai/gpt-4o');

        $this->assertSame(0.1234, $result['estimated_cost']);
        $this->assertSame('provider_reported', $result['cost_accuracy']);
    }

    private function openAIResponse(array $usage): array
    {
        return [
            'choices' => [['message' => ['content' => 'done']]],
            'usage' => $usage,
        ];
    }
}
