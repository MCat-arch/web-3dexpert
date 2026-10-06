<?php

namespace App\Filament\Resources\PortfolioImages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortfolioImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('portfolio.title')
                    ->label('Portofolio')
                    ->formatStateUsing(fn ($state, $record) => $record->portfolio?->getTranslation('title', 'id'))
                    ->searchable(),
                TextColumn::make('image_path')->limit(36),
                IconColumn::make('is_cover')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
