<?php

use App\Enums\Platform;
use App\Models\Article;
use App\Services\Posts\PostComposer;

function article(array $attributes = []): Article
{
    return new Article(array_merge([
        'title' => 'A Reasonable Headline',
        'excerpt' => 'A short summary of the article that gives the reader some context.',
        'url' => 'https://example.com/some/really/long/path/to/the/article-slug-here',
    ], $attributes));
}

beforeEach(function () {
    $this->composer = app(PostComposer::class);
});

it('renders title, excerpt and url within the LinkedIn limit', function () {
    $body = $this->composer->render(article(), Platform::LinkedIn);

    expect($body)->toContain('A Reasonable Headline')
        ->and($body)->toContain('short summary')
        ->and($body)->toContain('https://example.com/some/really/long/path/to/the/article-slug-here')
        ->and($this->composer->fits(Platform::LinkedIn, $body))->toBeTrue();
});

it('counts every URL on X as a fixed 23 characters', function () {
    $url = 'https://example.com/'.str_repeat('x', 200); // 220 chars
    $weighted = $this->composer->weightedLength(Platform::X, "hi {$url}");

    // "hi " (3) + url counted as 23 = 26, not 3 + 220.
    expect($weighted)->toBe(26);
});

it('counts real URL length on LinkedIn', function () {
    $url = 'https://example.com/'.str_repeat('x', 200);
    $weighted = $this->composer->weightedLength(Platform::LinkedIn, $url);

    expect($weighted)->toBe(mb_strlen($url));
});

it('keeps an X post within 280 weighted characters and preserves the url', function () {
    $body = $this->composer->render(article([
        'excerpt' => str_repeat('This is a long excerpt sentence. ', 30),
    ]), Platform::X);

    expect($this->composer->weightedLength(Platform::X, $body))->toBeLessThanOrEqual(280)
        ->and($body)->toEndWith('https://example.com/some/really/long/path/to/the/article-slug-here')
        ->and($body)->toContain('…'); // excerpt was truncated
});

it('drops the excerpt and truncates the title when title plus url already fill the limit', function () {
    $body = $this->composer->render(article([
        'title' => str_repeat('Very long title word ', 40),
    ]), Platform::X);

    expect($this->composer->fits(Platform::X, $body))->toBeTrue()
        ->and($body)->toEndWith('https://example.com/some/really/long/path/to/the/article-slug-here')
        ->and($body)->toContain('…');
});

it('counts a URL shorter than 23 chars up to the full 23 on X', function () {
    // t.co rule: even a short URL is weighted as 23.
    expect($this->composer->weightedLength(Platform::X, 'https://x.io'))->toBe(23);
});

it('counts multiple URLs each at the fixed weight on X', function () {
    $text = 'see https://a.io and https://b.io';
    // "see " (4) + 23 + " and " (5) + 23 = 55
    expect($this->composer->weightedLength(Platform::X, $text))->toBe(55);
});

it('never exceeds the X limit even when the excerpt embeds a short URL', function () {
    // A short URL in the excerpt weighs up to 23 though it consumes few raw chars.
    $body = $this->composer->render(article([
        'title' => null,
        'excerpt' => 'https://x.io '.str_repeat('a', 240),
    ]), Platform::X);

    expect($this->composer->weightedLength(Platform::X, $body))->toBeLessThanOrEqual(280);
});

it('renders excerpt and url when there is no title', function () {
    $body = $this->composer->render(article(['title' => null]), Platform::LinkedIn);

    expect($body)->toContain('short summary')
        ->and($body)->toEndWith('article-slug-here')
        ->and($body)->not->toContain('A Reasonable Headline');
});

it('renders just the url when there is no title or excerpt', function () {
    $body = $this->composer->render(article(['title' => null, 'excerpt' => null]), Platform::X);

    expect($body)->toBe('https://example.com/some/really/long/path/to/the/article-slug-here');
});

it('reports remaining characters and fit', function () {
    expect($this->composer->remaining(Platform::X, 'hello'))->toBe(275)
        ->and($this->composer->fits(Platform::X, str_repeat('a', 281)))->toBeFalse()
        ->and($this->composer->fits(Platform::X, str_repeat('a', 280)))->toBeTrue();
});
