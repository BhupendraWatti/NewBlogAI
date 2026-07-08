<?php

declare(strict_types=1);

namespace App\Modules\ContentPipeline\DTOs;

use ArrayAccess;
use JsonSerializable;

class SourceDTO implements ArrayAccess, JsonSerializable
{
    public string $url;
    public ?string $title;
    public ?string $snippet;
    public ?string $publisher;
    public ?string $author;
    public ?string $publishedDate;
    public float $relevanceScore;
    public array $keywords;
    public array $metadata;

    public function __construct(
        string $url,
        ?string $title = null,
        ?string $snippet = null,
        ?string $publisher = null,
        ?string $author = null,
        ?string $publishedDate = null,
        float $relevanceScore = 0.0,
        array $keywords = [],
        array $metadata = []
    ) {
        $this->url = $url;
        $this->title = $title;
        $this->snippet = $snippet;
        $this->publisher = $publisher;
        $this->author = $author;
        $this->publishedDate = $publishedDate;
        $this->relevanceScore = $relevanceScore;
        $this->keywords = $keywords;
        $this->metadata = $metadata;
    }

    /**
     * Create a DTO from an array.
     */
    public static function fromArray(array $data): self
    {
        $metadata = $data['metadata'] ?? [];

        $url = $data['url'] ?? '';
        $title = $data['title'] ?? null;
        $snippet = $data['snippet'] ?? null;

        $publisher = $data['publisher'] ?? $metadata['publisher'] ?? null;
        $author = $data['author'] ?? $metadata['author'] ?? null;
        $publishedDate = $data['published_date'] ?? $metadata['published_date'] ?? $data['publishedDate'] ?? $metadata['publishedDate'] ?? null;
        $relevanceScore = (float) ($data['relevance_score'] ?? $metadata['relevance_score'] ?? $data['relevanceScore'] ?? $metadata['relevanceScore'] ?? 0.0);
        $keywords = $data['keywords'] ?? $metadata['keywords'] ?? $data['keywords'] ?? [];

        $customMetadata = $metadata;
        unset($customMetadata['publisher'], $customMetadata['author'], $customMetadata['published_date'], $customMetadata['relevance_score'], $customMetadata['keywords']);

        return new self(
            url: $url,
            title: $title,
            snippet: $snippet,
            publisher: $publisher,
            author: $author,
            publishedDate: $publishedDate,
            relevanceScore: $relevanceScore,
            keywords: $keywords,
            metadata: $customMetadata
        );
    }

    /**
     * Convert the DTO to an array.
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'snippet' => $this->snippet,
            'publisher' => $this->publisher,
            'author' => $this->author,
            'published_date' => $this->publishedDate,
            'relevance_score' => $this->relevanceScore,
            'keywords' => $this->keywords,
            // To maintain full compatibility with legacy code accessing $source['metadata']
            'metadata' => array_merge([
                'publisher' => $this->publisher,
                'author' => $this->author,
                'published_date' => $this->publishedDate,
                'relevance_score' => $this->relevanceScore,
                'keywords' => $this->keywords,
            ], $this->metadata)
        ];
    }

    /**
     * ArrayAccess Implementation
     */
    public function offsetExists(mixed $offset): bool
    {
        $array = $this->toArray();
        return isset($array[$offset]) || array_key_exists($offset, $array);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $array = $this->toArray();
        return $array[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        switch ($offset) {
            case 'url':
                $this->url = (string) $value;
                break;
            case 'title':
                $this->title = $value;
                break;
            case 'snippet':
                $this->snippet = $value;
                break;
            case 'publisher':
                $this->publisher = $value;
                break;
            case 'author':
                $this->author = $value;
                break;
            case 'published_date':
            case 'publishedDate':
                $this->publishedDate = $value;
                break;
            case 'relevance_score':
            case 'relevanceScore':
                $this->relevanceScore = (float) $value;
                break;
            case 'keywords':
                $this->keywords = (array) $value;
                break;
            case 'metadata':
                $this->metadata = (array) $value;
                break;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->offsetSet($offset, null);
    }

    /**
     * JsonSerializable Implementation
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
