<?php

namespace Tests\Unit;

use App\Modules\AIProviderManager\Drivers\GoogleGeminiDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleGeminiDriverPricingTest extends TestCase
{
    public function test_flash_prices_thinking_tokens_and_disables_thinking_by_default(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'done']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 1000,
                'candidatesTokenCount' => 2000,
                'thoughtsTokenCount' => 3000,
                'totalTokenCount' => 6000,
            ],
        ])]);

        $result = (new GoogleGeminiDriver)->generate('key', 'prompt', 'gemini-2.5-flash');

        $this->assertSame(5000, $result['completion_tokens']);
        $this->assertEqualsWithDelta(0.0128, $result['estimated_cost'], 0.0000001);
        Http::assertSent(fn ($request) => $request['generationConfig']['thinkingConfig']['thinkingBudget'] === 0);
    }
}
