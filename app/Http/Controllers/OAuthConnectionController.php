<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Filament\Pages\Connections;
use App\Models\SocialAccount;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Uses Socialite purely as a token broker: it runs the OAuth handshake to obtain
 * publishing tokens for a platform and stores them against a SocialAccount.
 * It is NOT used for logging into the application.
 */
class OAuthConnectionController extends Controller
{
    /**
     * Begin the OAuth handshake for a platform.
     */
    public function connect(string $platform): SymfonyRedirect|RedirectResponse
    {
        $enum = $this->resolvePlatform($platform);

        if (! $enum->isConnectable()) {
            return $this->backWithError("{$enum->getLabel()} connections are not available yet.");
        }

        return Socialite::driver($enum->socialiteDriver())
            ->scopes($enum->oauthScopes())
            ->enablePKCE()
            ->redirect();
    }

    /**
     * Handle the provider callback and persist the publishing tokens.
     */
    public function callback(string $platform, Request $request): RedirectResponse
    {
        $enum = $this->resolvePlatform($platform);

        // The user declined authorization on the provider's consent screen.
        if ($request->has('error') || $request->missing('code')) {
            return $this->backWithError("Authorization for {$enum->getLabel()} was declined.");
        }

        try {
            $socialUser = Socialite::driver($enum->socialiteDriver())
                ->enablePKCE()
                ->user();
        } catch (Throwable) {
            return $this->backWithError("Could not complete the {$enum->getLabel()} connection. Please try again.");
        }

        SocialAccount::updateOrCreate(
            ['platform' => $enum],
            [
                'account_id' => $socialUser->getId(),
                'account_handle' => $socialUser->getNickname() ?: $socialUser->getName(),
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => $enum->tokenExpiryFrom($socialUser->expiresIn),
                'scopes' => $socialUser->approvedScopes ?: $enum->oauthScopes(),
            ],
        );

        Notification::make()
            ->title("Connected {$enum->getLabel()}")
            ->success()
            ->send();

        return Redirect::to($this->connectionsUrl());
    }

    protected function resolvePlatform(string $platform): Platform
    {
        return Platform::tryFrom($platform) ?? abort(404);
    }

    protected function backWithError(string $message): RedirectResponse
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        return Redirect::to($this->connectionsUrl());
    }

    protected function connectionsUrl(): string
    {
        return Connections::getUrl();
    }
}
