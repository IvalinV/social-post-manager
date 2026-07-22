<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Jobs\ScrapeArticle;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('Untitled')
                    ->description(fn (Article $record): string => $record->url)
                    ->searchable(['title', 'url'])
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('posts_count')
                    ->label('Posts')
                    ->counts('posts')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(ArticleStatus::cases())
                        ->mapWithKeys(fn (ArticleStatus $status): array => [$status->value => $status->getLabel()])
                        ->all()),
            ])
            ->recordActions([
                Action::make('rescrape')
                    ->label('Re-scrape')
                    ->icon(Heroicon::ArrowPath)
                    ->color('gray')
                    ->action(function (Article $record): void {
                        $record->update([
                            'status' => ArticleStatus::Pending,
                            'error_message' => null,
                        ]);

                        ScrapeArticle::dispatch($record);

                        Notification::make()
                            ->title('Re-scrape queued')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label('Review'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
