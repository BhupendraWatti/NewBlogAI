<?php

namespace Tests\Feature;

use App\Modules\AIProviderManager\Contracts\AIProviderClientInterface;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\AIProviderManager\Services\AIProviderService;
use App\Modules\ContentPipeline\Services\LLMCandidateRefinementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LLMCandidateRefinementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refinement_skips_gemini_when_it_is_the_only_available_provider(): void
    {
        $gemini = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Free',
            'api_key' => 'gemini-key',
            'default_model' => 'gemini-2.5-flash',
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $providerService = Mockery::mock(AIProviderService::class);
        $providerService->shouldReceive('getDriver')->never();

        $raw = [$this->candidate('Ujjain civic body announces monsoon drain cleanup')];

        $result = (new LLMCandidateRefinementService($providerService))
            ->refine($raw, $gemini, 'local', 'India');

        $this->assertSame($raw, $result['candidates']);
        $this->assertSame(0, $result['total_tokens']);
        $this->assertSame(0.0, $result['estimated_cost']);
    }

    public function test_refinement_prefers_groq_over_gemini_for_stage_two(): void
    {
        $gemini = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Gemini Free',
            'api_key' => 'gemini-key',
            'default_model' => 'gemini-2.5-flash',
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq Free',
            'api_key' => 'groq-key',
            'default_model' => 'llama-3.1-8b-instant',
            'status' => 'healthy',
            'is_enabled' => true,
        ]);

        $driver = new class implements AIProviderClientInterface
        {
            public function testConnection(string $apiKey, ?string $model = null): bool
            {
                return true;
            }

            public function getConfig(): array
            {
                return [];
            }

            public function generate(string $apiKey, string $prompt, ?string $model = null, array $options = []): array
            {
                return [
                    'text' => json_encode([
                        'results' => [
                            [
                                '_idx' => 0,
                                'keep' => true,
                                'drop_reason' => null,
                                'title' => 'Ujjain civic body starts monsoon drain cleanup',
                                'summary' => 'Officials began a drain cleanup drive in Ujjain before expected monsoon rainfall.',
                                'geo_city' => 'Ujjain',
                                'geo_state' => 'Madhya Pradesh',
                            ],
                        ],
                    ]),
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                    'estimated_cost' => 0.001,
                ];
            }
        };

        $providerService = Mockery::mock(AIProviderService::class);
        $providerService->shouldReceive('getDriver')->once()->with('groq')->andReturn($driver);

        $result = (new LLMCandidateRefinementService($providerService))
            ->refine([$this->candidate('Ujjain civic body announces monsoon drain cleanup')], $gemini, 'local', 'India');

        $this->assertSame(150, $result['total_tokens']);
        $this->assertSame('Ujjain civic body starts monsoon drain cleanup', $result['candidates'][0]['title']);
        $this->assertSame('Ujjain', $result['candidates'][0]['geo_city']);
    }

    private function candidate(string $title): array
    {
        return [
            'title' => $title,
            'summary' => 'A local civic update was reported today.',
            'source_references' => [['name' => 'Local Desk', 'url' => 'https://example.com/story']],
            'keywords' => ['ujjain', 'civic'],
            'trend_score' => 70,
            'freshness_score' => 90,
            'event_date' => now()->toDateString(),
            'geo_city' => null,
            'geo_state' => null,
        ];
    }
}
