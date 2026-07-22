<?php

namespace App\Filament\Pages;

use App\Enums\Platform;
use App\Models\SocialAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Connections extends Page
{
    protected string $view = 'filament.pages.connections';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 90;

    /**
     * Connected accounts keyed by platform value, loaded once per render.
     *
     * @var Collection<string, SocialAccount>|null
     */
    protected ?Collection $accounts = null;

    public function getTitle(): string|Htmlable
    {
        return 'Connections';
    }

    /**
     * @return Collection<string, SocialAccount>
     */
    protected function accounts(): Collection
    {
        return $this->accounts ??= SocialAccount::all()
            ->keyBy(fn (SocialAccount $account): string => $account->platform->value);
    }

    /**
     * Status of every platform for the view.
     *
     * @return array<int, array{platform: Platform, account: ?SocialAccount, connectable: bool}>
     */
    public function connections(): array
    {
        return collect(Platform::cases())
            ->map(fn (Platform $platform): array => [
                'platform' => $platform,
                'account' => $this->accounts()->get($platform->value),
                'connectable' => $platform->isConnectable(),
            ])
            ->all();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return collect(Platform::cases())
            ->map(fn (Platform $platform): Action => $this->actionForPlatform($platform))
            ->all();
    }

    protected function actionForPlatform(Platform $platform): Action
    {
        if ($this->accounts()->has($platform->value)) {
            return Action::make("disconnect_{$platform->value}")
                ->label("Disconnect {$platform->getLabel()}")
                ->icon(Heroicon::XMark)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription("This removes the stored {$platform->getLabel()} tokens. You can reconnect at any time.")
                ->action(function () use ($platform): void {
                    SocialAccount::where('platform', $platform)->delete();
                    $this->accounts = null; // invalidate memo after mutation

                    Notification::make()
                        ->title("Disconnected {$platform->getLabel()}")
                        ->success()
                        ->send();
                });
        }

        return Action::make("connect_{$platform->value}")
            ->label("Connect {$platform->getLabel()}")
            ->icon($platform->getIcon())
            ->color('primary')
            ->when(
                $platform->isConnectable(),
                fn (Action $action): Action => $action->url(route('oauth.connect', ['platform' => $platform->value])),
                fn (Action $action): Action => $action->disabled()->tooltip('Not available yet'),
            );
    }
}
