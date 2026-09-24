<x-filament-panels::page>
    <div class="grid gap-6 p-4 sm:grid-cols-2">
        @foreach ($this->connections() as $connection)
            @php
                $platform = $connection['platform'];
                $account = $connection['account'];
                $expired = $account?->tokenHasExpired() ?? false;
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    {{ $platform->getLabel() }}
                </x-slot>

                <x-slot name="description">
                    @if ($account && $expired)
                        Token expired — it will be refreshed automatically on the next publish, or reconnect.
                    @elseif ($account)
                        Ready to publish.
                    @else
                        Not connected.
                    @endif
                </x-slot>

                <div class="flex items-center gap-2">
                    @if ($account)
                        <x-filament::badge :color="$expired ? 'warning' : 'success'">
                            {{ $expired ? 'Expired' : 'Connected' }}
                        </x-filament::badge>

                        @if ($account->account_handle)
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                &commat;{{ $account->account_handle }}
                            </span>
                        @endif

                        @if ($account->expires_at)
                            <span class="text-sm text-gray-400 dark:text-gray-500">
                                · expires {{ $account->expires_at->diffForHumans() }}
                            </span>
                        @endif
                    @else
                        <x-filament::badge color="gray">
                            Not connected
                        </x-filament::badge>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
