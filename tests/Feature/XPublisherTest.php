<?php

use App\Enums\Platform;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Publishing\PublishException;
use App\Services\Publishing\XPublisher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\Token;

it('posts to the X API and returns the post id and url', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'account_handle' => 'my_handle',
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '1810000000000000001']], 201)]);

    $post = Post::factory()->forPlatform(Platform::X)->create(['body' => 'Hello world']);

    $result = app(XPublisher::class)->publish($post);

    expect($result->platformPostId)->toBe('1810000000000000001')
        ->and($result->platformUrl)->toBe('https://x.com/my_handle/status/1810000000000000001');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer valid-token')
        && $request['text'] === 'Hello world');
});

it('throws when X is not connected', function () {
    $post = Post::factory()->forPlatform(Platform::X)->create();

    expect(fn () => app(XPublisher::class)->publish($post))
        ->toThrow(PublishException::class, 'X is not connected. Connect the account first.');
});

it('throws a PublishException when the API rejects the post', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['detail' => 'You are not allowed to create a Tweet.'], 403)]);

    $post = Post::factory()->forPlatform(Platform::X)->create();

    expect(fn () => app(XPublisher::class)->publish($post))
        ->toThrow(PublishException::class, 'not allowed to create a Tweet');
});

it('throws when the token is expired and cannot be refreshed', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'refresh_token' => null,
    ]);
    Http::fake();

    $post = Post::factory()->forPlatform(Platform::X)->create();

    expect(fn () => app(XPublisher::class)->publish($post))
        ->toThrow(PublishException::class);

    Http::assertNothingSent();
});

it('refreshes an expired token then publishes', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'account_handle' => 'me',
        'refresh_token' => 'refresh-1',
    ]);
    $driver = Mockery::mock();
    $driver->shouldReceive('refreshToken')->with('refresh-1')
        ->andReturn(new Token('fresh-token', 'refresh-2', 7200, ['tweet.write']));
    Socialite::shouldReceive('driver')->with('x')->andReturn($driver);

    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '555']], 201)]);

    $result = app(XPublisher::class)->publish(Post::factory()->forPlatform(Platform::X)->create());

    expect($result->platformPostId)->toBe('555');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer fresh-token'));
});

it('uses the handle-less url fallback when no handle is stored', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'account_handle' => null,
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '777']], 201)]);

    $result = app(XPublisher::class)->publish(Post::factory()->forPlatform(Platform::X)->create());

    expect($result->platformUrl)->toBe('https://x.com/i/status/777');
});

it('wraps a network failure in a PublishException', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create([
        'access_token' => 'valid-token',
        'expires_at' => now()->addHour(),
    ]);
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => app(XPublisher::class)->publish(Post::factory()->forPlatform(Platform::X)->create()))
        ->toThrow(PublishException::class, 'Could not reach X');
});
