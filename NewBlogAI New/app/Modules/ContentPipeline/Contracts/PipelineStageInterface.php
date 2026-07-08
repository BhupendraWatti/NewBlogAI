<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Contracts;

use App\Modules\ContentPipeline\DTOs\PipelineContext;

interface PipelineStageInterface
{
    /**
     * Process the current stage of the content pipeline.
     */
    public function handle(PipelineContext $context): PipelineContext;
}
