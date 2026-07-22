<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'platform',
    'account_id',
    'account_handle',
    'account_urn',
    'access_token',
    'refresh_token',
    'scopes',
    'expires_at',
])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Whether the access token has expired (or expires within the grace window).
     * A 60-second skew avoids racing the expiry during a publish attempt.
     */
    public function tokenHasExpired(int $graceSeconds = 60): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(now()->addSeconds($graceSeconds));
    }
}
