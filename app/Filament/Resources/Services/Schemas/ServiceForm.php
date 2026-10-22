<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Models\Service;
use App\Models\ServiceFeature;
use App\Support\MediaUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Layanan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.id')
                            ->label('Nama layanan (Indonesia)')
                            ->required()
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('title', 'id') ?? ''),
                        TextInput::make('title.en')
                            ->label('Service name (English)')
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('title', 'en') ?? ''),
                        Textarea::make('description.id')
                            ->label('Deskripsi (Indonesia)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('description', 'id') ?? ''),
                        Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('description', 'en') ?? ''),
                        TextInput::make('slug')->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('price')->numeric()->minValue(0),
                        TextInput::make('original_price')->numeric()->minValue(0),
                        TextInput::make('discount_text.id')
                            ->label('Teks diskon (Indonesia)')
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('discount_text', 'id') ?? ''),
                        TextInput::make('discount_text.en')
                            ->label('Discount text (English)')
                            ->formatStateUsing(fn ($state, ?Service $record) => $record?->getTranslation('discount_text', 'en') ?? ''),
                        MediaUpload::field('brochure_path', 'brochures', ['application/pdf'], 20480),
                        MediaUpload::field('icon_path', 'service-icons', ['image/jpeg', 'image/png', 'image/webp'], 5120, true),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_published'),
                        Toggle::make('needs_review'),
                    ]),
                Section::make('Fitur layanan')
                    ->schema([
                        Repeater::make('features')
                            ->relationship()
                            ->schema([
                                TextInput::make('label.id')
                                    ->label('Fitur (Indonesia)')
                                    ->required()
                                    ->formatStateUsing(fn ($state, ?ServiceFeature $record) => $record?->getTranslation('label', 'id') ?? ''),
                                TextInput::make('label.en')
                                    ->label('Feature (English)')
                                    ->formatStateUsing(fn ($state, ?ServiceFeature $record) => $record?->getTranslation('label', 'en') ?? ''),
                                TextInput::make('sort_order')->numeric()->default(0)->required(),
                                Toggle::make('is_heading'),
                            ])
                            ->columns(2)
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
