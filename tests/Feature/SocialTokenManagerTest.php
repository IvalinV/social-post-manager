<?php

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Services\Social\SocialTokenManager;
use App\Services\Social\TokenRefreshException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\Token;

it('returns the current token when it has not expired', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::X)->create([
        'access_token' => 'still-valid',
        'expires_at' => now()->addHour(),
    ]);

    expect(app(SocialTokenManager::class)->freshAccessToken($account))->toBe('still-valid');
});

it('refreshes and persists a new token when expired', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'access_token' => 'old-token',
        'refresh_token' => 'refresh-1',
    ]);

    $driver = Mockery::mock();
    $driver->shouldReceive('refreshToken')
        ->once()
        ->with('refresh-1')
        ->andReturn(new Token('new-token', 'refresh-2', 7200, ['tweet.write']));
    Socialite::shouldReceive('driver')->with('x')->andReturn($driver);

    $token = app(SocialTokenManager::class)->freshAccessToken($account);

    expect($token)->toBe('new-token');

    $account->refresh();
    expect($account->access_token)->toBe('new-token')
        ->and($account->refresh_token)->toBe('refresh-2')
        ->and($account->tokenHasExpired())->toBeFalse();
});

it('keeps the old refresh token when the provider does not rotate it', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'refresh_token' => 'keep-me',
    ]);

    $driver = Mockery::mock();
    $driver->shouldReceive('refreshToken')->andReturn(new Token('new-token', '', 7200, []));
    Socialite::shouldReceive('driver')->andReturn($driver);

    app(SocialTokenManager::class)->freshAccessToken($account);

    expect($account->refresh()->refresh_token)->toBe('keep-me');
});

it('throws when expired with no refresh token', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'refresh_token' => null,
    ]);

    expect(fn () => app(SocialTokenManager::class)->freshAccessToken($account))
        ->toThrow(TokenRefreshException::class);
});

it('wraps a provider refresh failure in a TokenRefreshException', function () {
    $account = SocialAccount::factory()->forPlatform(Platform::X)->expired()->create([
        'refresh_token' => 'refresh-1',
    ]);

    $driver = Mockery::mock();
    $driver->shouldReceive('refreshToken')->andThrow(new RuntimeException('invalid_grant'));
    Socialite::shouldReceive('driver')->andReturn($driver);

    expect(fn () => app(SocialTokenManager::class)->freshAccessToken($account))
        ->toThrow(TokenRefreshException::class);
});
