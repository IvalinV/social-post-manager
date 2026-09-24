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

it('publishes a LinkedIn post through the Posts API', function () {
    SocialAccount::factory()->forPlatform(Platform::LinkedIn)->create([
        'account_id' => 'linkedin-member-999',
        'account_urn' => 'urn:li:person:linkedin-member-999',
        'access_token' => 'linkedin-token',
        'expires_at' => now()->addDay(),
    ]);
    Http::fake([
        'api.linkedin.com/rest/posts' => Http::response([], 201, [
            'x-restli-id' => 'urn:li:share:999',
        ]),
    ]);

    $post = Post::factory()->forPlatform(Platform::LinkedIn)->create([
        'status' => PostStatus::Draft,
        'body' => 'A LinkedIn post',
    ]);

    runPublish($post);

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->platform_post_id)->toBe('urn:li:share:999')
        ->and($post->platform_url)->toBe('https://www.linkedin.com/feed/update/urn:li:share:999')
        ->and($post->error_message)->toBeNull();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.linkedin.com/rest/posts'
            && $request->hasHeader('Linkedin-Version', '202609')
            && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0')
            && $request->data() === [
                'author' => 'urn:li:person:linkedin-member-999',
                'commentary' => 'A LinkedIn post',
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ];
    });
});

it('fails a LinkedIn post when no LinkedIn account is connected', function () {
    $post = Post::factory()->forPlatform(Platform::LinkedIn)->create(['status' => PostStatus::Draft]);

    runPublish($post);

    expect($post->refresh()->status)->toBe(PostStatus::Failed)
        ->and($post->error_message)->toContain('LinkedIn is not connected');
});
