<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\DTOs;

use App\Modules\ContentPipeline\Models\ContentPipeline;
use App\Modules\ContentPipeline\Models\PipelineRun;

class PipelineContext
{
    public function __construct(
        public PipelineRun $run,
        public ContentPipeline $pipeline,
        public ?string $resolvedTopic = null,
        public array $sources = [],
        public array $researchData = [],
        public ?string $generatedContent = null,
        public ?string $title = null,
        public array $mediaItems = [],
        public array $metadata = [],
        public array $errors = []
    ) {}

    /**
     * Add a source to the context.
     */
    public function addSource(SourceDTO|array $source): self
    {
        $this->sources[] = $source instanceof SourceDTO ? $source : SourceDTO::fromArray($source);
        return $this;
    }

    /**
     * Add research data to the context.
     */
    public function addResearchData(string $key, mixed $value): self
    {
        $this->researchData[$key] = $value;
        return $this;
    }

    /**
     * Add a media item to the context.
     */
    public function addMediaItem(array $mediaItem): self
    {
        $this->mediaItems[] = $mediaItem;
        return $this;
    }

    /**
     * Add an error to the context.
     */
    public function addError(string $stage, string $message): self
    {
        $this->errors[$stage][] = $message;
        return $this;
    }

    /**
     * Determine if the context has any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
