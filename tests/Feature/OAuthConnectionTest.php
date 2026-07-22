<?php

use App\Enums\Platform;
use App\Filament\Pages\Connections;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeXUser(): SocialiteUser
{
    return (new SocialiteUser)->map([
        'id' => 'x-999',
        'nickname' => 'my_handle',
        'name' => 'My Name',
    ])->setToken('access-token-abc')
        ->setRefreshToken('refresh-token-xyz')
        ->setExpiresIn(7200)
        ->setApprovedScopes(['tweet.write', 'offline.access']);
}

it('requires authentication to start a connection', function () {
    $this->get(route('oauth.connect', ['platform' => 'x']))
        ->assertRedirect('/admin/login');
});

it('redirects to the provider when connecting X', function () {
    Socialite::fake('x');
    $this->actingAs(User::factory()->create());

    $this->get(route('oauth.connect', ['platform' => 'x']))->assertRedirect();
});

it('stores publishing tokens on a successful callback', function () {
    Socialite::fake('x', fakeXUser());
    $this->actingAs(User::factory()->create());

    $this->get('/twitter/redirect?code=auth-code&state=state-value')
        ->assertRedirect(Connections::getUrl());

    $account = SocialAccount::where('platform', Platform::X)->first();
    expect($account)->not->toBeNull()
        ->and($account->account_id)->toBe('x-999')
        ->and($account->account_handle)->toBe('my_handle')
        ->and($account->access_token)->toBe('access-token-abc')
        ->and($account->refresh_token)->toBe('refresh-token-xyz')
        ->and($account->scopes)->toContain('tweet.write')
        ->and($account->expires_at)->not->toBeNull();

    // Tokens must be encrypted at rest, not stored in plaintext.
    $raw = DB::table('social_accounts')->where('id', $account->id)->first();
    expect($raw->access_token)->not->toBe('access-token-abc')
        ->and($raw->refresh_token)->not->toBe('refresh-token-xyz');
});

it('falls back to the platform default TTL when the provider omits expires_in', function () {
    $user = (new SocialiteUser)->map(['id' => 'x-1', 'nickname' => 'h', 'name' => 'n'])
        ->setToken('t')
        ->setRefreshToken('r');
    // expiresIn left null.
    Socialite::fake('x', $user);
    $this->actingAs(User::factory()->create());

    $this->get('/twitter/redirect?code=auth-code');

    $account = SocialAccount::where('platform', Platform::X)->first();
    // ~2h fallback, not null (which would mean "never expires").
    expect($account->expires_at)->not->toBeNull()
        ->and($account->expires_at->isFuture())->toBeTrue();
});

it('updates the existing account instead of duplicating on reconnect', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create(['account_handle' => 'old_handle']);
    Socialite::fake('x', fakeXUser());
    $this->actingAs(User::factory()->create());

    $this->get('/twitter/redirect?code=auth-code');

    expect(SocialAccount::where('platform', Platform::X)->count())->toBe(1)
        ->and(SocialAccount::where('platform', Platform::X)->first()->account_handle)->toBe('my_handle');
});

it('does not store anything when authorization is declined', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/twitter/redirect?error=access_denied')
        ->assertRedirect(Connections::getUrl());

    expect(SocialAccount::where('platform', Platform::X)->exists())->toBeFalse();
});

it('refuses to connect a platform that is not available yet', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('oauth.connect', ['platform' => 'linkedin']))
        ->assertRedirect(Connections::getUrl());

    expect(SocialAccount::where('platform', Platform::LinkedIn)->exists())->toBeFalse();
});

it('returns 404 for an unknown platform', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('oauth.connect', ['platform' => 'myspace']))->assertNotFound();
});

it('renders the connections page with platform statuses', function () {
    Filament\Facades\Filament::setCurrentPanel('admin');
    SocialAccount::factory()->forPlatform(Platform::X)->create(['account_handle' => 'my_handle']);
    $this->actingAs(User::factory()->create());

    $this->get('/admin/connections')
        ->assertSuccessful()
        ->assertSee('my_handle')
        ->assertSee('LinkedIn');
});

it('disconnects a platform via the page action', function () {
    Filament\Facades\Filament::setCurrentPanel('admin');
    SocialAccount::factory()->forPlatform(Platform::X)->create();
    $this->actingAs(User::factory()->create());

    Livewire\Livewire::test(Connections::class)
        ->callAction('disconnect_x');

    expect(SocialAccount::where('platform', Platform::X)->exists())->toBeFalse();
});
