<?php

namespace App\Filament\Resources\Portfolios\Schemas;

use App\Models\Portfolio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortfolioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konten portofolio')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.id')
                            ->label('Judul (Indonesia)')
                            ->required()
                            ->formatStateUsing(fn ($state, ?Portfolio $record) => $record?->getTranslation('title', 'id') ?? ''),
                        TextInput::make('title.en')
                            ->label('Title (English)')
                            ->formatStateUsing(fn ($state, ?Portfolio $record) => $record?->getTranslation('title', 'en') ?? ''),
                        Textarea::make('description.id')
                            ->label('Deskripsi (Indonesia)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?Portfolio $record) => $record?->getTranslation('description', 'id') ?? ''),
                        Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?Portfolio $record) => $record?->getTranslation('description', 'en') ?? ''),
                        TextInput::make('slug')->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('price')->numeric()->minValue(0),
                        TextInput::make('location')->maxLength(255),
                        TextInput::make('style')->maxLength(255),
                        TextInput::make('floors')->numeric()->minValue(0),
                        TextInput::make('bedrooms')->numeric()->minValue(0),
                        TextInput::make('bathrooms')->numeric()->minValue(0),
                        TextInput::make('land_size')->numeric()->minValue(0),
                        TextInput::make('building_size')->numeric()->minValue(0),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_featured'),
                        Toggle::make('is_published'),
                    ]),
            ]);
    }
}
