<?php

namespace App\Filament\Resources\DesignReviews\Schemas;

use App\Models\DesignReview;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DesignReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review desain')
                    ->columns(2)
                    ->schema([
                        TextInput::make('youtube_url')->label('YouTube URL')->url()->required()->maxLength(2048),
                        TextInput::make('title.id')
                            ->label('Judul (Indonesia)')
                            ->formatStateUsing(fn ($state, ?DesignReview $record) => $record?->getTranslation('title', 'id') ?? ''),
                        TextInput::make('title.en')
                            ->label('Title (English)')
                            ->formatStateUsing(fn ($state, ?DesignReview $record) => $record?->getTranslation('title', 'en') ?? ''),
                        Textarea::make('description.id')
                            ->label('Deskripsi (Indonesia)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?DesignReview $record) => $record?->getTranslation('description', 'id') ?? ''),
                        Textarea::make('description.en')
                            ->label('Description (English)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state, ?DesignReview $record) => $record?->getTranslation('description', 'en') ?? ''),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_published')->default(true),
                    ]),
            ]);
    }
}
