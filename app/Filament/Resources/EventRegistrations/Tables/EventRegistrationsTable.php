<?php

namespace App\Filament\Resources\EventRegistrations\Tables;

use App\Actions\Events\CancelEventRegistration;
use App\Models\EventRegistration;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('registered_at', 'desc')
            // The event title is shown on every row, so it must be eager
            // loaded: preventLazyLoading() is active outside production.
            ->modifyQueryUsing(fn ($query) => $query->with('event'))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Acara')
                    ->formatStateUsing(fn ($state, $record) => $record->event?->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),

                // The snapshot, not the live customer relation -- this is what
                // survives account deletion.
                TextColumn::make('attendee_name')->label('Peserta')->searchable(),
                TextColumn::make('attendee_email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('attendee_phone')->label('WhatsApp')->toggleable(),

                TextColumn::make('ticket_code')
                    ->label('Kode tiket')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('events.status_'.$state))
                    ->color(fn (string $state) => match ($state) {
                        EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_ATTENDED => 'success',
                        EventRegistration::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('registered_at')->label('Terdaftar')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    EventRegistration::STATUS_PENDING => __('events.status_pending'),
                    EventRegistration::STATUS_CONFIRMED => __('events.status_confirmed'),
                    EventRegistration::STATUS_ATTENDED => __('events.status_attended'),
                    EventRegistration::STATUS_CANCELLED => __('events.status_cancelled'),
                ]),

                SelectFilter::make('event')->relationship('event', 'slug')->label('Acara'),
            ])
            ->recordActions([
                Action::make('markAttended')
                    ->label('Tandai hadir')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (EventRegistration $record) => $record->status === EventRegistration::STATUS_CONFIRMED)
                    ->action(fn (EventRegistration $record) => $record->forceFill([
                        'status' => EventRegistration::STATUS_ATTENDED,
                        'attended_at' => now(),
                    ])->save()),

                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EventRegistration $record) => ! $record->isCancelled())
                    // Routed through the same action the public flow uses, so
                    // the seat is released exactly once and by one code path.
                    // Decrementing here instead is how seats_taken drifts.
                    ->action(fn (EventRegistration $record) => app(CancelEventRegistration::class)($record)),
            ]);
    }
}
