<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Provides a valid access token for a connected account, refreshing on demand.
 * X access tokens expire after ~2 hours, so refresh-before-use is mandatory
 * rather than relying on a background scheduler.
 */
class SocialTokenManager
{
    /**
     * Return a currently-valid access token, refreshing first if it has expired.
     *
     * @throws TokenRefreshException when the token is expired and unrefreshable
     */
    public function freshAccessToken(SocialAccount $account): string
    {
        if (! $account->tokenHasExpired()) {
            return $account->access_token;
        }

        return $this->refresh($account);
    }

    /**
     * @throws TokenRefreshException
     */
    public function refresh(SocialAccount $account): string
    {
        if (blank($account->refresh_token)) {
            throw new TokenRefreshException(
                "The {$account->platform->getLabel()} connection has expired and has no refresh token. Reconnect the account.",
            );
        }

        try {
            $token = Socialite::driver($account->platform->socialiteDriver())
                ->refreshToken($account->refresh_token);
        } catch (Throwable $e) {
            throw new TokenRefreshException(
                "Could not refresh the {$account->platform->getLabel()} token. Reconnect the account.",
                previous: $e,
            );
        }

        $account->update([
            'access_token' => $token->token,
            // X may or may not rotate the refresh token; keep the old one if absent.
            'refresh_token' => $token->refreshToken ?: $account->refresh_token,
            'expires_at' => $account->platform->tokenExpiryFrom($token->expiresIn),
            'scopes' => $token->approvedScopes ?: $account->scopes,
        ]);

        return $token->token;
    }
}
