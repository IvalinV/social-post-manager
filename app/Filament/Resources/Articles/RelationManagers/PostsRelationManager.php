<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Jobs\PublishPost;
use App\Models\Article;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Posts\PostComposer;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'Social posts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platform')
                    ->options(fn (): array => collect(Platform::cases())
                        ->filter(fn (Platform $platform): bool => $platform->isConnectable())
                        ->mapWithKeys(fn (Platform $platform): array => [$platform->value => $platform->getLabel()])
                        ->all())
                    ->required()
                    ->live()
                    ->disabledOn('edit'),

                Textarea::make('body')
                    ->required()
                    ->rows(6)
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => $this->characterHint($get))
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $platform = $this->resolvePlatform($get('platform'));

                            if ($platform && ! app(PostComposer::class)->fits($platform, (string) $value)) {
                                $fail("This exceeds the {$platform->getLabel()} limit of {$platform->maxLength()} characters.");
                            }
                        },
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->poll('10s')
            ->columns([
                TextColumn::make('platform')
                    ->badge(),
                TextColumn::make('body')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('length')
                    ->label('Length')
                    ->state(fn (Post $record): string => $this->lengthLabel($record))
                    ->badge()
                    ->color(fn (Post $record): string => $this->withinLimit($record) ? 'gray' : 'danger'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('platform_url')
                    ->label('Live post')
                    ->url(fn (Post $record): ?string => $record->platform_url)
                    ->openUrlInNewTab()
                    ->formatStateUsing(fn (): string => 'View')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->headerActions([
                Action::make('generateDrafts')
                    ->label('Generate drafts')
                    ->icon(Heroicon::Sparkles)
                    ->action(fn () => $this->generateDrafts()),
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['status'] = PostStatus::Draft->value;

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(fn (Post $record): string => $record->status === PostStatus::Failed ? 'Retry publish' : 'Publish')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('primary')
                    ->visible(fn (Post $record): bool => in_array($record->status, [PostStatus::Draft, PostStatus::Failed], true))
                    ->disabled(fn (Post $record): bool => $this->publishBlockReason($record) !== null)
                    ->tooltip(fn (Post $record): ?string => $this->publishBlockReason($record))
                    ->requiresConfirmation()
                    ->modalHeading('Publish now?')
                    ->modalDescription(fn (Post $record): string => $record->status === PostStatus::Failed
                        ? "This re-posts to {$record->platform->getLabel()}. If the previous attempt may have actually posted, check {$record->platform->getLabel()} first — retrying can create a duplicate."
                        : "This posts to {$record->platform->getLabel()} immediately. This cannot be undone from here.")
                    ->action(function (Post $record): void {
                        // Defense in depth: re-verify server-side, since disabled()
                        // is only a UI state and could be bypassed.
                        if ($this->publishBlockReason($record) !== null
                            || ! in_array($record->status, [PostStatus::Draft, PostStatus::Failed], true)) {
                            Notification::make()
                                ->title('Cannot publish this post')
                                ->body($this->publishBlockReason($record) ?? 'The post is no longer in a publishable state.')
                                ->danger()
                                ->send();

                            return;
                        }

                        PublishPost::dispatch($record);

                        Notification::make()
                            ->title('Publishing…')
                            ->body('The post is being sent; the status will update shortly.')
                            ->success()
                            ->send();
                    }),
                EditAction::make()->label('Compose'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function generateDrafts(): void
    {
        /** @var Article $article */
        $article = $this->getOwnerRecord();
        $composer = app(PostComposer::class);
        $created = 0;

        foreach (Platform::cases() as $platform) {
            if (! $platform->isConnectable()) {
                continue;
            }

            if ($article->posts()->where('platform', $platform)->exists()) {
                continue;
            }

            $article->posts()->create([
                'platform' => $platform,
                'body' => $composer->render($article, $platform),
                'status' => PostStatus::Draft,
            ]);

            $created++;
        }

        Notification::make()
            ->title($created > 0 ? "Generated {$created} draft(s)" : 'Drafts already exist for all platforms')
            ->success()
            ->send();
    }

    protected function characterHint(Get $get): string
    {
        $platform = $this->resolvePlatform($get('platform'));

        if (! $platform) {
            return 'Select a platform to see the character limit.';
        }

        $remaining = app(PostComposer::class)->remaining($platform, (string) $get('body'));

        return $remaining >= 0
            ? "{$remaining} characters remaining (limit {$platform->maxLength()})"
            : abs($remaining).' characters over the limit';
    }

    protected function lengthLabel(Post $record): string
    {
        $used = app(PostComposer::class)->weightedLength($record->platform, (string) $record->body);

        return "{$used}/{$record->platform->maxLength()}";
    }

    protected function withinLimit(Post $record): bool
    {
        return app(PostComposer::class)->fits($record->platform, (string) $record->body);
    }

    /**
     * Reason the post cannot be published right now, or null when it can.
     */
    protected function publishBlockReason(Post $record): ?string
    {
        if (! $record->platform->isConnectable()) {
            return "{$record->platform->getLabel()} publishing is not available yet.";
        }

        if (blank($record->body)) {
            return 'The post body is empty.';
        }

        if (! $this->withinLimit($record)) {
            return "The post exceeds the {$record->platform->getLabel()} character limit.";
        }

        if (! SocialAccount::where('platform', $record->platform)->exists()) {
            return "Connect your {$record->platform->getLabel()} account first.";
        }

        return null;
    }

    protected function resolvePlatform(mixed $platform): ?Platform
    {
        if ($platform instanceof Platform) {
            return $platform;
        }

        return is_string($platform) ? Platform::tryFrom($platform) : null;
    }
}
