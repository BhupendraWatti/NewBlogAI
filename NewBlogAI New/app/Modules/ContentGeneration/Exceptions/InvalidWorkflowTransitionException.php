<?php

namespace App\Modules\ContentGeneration\Exceptions;

use InvalidArgumentException;

class InvalidWorkflowTransitionException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $fromStatus,
        public readonly string $toStatus
    ) {
        parent::__construct($message);
    }
}
