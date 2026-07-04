<?php

namespace App\Modules\SubscriptionManager\Exceptions;

use DomainException;

class EntitlementDeniedException extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $entitlement,
        public readonly int|string|null $limit = null,
        public readonly int|string|null $usage = null,
    ) {
        parent::__construct($message);
    }

    public function context(): array
    {
        return array_filter([
            'entitlement' => $this->entitlement,
            'limit' => $this->limit,
            'usage' => $this->usage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
