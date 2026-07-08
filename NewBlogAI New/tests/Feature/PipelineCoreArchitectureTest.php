<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ContentPipeline\Contracts\ContentGeneratorInterface;
use App\Modules\ContentPipeline\Contracts\FactExtractorInterface;
use App\Modules\ContentPipeline\Contracts\MediaPreparatorInterface;
use App\Modules\ContentPipeline\Contracts\PipelineStageInterface;
use App\Modules\ContentPipeline\Contracts\PublishingQueueInterface;
use App\Modules\ContentPipeline\Contracts\ResearchServiceInterface;
use App\Modules\ContentPipeline\Contracts\SourceCollectorInterface;
use App\Modules\ContentPipeline\Contracts\TopicResolverInterface;
use App\Modules\ContentPipeline\DTOs\PipelineContext;
use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;
use Tests\TestCase;

class PipelineCoreArchitectureTest extends TestCase
{
    /**
     * Test that all pipeline stage interfaces are properly registered and can be resolved.
     */
    public function test_pipeline_stages_can_be_resolved(): void
    {
        $this->assertInstanceOf(TopicResolverInterface::class, app(TopicResolverInterface::class));
        $this->assertInstanceOf(ResearchServiceInterface::class, app(ResearchServiceInterface::class));
        $this->assertInstanceOf(SourceCollectorInterface::class, app(SourceCollectorInterface::class));
        $this->assertInstanceOf(FactExtractorInterface::class, app(FactExtractorInterface::class));
        $this->assertInstanceOf(ContentGeneratorInterface::class, app(ContentGeneratorInterface::class));
        $this->assertInstanceOf(MediaPreparatorInterface::class, app(MediaPreparatorInterface::class));
        $this->assertInstanceOf(PublishingQueueInterface::class, app(PublishingQueueInterface::class));
    }

    /**
     * Test that PipelineContext DTO can be instantiated and behaves correctly.
     */
    public function test_pipeline_context_dto(): void
    {
        $pipeline = new ContentPipeline();
        $run = new PipelineRun();

        $context = new PipelineContext($run, $pipeline);

        $this->assertSame($run, $context->run);
        $this->assertSame($pipeline, $context->pipeline);
        $this->assertNull($context->resolvedTopic);
        $this->assertEmpty($context->sources);
        $this->assertEmpty($context->researchData);
        $this->assertNull($context->generatedContent);
        $this->assertNull($context->title);
        $this->assertEmpty($context->mediaItems);
        $this->assertEmpty($context->metadata);
        $this->assertEmpty($context->errors);

        // Test modifiers
        $context->resolvedTopic = 'Resolved Topic Name';
        $this->assertEquals('Resolved Topic Name', $context->resolvedTopic);

        $context->addSource(['url' => 'https://example.com/source']);
        $this->assertCount(1, $context->sources);
        $this->assertEquals('https://example.com/source', $context->sources[0]['url']);

        $context->addResearchData('key', 'value');
        $this->assertEquals('value', $context->researchData['key']);

        $context->addMediaItem(['url' => 'https://example.com/image.png']);
        $this->assertCount(1, $context->mediaItems);
        $this->assertEquals('https://example.com/image.png', $context->mediaItems[0]['url']);

        $this->assertFalse($context->hasErrors());
        $context->addError('research', 'Failed to retrieve research');
        $this->assertTrue($context->hasErrors());
        $this->assertEquals('Failed to retrieve research', $context->errors['research'][0]);
    }
}
