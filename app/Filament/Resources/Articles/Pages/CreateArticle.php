<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Jobs\ScrapeArticle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ArticleStatus::Pending;

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        // Replaced by the "Scrape queued" notification in afterCreate().
        return null;
    }

    protected function afterCreate(): void
    {
        ScrapeArticle::dispatch($this->record);

        Notification::make()
            ->title('Scrape queued')
            ->body('The article content will appear once scraping finishes.')
            ->success()
            ->send();
    }
}
