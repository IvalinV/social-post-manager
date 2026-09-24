<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Creation method')
                    ->hiddenOn('edit')
                    ->schema([
                        Radio::make('source_mode')
                            ->label('How should this article be created?')
                            ->options([
                                'scrape' => 'Scrape from a URL',
                                'manual' => 'Enter the article manually',
                            ])
                            ->default('scrape')
                            ->live()
                            ->required()
                            ->inline(),
                    ]),

                Section::make('Scrape from URL')
                    ->description('Fetch and extract the article content in the background.')
                    ->visible(fn (Get $get): bool => $get('source_mode') === 'scrape')
                    ->collapsible()
                    ->schema([
                        TextInput::make('url')
                            ->label('Article URL')
                            ->placeholder('https://example.com/some-post')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Enter manually')
                    ->description('Provide the article details without fetching a URL.')
                    ->visible(fn (Get $get): bool => $get('source_mode') === 'manual')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required(fn (Get $get): bool => $get('source_mode') === 'manual')
                            ->maxLength(255),
                        TextInput::make('author')
                            ->maxLength(255),
                        Textarea::make('excerpt')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->required(fn (Get $get): bool => $get('source_mode') === 'manual')
                            ->rows(14)
                            ->columnSpanFull(),
                        DateTimePicker::make('published_at'),
                        TextInput::make('og_image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(2048),
                    ]),

                Section::make('Source')
                    ->hiddenOn('create')
                    ->schema([
                        TextInput::make('url')
                            ->label('Article URL')
                            ->placeholder('https://example.com/some-post')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->unique(ignoreRecord: true)
                            ->disabled()
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
