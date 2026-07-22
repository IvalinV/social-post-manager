<?php

use App\Enums\ArticleStatus;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\RelationManagers\PostsRelationManager;
use App\Models\Article;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('generates one draft per connectable platform from templates', function () {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Scraped,
        'title' => 'Headline',
        'excerpt' => 'Summary text.',
    ]);

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ])->callTableAction('generateDrafts');

    $posts = $article->posts()->get();
    // Only X is connectable; LinkedIn is skipped.
    expect($posts)->toHaveCount(1)
        ->and($posts->first()->platform)->toBe(Platform::X)
        ->and($posts->first()->status)->toBe(PostStatus::Draft)
        ->and($posts->first()->body)->toContain('Headline');
});

it('does not duplicate drafts when generated twice', function () {
    $article = Article::factory()->create();
    Post::factory()->forPlatform(Platform::X)->create(['article_id' => $article->id]);

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ])->callTableAction('generateDrafts');

    expect($article->posts()->where('platform', Platform::X)->count())->toBe(1);
});

it('rejects a body that exceeds the platform limit', function () {
    $article = Article::factory()->create();

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ])->callTableAction('create', data: [
        'platform' => Platform::X->value,
        'body' => str_repeat('a', 281),
    ])->assertHasTableActionErrors(['body']);

    expect($article->posts()->count())->toBe(0);
});

it('creates a valid draft post through the create action', function () {
    $article = Article::factory()->create();

    Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ])->callTableAction('create', data: [
        'platform' => Platform::X->value,
        'body' => 'A short, valid post body.',
    ])->assertHasNoTableActionErrors();

    $post = $article->posts()->first();
    expect($post)->not->toBeNull()
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->platform)->toBe(Platform::X);
});
