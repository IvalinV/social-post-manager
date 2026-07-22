<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\Scraping\ArticleScraper;
use App\Services\Scraping\ScrapeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scrapes a single article. Idempotent — re-running simply re-extracts and
 * overwrites, so transient failures may be retried safely. Permanent failures
 * (4xx, no readable content) are recorded without retrying.
 */
class ScrapeArticle implements ShouldQueue
{
    use Queueable;

    /**
     * Retries apply only to transient failures; permanent ones return early.
     */
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public Article $article) {}

    public function handle(ArticleScraper $scraper): void
    {
        // Clear any prior extraction so a Scraping/Failed article never displays
        // stale content from an earlier successful scrape.
        $this->article->update([
            'status' => ArticleStatus::Scraping,
            'error_message' => null,
            'title' => null,
            'body' => null,
            'excerpt' => null,
            'author' => null,
            'og_image_url' => null,
            'published_at' => null,
        ]);

        try {
            $content = $scraper->scrape($this->article->url);
        } catch (ScrapeException $e) {
            // Permanent: retrying will not help, so stop here.
            $this->markFailed($e->getMessage());

            return;
        }

        // Transient failures (connection/5xx) intentionally bubble out of
        // scrape() so the queue retries; failed() records the final error.

        $this->article->update([
            ...$content->toAttributes(),
            'status' => ArticleStatus::Scraped,
            'error_message' => null,
        ]);
    }

    /**
     * Runs after retries are exhausted for a transient failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    protected function markFailed(string $message): void
    {
        $this->article->update([
            'status' => ArticleStatus::Failed,
            'error_message' => Str::limit($message, 1000),
        ]);
    }
}
