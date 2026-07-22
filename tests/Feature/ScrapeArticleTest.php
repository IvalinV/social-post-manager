<?php

use App\Enums\ArticleStatus;
use App\Jobs\ScrapeArticle;
use App\Models\Article;
use App\Services\Scraping\ArticleScraper;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('populates the article and marks it scraped on success', function () {
    Http::fake(['*' => Http::response(articleHtml(), 200)]);
    $article = Article::factory()->pending()->create(['url' => 'https://example.com/post']);

    (new ScrapeArticle($article))->handle(app(ArticleScraper::class));

    $article->refresh();
    expect($article->status)->toBe(ArticleStatus::Scraped)
        ->and($article->title)->toContain('Headline of the Piece')
        ->and($article->body)->toContain('meaningful sentence')
        ->and($article->og_image_url)->toBe('https://cdn.example.com/hero.jpg')
        ->and($article->error_message)->toBeNull();
});

it('marks the article failed without retrying on a permanent error', function () {
    Http::fake(['*' => Http::response('Gone', 404)]);
    $article = Article::factory()->pending()->create(['url' => 'https://example.com/missing']);

    // handle() must NOT throw for a permanent failure (throwing would retry).
    (new ScrapeArticle($article))->handle(app(ArticleScraper::class));

    $article->refresh();
    expect($article->status)->toBe(ArticleStatus::Failed)
        ->and($article->error_message)->not->toBeNull();
});

it('rethrows transient failures so the queue can retry', function () {
    Http::fake(['*' => Http::response('Down', 503)]);
    $article = Article::factory()->pending()->create(['url' => 'https://example.com/down']);

    expect(fn () => (new ScrapeArticle($article))->handle(app(ArticleScraper::class)))
        ->toThrow(RequestException::class);

    // Left in the scraping state; the eventual failure is recorded by failed().
    expect($article->refresh()->status)->toBe(ArticleStatus::Scraping);
});

it('records the final error when retries are exhausted', function () {
    $article = Article::factory()->pending()->create();

    (new ScrapeArticle($article))->failed(new RuntimeException('connection timed out'));

    $article->refresh();
    expect($article->status)->toBe(ArticleStatus::Failed)
        ->and($article->error_message)->toBe('connection timed out');
});

it('clears stale content when a re-scrape ends in failure', function () {
    Http::fake(['*' => Http::response('Gone', 404)]);
    // Previously scraped article still holding old content.
    $article = Article::factory()->create([
        'url' => 'https://example.com/post',
        'title' => 'Old title',
        'body' => 'Old body content',
    ]);

    (new ScrapeArticle($article))->handle(app(ArticleScraper::class));

    $article->refresh();
    expect($article->status)->toBe(ArticleStatus::Failed)
        ->and($article->title)->toBeNull()
        ->and($article->body)->toBeNull();
});

it('is configured for bounded retries', function () {
    $job = new ScrapeArticle(Article::factory()->pending()->create());

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60]);
});
