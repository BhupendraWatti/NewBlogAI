<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\DTOs\SourceDTO;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use App\Modules\ContentPipeline\Services\PromptEngine;
use App\Modules\SiteManager\Models\Site;
use App\Modules\TopicManager\Models\Topic;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\AIProviderManager\Models\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptEngineImprovementTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;
    protected Topic $topic;
    protected Prompt $prompt;
    protected AIProvider $provider;
    protected ContentPipeline $pipeline;
    protected PipelineRun $run;
    protected PromptEngine $promptEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'domain_url' => 'https://example-prompt-test.com',
            'api_key' => 'test-token',
            'is_active' => true,
        ]);

        $this->topic = Topic::create([
            'name' => 'Laravel 12 Architecture',
            'category' => 'Tech',
            'language' => 'en',
            'status' => 'active',
            'generation_frequency' => 'daily',
        ]);

        $this->prompt = Prompt::create([
            'name' => 'Test Prompt Template',
            'prompt' => 'Write about {{topic}} for {{website}} in {{language}} under category {{category}}.',
            'category' => 'Tech',
            'status' => 'active',
        ]);

        $this->provider = AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'some-api-key',
            'default_model' => 'gemini-2.5-flash',
            'is_enabled' => true,
        ]);

        $this->pipeline = ContentPipeline::create([
            'site_id' => $this->site->id,
            'topic_id' => $this->topic->id,
            'prompt_id' => $this->prompt->id,
            'ai_provider_id' => $this->provider->id,
            'language' => 'en',
            'generation_type' => 'article',
            'is_active' => true,
        ]);

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'processing',
        ]);

        $this->promptEngine = app(PromptEngine::class);
    }

    public function test_compile_system_prompt_supports_overrides(): void
    {
        // Default system prompt
        $default = $this->promptEngine->compileSystemPrompt();
        $this->assertStringContainsString('You are a professional, expert copywriter', $default);

        // Custom system prompt override
        $custom = $this->promptEngine->compileSystemPrompt(['persona' => 'You are a minimalist tech writer.']);
        $this->assertEquals('You are a minimalist tech writer.', $custom);
    }

    public function test_compile_research_context_renders_details_and_clusters(): void
    {
        $context = new PipelineContext($this->run, $this->pipeline);
        
        $source1 = new SourceDTO(
            url: 'https://laravel.com/doc/12',
            title: 'Laravel 12 Docs',
            snippet: 'Introduction to modular architecture.',
            publisher: 'Laravel Team',
            publishedDate: '2026-03-01',
            metadata: ['region' => 'US', 'locale' => 'en-US']
        );
        $context->addSource($source1);

        // Add topic cluster metadata
        $context->metadata['topic_clusters'] = [
            'Modular Architecture' => ['https://laravel.com/doc/12']
        ];

        $compiled = $this->promptEngine->compileResearchContext($context);

        $this->assertStringContainsString('Laravel 12 Docs', $compiled);
        $this->assertStringContainsString('https://laravel.com/doc/12', $compiled);
        $this->assertStringContainsString('Publisher: Laravel Team', $compiled);
        $this->assertStringContainsString('Date: 2026-03-01', $compiled);
        $this->assertStringContainsString('Region: US (en-US)', $compiled);
        $this->assertStringContainsString('Topic Clusters:', $compiled);
        $this->assertStringContainsString('Modular Architecture:', $compiled);
    }

    public function test_compile_context_injection_renders_extracted_facts(): void
    {
        $context = new PipelineContext($this->run, $this->pipeline);
        
        $facts = [
            'people' => ['Taylor Otwell'],
            'organizations' => ['Laravel LLC'],
            'locations' => ['USA'],
            'dates' => ['2026'],
            'events' => ['Laracon US 2026'],
            'keywords' => ['PHP', 'Framework']
        ];
        $context->metadata['extracted_facts'] = $facts;

        $compiled = $this->promptEngine->compileContextInjection($context);

        $this->assertStringContainsString('People: Taylor Otwell', $compiled);
        $this->assertStringContainsString('Organizations: Laravel LLC', $compiled);
        $this->assertStringContainsString('Locations: USA', $compiled);
        $this->assertStringContainsString('Dates: 2026', $compiled);
        $this->assertStringContainsString('Events: Laracon US 2026', $compiled);
        $this->assertStringContainsString('Key Terms: PHP, Framework', $compiled);
    }

    public function test_compile_user_prompt_interpolates_variables(): void
    {
        $template = 'Write about {{topic}} for {{website}} in {{language}} under category {{category}}.';
        $variables = [
            'topic' => 'Laravel 12 Features',
            'website' => 'https://laravel-news.com',
            'language' => 'en',
            'category' => 'Development'
        ];

        $compiled = $this->promptEngine->compileUserPrompt($template, $variables);

        $this->assertEquals(
            'Write about Laravel 12 Features for https://laravel-news.com in en under category Development.',
            $compiled
        );
    }

    public function test_compile_dynamic_instructions_based_on_context(): void
    {
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->metadata['language'] = 'fr';
        $context->metadata['locale'] = 'fr-FR';
        $context->metadata['style_guide'] = 'Write in short paragraphs.';
        $context->metadata['tone'] = 'professional and technical';
        $context->metadata['dynamic_instructions'] = 'Add code examples where possible.';

        $compiled = $this->promptEngine->compileDynamicInstructions($context);

        $this->assertStringContainsString("Language: The content must be written in language code 'fr'.", $compiled);
        $this->assertStringContainsString("Locale: Target audience locale is 'fr-FR'.", $compiled);
        $this->assertStringContainsString('Style Guide: Write in short paragraphs.', $compiled);
        $this->assertStringContainsString('Tone: Write with a professional and technical tone.', $compiled);
        $this->assertStringContainsString('Additional Guidelines: Add code examples where possible.', $compiled);
    }

    public function test_compile_output_instructions_formats_markdown_instructions(): void
    {
        $default = $this->promptEngine->compileOutputInstructions();
        $this->assertStringContainsString('Format the article using clean, readable Markdown.', $default);

        $custom = $this->promptEngine->compileOutputInstructions([
            'additional_output_instructions' => 'Include a brief conclusion.'
        ]);
        $this->assertStringContainsString('Include a brief conclusion.', $custom);
    }

    public function test_build_full_prompt_combines_all_sections(): void
    {
        $context = new PipelineContext($this->run, $this->pipeline);
        $context->metadata['extracted_facts'] = [
            'people' => ['Taylor Otwell']
        ];
        $context->metadata['tone'] = 'informative';

        $template = 'Focus on {{topic}}.';
        $variables = ['topic' => 'Laravel 12'];

        $fullPrompt = $this->promptEngine->buildFullPrompt($context, $template, $variables);

        $this->assertStringContainsString('System Prompt:', $fullPrompt);
        $this->assertStringContainsString('Research Context:', $fullPrompt);
        $this->assertStringContainsString('Context Injection:', $fullPrompt);
        $this->assertStringContainsString('User Prompt:', $fullPrompt);
        $this->assertStringContainsString('Focus on Laravel 12.', $fullPrompt);
        $this->assertStringContainsString('Dynamic Instructions:', $fullPrompt);
        $this->assertStringContainsString('Tone: Write with a informative tone.', $fullPrompt);
        $this->assertStringContainsString('Output Instructions:', $fullPrompt);
    }
}
