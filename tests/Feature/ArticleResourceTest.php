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
        ->fillForm(['url' => 'https://example.com/a-post'])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::firstWhere('url', 'https://example.com/a-post');
    expect($article)->not->toBeNull()
        ->and($article->status)->toBe(ArticleStatus::Pending);

    Queue::assertPushed(ScrapeArticle::class, fn (ScrapeArticle $job): bool => $job->article->is($article));
});
