<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PostStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Publishing = 'publishing';
    case Published = 'published';
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
            self::Draft => 'gray',
            self::Publishing => 'warning',
            self::Published => 'success',
            self::Failed => 'danger',
        };
    }
}
