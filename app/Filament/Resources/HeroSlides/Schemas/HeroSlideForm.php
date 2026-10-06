<?php

namespace App\Filament\Resources\HeroSlides\Schemas;

use App\Models\HeroSlide;
use App\Support\MediaUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HeroSlideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero slide')
                    ->columns(2)
                    ->schema([
                        MediaUpload::field('image_path', 'hero', ['image/jpeg', 'image/png', 'image/webp'], 5120, true)
                            ->required(),
                        TextInput::make('alt_text.id')
                            ->label('Alt text (Indonesia)')
                            ->formatStateUsing(fn ($state, ?HeroSlide $record) => $record?->getTranslation('alt_text', 'id') ?? ''),
                        TextInput::make('alt_text.en')
                            ->label('Alt text (English)')
                            ->formatStateUsing(fn ($state, ?HeroSlide $record) => $record?->getTranslation('alt_text', 'en') ?? ''),
                        TextInput::make('caption.id')
                            ->label('Keterangan (Indonesia)')
                            ->formatStateUsing(fn ($state, ?HeroSlide $record) => $record?->getTranslation('caption', 'id') ?? ''),
                        TextInput::make('caption.en')
                            ->label('Caption (English)')
                            ->formatStateUsing(fn ($state, ?HeroSlide $record) => $record?->getTranslation('caption', 'en') ?? ''),
                        TextInput::make('link_url')->url()->maxLength(2048),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
