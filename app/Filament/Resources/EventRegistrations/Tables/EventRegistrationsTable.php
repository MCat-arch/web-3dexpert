<?php

namespace App\Filament\Resources\EventRegistrations\Tables;

use App\Actions\Events\CancelEventRegistration;
use App\Models\EventRegistration;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class EventRegistrationsTable
{
    /**
     * The same table is used by the global registration list and by the
     * per-event relation manager. The event column/filter would only repeat
     * the page context in the latter, so it can be hidden without changing
     * the underlying rows or actions.
     */
    public static function configure(Table $table, bool $showEvent = true): Table
    {
        $columns = [
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
        ];

        $filters = [
            SelectFilter::make('status')->options([
                EventRegistration::STATUS_PENDING => __('events.status_pending'),
                EventRegistration::STATUS_CONFIRMED => __('events.status_confirmed'),
                EventRegistration::STATUS_ATTENDED => __('events.status_attended'),
                EventRegistration::STATUS_CANCELLED => __('events.status_cancelled'),
            ]),
        ];

        if ($showEvent) {
            array_unshift($columns, TextColumn::make('event.title')
                ->label('Acara')
                ->formatStateUsing(fn ($state, $record) => $record->event?->getTranslation('title', 'id'))
                ->searchable()
                ->wrap());

            $filters[] = SelectFilter::make('event')->relationship('event', 'slug')->label('Acara');
        }

        $table = $table
            ->defaultSort('registered_at', 'desc')
            // The event title is shown on every row and used for grouping, so
            // it must be eager loaded: preventLazyLoading() is active outside
            // production.
            ->modifyQueryUsing(fn ($query) => $query->with('event'))
            ->columns($columns)
            ->filters($filters);

        if ($showEvent) {
            // Keep one shared registration dataset, but present the global
            // list grouped per event by default. Staff can still change or
            // remove the grouping from Filament's table controls.
            $table
                ->groups([
                    Group::make('event_id')
                        ->label('Acara')
                        ->getTitleFromRecordUsing(fn (EventRegistration $record) => $record->event?->getTranslation('title', 'id')
                            ?? 'Acara #'.$record->event_id)
                        ->collapsible(),
                ])
                ->defaultGroup('event_id');
        }

        return $table
            ->recordActions([
                Action::make('markAttended')
                    ->label('Tandai hadir')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (EventRegistration $record) => $record->status === EventRegistration::STATUS_CONFIRMED)
                    ->action(function (EventRegistration $record) {
                        $old = $record->status;

                        $record->forceFill([
                            'status' => EventRegistration::STATUS_ATTENDED,
                            'attended_at' => now(),
                        ])->save();

                        app(AuditLogger::class)->statusChanged(
                            $record,
                            $old,
                            EventRegistration::STATUS_ATTENDED,
                            'registration.attended',
                        );
                    }),

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
