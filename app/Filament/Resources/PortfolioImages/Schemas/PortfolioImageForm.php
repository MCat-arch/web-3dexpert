<?php

namespace App\Filament\Resources\PortfolioImages\Schemas;

use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Support\MediaUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PortfolioImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gambar portofolio')
                    ->columns(2)
                    ->schema([
                        Select::make('portfolio_id')
                            ->label('Portofolio')
                            ->options(fn () => Portfolio::query()->get()->mapWithKeys(fn (Portfolio $portfolio) => [
                                $portfolio->id => $portfolio->getTranslation('title', 'id'),
                            ]))
                            ->searchable()
                            ->required(),
                        MediaUpload::field('image_path', 'portfolio', ['image/jpeg', 'image/png', 'image/webp'], 5120, true)
                            ->requiredWithout('external_url'),
                        TextInput::make('external_url')
                            ->url()
                            ->maxLength(2048)
                            ->requiredWithout('image_path'),
                        TextInput::make('alt_text.id')
                            ->label('Alt text (Indonesia)')
                            ->formatStateUsing(fn ($state, ?PortfolioImage $record) => $record?->getTranslation('alt_text', 'id') ?? ''),
                        TextInput::make('alt_text.en')
                            ->label('Alt text (English)')
                            ->formatStateUsing(fn ($state, ?PortfolioImage $record) => $record?->getTranslation('alt_text', 'en') ?? ''),
                        Toggle::make('is_cover'),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                    ]),
            ]);
    }
}
