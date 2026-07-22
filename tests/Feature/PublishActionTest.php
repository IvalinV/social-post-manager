<?php

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\RelationManagers\PostsRelationManager;
use App\Jobs\PublishPost;
use App\Models\Article;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
    $this->article = Article::factory()->create();
});

function postsManager(Article $article)
{
    return Livewire::test(PostsRelationManager::class, [
        'ownerRecord' => $article,
        'pageClass' => EditArticle::class,
    ]);
}

it('dispatches the publish job for a connected, valid draft', function () {
    Queue::fake();
    SocialAccount::factory()->forPlatform(Platform::X)->create();
    $post = Post::factory()->forPlatform(Platform::X)->create([
        'article_id' => $this->article->id,
        'status' => PostStatus::Draft,
        'body' => 'A valid short post.',
    ]);

    postsManager($this->article)
        ->callTableAction('publish', $post)
        ->assertHasNoTableActionErrors();

    Queue::assertPushed(PublishPost::class, fn (PublishPost $job): bool => $job->post->is($post));
});

it('disables the publish action when X is not connected', function () {
    $post = Post::factory()->forPlatform(Platform::X)->create([
        'article_id' => $this->article->id,
        'status' => PostStatus::Draft,
        'body' => 'A valid short post.',
    ]);

    postsManager($this->article)->assertTableActionDisabled('publish', $post);
});

it('disables the publish action when the body is over the limit', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create();
    $post = Post::factory()->forPlatform(Platform::X)->create([
        'article_id' => $this->article->id,
        'status' => PostStatus::Draft,
        'body' => str_repeat('a', 300),
    ]);

    postsManager($this->article)->assertTableActionDisabled('publish', $post);
});

it('disables the publish action when the body is empty', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create();
    $post = Post::factory()->forPlatform(Platform::X)->create([
        'article_id' => $this->article->id,
        'status' => PostStatus::Draft,
        'body' => '',
    ]);

    postsManager($this->article)->assertTableActionDisabled('publish', $post);
});

it('hides the publish action for an already published post', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create();
    $post = Post::factory()->forPlatform(Platform::X)->published()->create([
        'article_id' => $this->article->id,
    ]);

    postsManager($this->article)->assertTableActionHidden('publish', $post);
});
