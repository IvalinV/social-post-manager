<?php

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Publishing\PublisherFactory;
use Illuminate\Support\Facades\Http;

function runPublish(Post $post): void
{
    (new PublishPost($post))->handle(app(PublisherFactory::class));
}

it('never auto-retries (tries = 1)', function () {
    expect((new PublishPost(Post::factory()->create()))->tries)->toBe(1);
});

it('publishes a draft and records the platform id and url', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'account_handle' => 'me',
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '999']], 201)]);

    $post = Post::factory()->forPlatform(Platform::X)->create(['status' => PostStatus::Draft, 'body' => 'hi']);

    runPublish($post);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->platform_post_id)->toBe('999')
        ->and($post->platform_url)->toBe('https://x.com/me/status/999')
        ->and($post->published_at)->not->toBeNull()
        ->and($post->error_message)->toBeNull();
});

it('marks the post failed and stores the error when the API rejects it', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['detail' => 'duplicate content'], 403)]);

    $post = Post::factory()->forPlatform(Platform::X)->create(['status' => PostStatus::Draft]);

    runPublish($post);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->error_message)->toContain('duplicate content')
        ->and($post->platform_post_id)->toBeNull();
});

it('refuses to publish a post that is already published (double-post guard)', function () {
    Http::fake();
    $post = Post::factory()->forPlatform(Platform::X)->published()->create();

    runPublish($post);

    expect($post->refresh()->status)->toBe(PostStatus::Published);
    Http::assertNothingSent();
});

it('refuses to publish a post that is already publishing', function () {
    Http::fake();
    $post = Post::factory()->forPlatform(Platform::X)->create(['status' => PostStatus::Publishing]);

    runPublish($post);

    expect($post->refresh()->status)->toBe(PostStatus::Publishing);
    Http::assertNothingSent();
});

it('retries a previously failed post', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'account_handle' => 'me',
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1001']], 201)]);

    $post = Post::factory()->forPlatform(Platform::X)->failed()->create();

    runPublish($post);

    expect($post->refresh()->status)->toBe(PostStatus::Published);
});

it('only posts once when two jobs run for the same draft (atomic claim)', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'account_handle' => 'me',
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '42']], 201)]);

    $post = Post::factory()->forPlatform(Platform::X)->create(['status' => PostStatus::Draft, 'body' => 'hi']);

    // Two jobs were dispatched for the same post; run both.
    runPublish($post);
    runPublish($post);

    expect($post->refresh()->status)->toBe(PostStatus::Published);
    // Second job's conditional claim affected 0 rows, so only one tweet was sent.
    Http::assertSentCount(1);
});

it('marks a LinkedIn post failed because the publisher is stubbed', function () {
    $post = Post::factory()->forPlatform(Platform::LinkedIn)->create(['status' => PostStatus::Draft]);

    runPublish($post);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->error_message)->toContain('not available yet');
});
