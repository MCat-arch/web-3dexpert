<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('title')
                    ->label('Layanan')
                    ->formatStateUsing(fn ($state, $record) => $record->getTranslation('title', 'id'))
                    ->searchable(),
                TextColumn::make('price')->money('IDR', locale: 'id')->sortable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_published')->boolean(),
                IconColumn::make('needs_review')
                    ->label('Perlu ditinjau')
                    ->boolean(),
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
