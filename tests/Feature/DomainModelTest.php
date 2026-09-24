<?php

use App\Enums\ArticleStatus;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Models\Article;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('casts article status to an enum and relates to posts', function () {
    $article = Article::factory()
        ->has(Post::factory()->count(2))
        ->create();

    expect($article->status)->toBe(ArticleStatus::Scraped)
        ->and($article->posts)->toHaveCount(2)
        ->and($article->posts->first())->toBeInstanceOf(Post::class);
});

it('casts post platform and status to enums and belongs to an article', function () {
    $post = Post::factory()->forPlatform(Platform::X)->create();

    expect($post->platform)->toBe(Platform::X)
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->article)->toBeInstanceOf(Article::class);
});

it('deletes posts when their article is deleted', function () {
    $article = Article::factory()->has(Post::factory()->count(3))->create();

    $article->delete();

    expect(Post::count())->toBe(0);
});

it('encrypts social account tokens at rest', function () {
    $account = SocialAccount::factory()->create([
        'access_token' => 'super-secret-access',
        'refresh_token' => 'super-secret-refresh',
    ]);

    // Accessor returns plaintext...
    expect($account->access_token)->toBe('super-secret-access')
        ->and($account->refresh_token)->toBe('super-secret-refresh');

    // ...but the stored column is ciphertext.
    $raw = DB::table('social_accounts')->where('id', $account->id)->first();
    expect($raw->access_token)->not->toBe('super-secret-access')
        ->and($raw->refresh_token)->not->toBe('super-secret-refresh');
});

it('detects expired and valid tokens with a grace window', function () {
    // A single account is reused (platform is unique) by mutating its expiry.
    $account = SocialAccount::factory()->create(['expires_at' => now()->addHours(2)]);
    expect($account->tokenHasExpired())->toBeFalse();

    $account->update(['expires_at' => now()->subMinutes(5)]);
    expect($account->tokenHasExpired())->toBeTrue();

    // Within the 60s grace window counts as expired.
    $account->update(['expires_at' => now()->addSeconds(30)]);
    expect($account->tokenHasExpired())->toBeTrue();

    // A null expiry (never expires) is not treated as expired.
    $account->update(['expires_at' => null]);
    expect($account->tokenHasExpired())->toBeFalse();
});

it('enforces one social account per platform', function () {
    SocialAccount::factory()->forPlatform(Platform::X)->create();

    expect(fn () => SocialAccount::factory()->forPlatform(Platform::X)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('resolves per-platform limits from config', function () {
    expect(Platform::X->maxLength())->toBe(280)
        ->and(Platform::X->urlLength())->toBe(23)
        ->and(Platform::LinkedIn->maxLength())->toBe(3000)
        ->and(Platform::LinkedIn->urlLength())->toBeNull();
});

it('uses LinkedIn access token lifetime when expiry is omitted', function () {
    $expiry = Platform::LinkedIn->tokenExpiryFrom(null);

    expect($expiry)->not->toBeNull()
        ->and($expiry->between(now()->addDays(59), now()->addDays(61)))->toBeTrue();
});

it('preserves manual articles when rolling back the nullable URL migration', function () {
    $article = Article::factory()->create(['url' => null]);
    $migration = require base_path('database/migrations/2026_09_23_000000_make_articles_url_nullable.php');

    $migration->down();

    expect($article->fresh()->url)->toBe('');

    $migration->up();
});
