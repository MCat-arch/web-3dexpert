<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\EventRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'eventRegistrations';

    protected static ?string $title = 'Pendaftaran Acara';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('registered_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('event'))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Acara')
                    ->formatStateUsing(fn ($state, EventRegistration $record) => $record->event?->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('events.status_'.$state)),
                TextColumn::make('ticket_code')->label('Kode tiket')->copyable()->toggleable(),
                TextColumn::make('registered_at')->label('Didaftarkan')->dateTime('d M Y H:i'),
            ]);
    }
}
