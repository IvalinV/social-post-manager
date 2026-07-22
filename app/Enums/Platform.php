<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasIcon, HasLabel
{
    case X = 'x';
    case LinkedIn = 'linkedin';

    public function getLabel(): string
    {
        return match ($this) {
            self::X => 'X',
            self::LinkedIn => 'LinkedIn',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::X => 'heroicon-o-hashtag',
            self::LinkedIn => 'heroicon-o-briefcase',
        };
    }

    /**
     * The Socialite driver name used for this platform's OAuth flow.
     */
    public function socialiteDriver(): string
    {
        return match ($this) {
            self::X => 'x',
            self::LinkedIn => 'linkedin-openid',
        };
    }

    /**
     * OAuth scopes required to publish on this platform.
     *
     * @return array<int, string>
     */
    public function oauthScopes(): array
    {
        return match ($this) {
            self::X => ['users.read', 'tweet.read', 'tweet.write', 'offline.access'],
            self::LinkedIn => ['openid', 'profile', 'w_member_social'],
        };
    }

    /**
     * Whether this platform can currently be connected/published. LinkedIn is
     * deferred until its developer app and "Share on LinkedIn" product are
     * approved, so it is stubbed off for now.
     */
    public function isConnectable(): bool
    {
        return match ($this) {
            self::X => true,
            self::LinkedIn => false,
        };
    }

    /**
     * Resolve a token expiry timestamp from an OAuth response's `expires_in`.
     * Falls back to the platform's known access-token lifetime so a response
     * that omits `expires_in` never yields a "never expires" token that would
     * be served stale forever. Returns null only when no lifetime is known.
     */
    public function tokenExpiryFrom(?int $expiresIn): ?CarbonInterface
    {
        $seconds = $expiresIn ?: $this->defaultTokenTtlSeconds();

        return $seconds ? now()->addSeconds($seconds) : null;
    }

    /**
     * Known access-token lifetime in seconds (X access tokens last ~2 hours).
     */
    private function defaultTokenTtlSeconds(): ?int
    {
        return match ($this) {
            self::X => 7200,
            self::LinkedIn => null,
        };
    }

    /**
     * Maximum characters allowed in a post body for this platform.
     */
    public function maxLength(): int
    {
        return (int) config("social.limits.{$this->value}.max_length");
    }

    /**
     * Fixed character weight a URL contributes on this platform, or null when
     * URLs are counted at their real length.
     */
    public function urlLength(): ?int
    {
        $length = config("social.limits.{$this->value}.url_length");

        return $length === null ? null : (int) $length;
    }
}
