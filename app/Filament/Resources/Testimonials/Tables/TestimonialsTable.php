<?php

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')
                    ->label('Klien')
                    ->formatStateUsing(fn ($state, $record) => $record->getTranslation('client_name', 'id'))
                    ->searchable(),
                TextColumn::make('rating'),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_highlighted')->boolean(),
                IconColumn::make('is_published')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
