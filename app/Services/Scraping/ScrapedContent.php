<?php

namespace App\Services\Scraping;

use Carbon\CarbonImmutable;

/**
 * Immutable result of extracting readable content from a fetched page.
 */
readonly class ScrapedContent
{
    public function __construct(
        public ?string $title,
        public ?string $body,
        public ?string $excerpt,
        public ?string $author,
        public ?string $ogImageUrl,
        public ?CarbonImmutable $publishedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'excerpt' => $this->excerpt,
            'author' => $this->author,
            'og_image_url' => $this->ogImageUrl,
            'published_at' => $this->publishedAt,
        ];
    }
}
