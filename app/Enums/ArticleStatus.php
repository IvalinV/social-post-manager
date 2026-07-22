<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ArticleStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Scraping = 'scraping';
    case Scraped = 'scraped';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return string|array<int, int>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Scraping => 'info',
            self::Scraped => 'success',
            self::Failed => 'danger',
        };
    }
}
