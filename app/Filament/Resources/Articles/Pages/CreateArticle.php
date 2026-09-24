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

    protected bool $shouldScrape = true;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldScrape = ($data['source_mode'] ?? 'scrape') === 'scrape';
        unset($data['source_mode']);

        $data['status'] = $this->shouldScrape
            ? ArticleStatus::Pending
            : ArticleStatus::Scraped;

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        // Replaced by the "Scrape queued" notification in afterCreate().
        return null;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldScrape) {
            Notification::make()
                ->title('Article created')
                ->body('The manually entered article is ready for post composition.')
                ->success()
                ->send();

            return;
        }

        ScrapeArticle::dispatch($this->record);

        Notification::make()
            ->title('Scrape queued')
            ->body('The article content will appear once scraping finishes.')
            ->success()
            ->send();
    }
}
