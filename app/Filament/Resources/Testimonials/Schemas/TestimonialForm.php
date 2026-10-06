<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Testimonial;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Video testimoni')
                    ->columns(2)
                    ->schema([
                        TextInput::make('youtube_url')->label('YouTube URL')->url()->required()->maxLength(2048),
                        TextInput::make('title.id')
                            ->label('Judul (Indonesia)')
                            ->formatStateUsing(fn ($state, ?Testimonial $record) => $record?->getTranslation('title', 'id') ?? ''),
                        TextInput::make('title.en')
                            ->label('Title (English)')
                            ->formatStateUsing(fn ($state, ?Testimonial $record) => $record?->getTranslation('title', 'en') ?? ''),
                        TextInput::make('client_name.id')
                            ->label('Nama klien (Indonesia)')
                            ->formatStateUsing(fn ($state, ?Testimonial $record) => $record?->getTranslation('client_name', 'id') ?? ''),
                        TextInput::make('client_name.en')
                            ->label('Client name (English)')
                            ->formatStateUsing(fn ($state, ?Testimonial $record) => $record?->getTranslation('client_name', 'en') ?? ''),
                        Select::make('rating')
                            ->options(array_combine(range(1, 5), range(1, 5)))
                            ->required()
                            ->default(5),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_highlighted'),
                        Toggle::make('is_published')->default(true),
                    ]),
            ]);
    }
}
