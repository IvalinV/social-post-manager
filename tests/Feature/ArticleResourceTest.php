<?php

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Jobs\ScrapeArticle;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the article list page', function () {
    Article::factory()->count(3)->create();

    $this->get('/admin/articles')->assertSuccessful();
});

it('renders the create and edit pages', function () {
    $article = Article::factory()->create();

    $this->get('/admin/articles/create')->assertSuccessful();
    $this->get("/admin/articles/{$article->getKey()}/edit")->assertSuccessful();
});

it('queues a scrape when an article is created through the panel', function () {
    Queue::fake();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'source_mode' => 'scrape',
            'url' => 'https://example.com/a-post',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::firstWhere('url', 'https://example.com/a-post');
    expect($article)->not->toBeNull()
        ->and($article->status)->toBe(ArticleStatus::Pending);

    Queue::assertPushed(ScrapeArticle::class, fn (ScrapeArticle $job): bool => $job->article->is($article));
});

it('creates an article manually without requiring a URL or queueing a scrape', function () {
    Queue::fake();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'source_mode' => 'manual',
            'url' => null,
            'title' => 'Manually entered title',
            'author' => 'A. Writer',
            'excerpt' => 'A manually entered summary.',
            'body' => 'The complete manually entered article body.',
            'published_at' => '2026-09-23 12:30:00',
            'og_image_url' => 'https://cdn.example.com/manual.jpg',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::firstWhere('title', 'Manually entered title');
    expect($article)->not->toBeNull()
        ->and($article->url)->toBeNull()
        ->and($article->status)->toBe(ArticleStatus::Scraped)
        ->and($article->author)->toBe('A. Writer')
        ->and($article->excerpt)->toBe('A manually entered summary.')
        ->and($article->body)->toBe('The complete manually entered article body.')
        ->and($article->og_image_url)->toBe('https://cdn.example.com/manual.jpg')
        ->and($article->published_at?->format('Y-m-d H:i:s'))->toBe('2026-09-23 12:30:00');

    Queue::assertNotPushed(ScrapeArticle::class);
});

it('requires a title and body for manually created articles', function () {
    Livewire::test(CreateArticle::class)
        ->fillForm([
            'source_mode' => 'manual',
            'url' => null,
            'title' => null,
            'body' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['title', 'body']);
});
