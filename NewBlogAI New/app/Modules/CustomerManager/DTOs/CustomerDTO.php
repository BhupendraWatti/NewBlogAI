<?php

namespace App\Modules\CustomerManager\DTOs;

class CustomerDTO
{
    public function __construct(
        public string $companyName,
        public string $ownerName,
        public string $email,
        public ?string $phone = null,
        public ?string $country = null,
        public string $timezone = 'UTC',
        public string $language = 'en',
        public ?string $companyLogo = null,
        public ?string $website = null,
        public ?string $industry = null,
        public string $status = 'trial',
        public ?array $tags = null,
        public ?string $notes = null
    ) {}

    /**
     * Build DTO from request validated payload.
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            companyName: $validated['company_name'],
            ownerName: $validated['owner_name'],
            email: $validated['email'],
            phone: $validated['phone'] ?? null,
            country: $validated['country'] ?? null,
            timezone: $validated['timezone'] ?? 'UTC',
            language: $validated['language'] ?? 'en',
            companyLogo: $validated['company_logo'] ?? null,
            website: $validated['website'] ?? null,
            industry: $validated['industry'] ?? null,
            status: $validated['status'] ?? 'trial',
            tags: $validated['tags'] ?? null,
            notes: $validated['notes'] ?? null
        );
    }

    /**
     * Convert DTO to storage array.
     */
    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'owner_name'   => $this->ownerName,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'country'      => $this->country,
            'timezone'     => $this->timezone,
            'language'     => $this->language,
            'company_logo' => $this->companyLogo,
            'website'      => $this->website,
            'industry'     => $this->industry,
            'status'       => $this->status,
            'tags'         => $this->tags
        ];
    }
}
