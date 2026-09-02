<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\Exceptions;

use RuntimeException;

class SourceEvidenceException extends RuntimeException
{
    // A source-access failure is independent of the selected AI provider.
}
