<?php

use App\Services\Scraping\ArticleScraper;
use App\Services\Scraping\ScrapeException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Builds an article-shaped HTML document with enough body text for Readability
 * to treat it as the main content.
 */
function articleHtml(string $body = ''): string
{
    $paragraph = str_repeat('This is a meaningful sentence about the subject matter of the article. ', 12);
    $body = $body !== '' ? $body : "<p>{$paragraph}</p><p>{$paragraph}</p><p>{$paragraph}</p>";

    return <<<HTML
    <!doctype html>
    <html>
    <head>
        <title>The Headline of the Piece</title>
        <meta property="og:image" content="https://cdn.example.com/hero.jpg">
        <meta name="author" content="Jane Journalist">
        <meta property="article:published_time" content="2026-03-14T09:30:00Z">
    </head>
    <body>
        <nav>Home About Contact</nav>
        <article>
            <h1>The Headline of the Piece</h1>
            {$body}
        </article>
        <footer>Copyright 2026</footer>
    </body>
    </html>
    HTML;
}

it('extracts title, body, image and published date from article HTML', function () {
    $content = app(ArticleScraper::class)->extract(articleHtml(), 'https://example.com/post');

    expect($content->title)->toContain('Headline of the Piece')
        ->and($content->body)->toContain('meaningful sentence')
        ->and($content->body)->not->toContain('<p>')
        ->and($content->ogImageUrl)->toBe('https://cdn.example.com/hero.jpg')
        ->and($content->publishedAt?->toDateString())->toBe('2026-03-14');
});

it('throws a ScrapeException when the HTML has no readable content', function () {
    $scraper = app(ArticleScraper::class);

    expect(fn () => $scraper->extract('<html><body><nav>menu</nav></body></html>', 'https://example.com'))
        ->toThrow(ScrapeException::class);
});

it('throws a ScrapeException on an empty document', function () {
    expect(fn () => app(ArticleScraper::class)->extract('   ', 'https://example.com'))
        ->toThrow(ScrapeException::class);
});

it('decodes HTML entities and separates adjacent blocks in the body', function () {
    $sentence = str_repeat('Reasonably long sentence to clear the content threshold here. ', 4);
    $html = articleHtml("<p>Tom &amp; Jerry&#39;s &quot;big&quot; day.</p><p>{$sentence}</p><p>{$sentence}</p>");

    $content = app(ArticleScraper::class)->extract($html, 'https://example.com/post');

    expect($content->body)->toContain('Tom & Jerry\'s "big" day.')
        ->and($content->body)->not->toContain('&amp;')
        ->and($content->body)->not->toContain('day.Reasonably'); // blocks didn't merge
});

it('rejects non-http and internal URLs before fetching', function (string $url) {
    Http::fake();

    expect(fn () => app(ArticleScraper::class)->scrape($url))
        ->toThrow(ScrapeException::class);

    Http::assertNothingSent();
})->with([
    'ftp scheme' => ['ftp://example.com/file'],
    'file scheme' => ['file:///etc/passwd'],
    'cloud metadata IP' => ['http://169.254.169.254/latest/meta-data/'],
    'loopback IP' => ['http://127.0.0.1/admin'],
    'private IP' => ['http://192.168.1.1/'],
    'localhost' => ['http://localhost:8000/'],
]);

it('fetches and extracts a live URL', function () {
    Http::fake(['*' => Http::response(articleHtml(), 200)]);

    $content = app(ArticleScraper::class)->scrape('https://example.com/post');

    expect($content->title)->toContain('Headline of the Piece');

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('social.scraping.user_agent')));
});

it('treats a 4xx response as a permanent ScrapeException', function () {
    Http::fake(['*' => Http::response('Not found', 404)]);

    expect(fn () => app(ArticleScraper::class)->scrape('https://example.com/missing'))
        ->toThrow(ScrapeException::class);
});

it('lets a 5xx response bubble as a retryable RequestException', function () {
    Http::fake(['*' => Http::response('Server error', 503)]);

    expect(fn () => app(ArticleScraper::class)->scrape('https://example.com/down'))
        ->toThrow(RequestException::class);
});
