<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Source')
                    ->schema([
                        TextInput::make('url')
                            ->label('Article URL')
                            ->placeholder('https://example.com/some-post')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->columnSpanFull(),
                    ]),

                // Populated by the scraper; shown only when reviewing an existing
                // article. These fields are read-only and never written back.
                Section::make('Extracted content')
                    ->hiddenOn('create')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->disabled()
                            ->placeholder('—'),
                        TextInput::make('author')
                            ->disabled()
                            ->placeholder('—'),
                        Textarea::make('excerpt')
                            ->disabled()
                            ->rows(3)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->disabled()
                            ->rows(12)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextInput::make('error_message')
                            ->label('Last error')
                            ->disabled()
                            ->visible(fn (?Article $record): bool => filled($record?->error_message))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
