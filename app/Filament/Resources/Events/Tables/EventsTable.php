<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->formatStateUsing(fn ($state, $record) => $record->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),

                IconColumn::make('cover_path')
                    ->label('Pamflet')
                    ->boolean()
                    ->state(fn ($record) => filled($record->cover_path)),

                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                // Seats and capacity together: either number alone is
                // meaningless to whoever is watching an event fill up.
                TextColumn::make('seats_taken')
                    ->label('Kursi')
                    ->formatStateUsing(fn ($state, $record) => $record->isUnlimited()
                        ? $state.' / tak terbatas'
                        : $state.' / '.$record->capacity)
                    ->badge()
                    ->color(fn ($record) => $record->hasSeatsLeft() ? 'gray' : 'danger'),

                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => (float) $state === 0.0
                        ? 'Gratis'
                        : 'Rp '.number_format((float) $state, 0, ',', '.')),

                IconColumn::make('is_published')->label('Terbit')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Terbit'),

                Filter::make('upcoming')
                    ->label('Akan datang')
                    ->query(fn ($query) => $query->where('starts_at', '>=', now())),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
