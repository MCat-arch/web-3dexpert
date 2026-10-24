<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\EventRegistrations\EventRegistrationResource;
use App\Filament\Resources\EventRegistrations\Tables\EventRegistrationsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-event view of the same registration rows used by the global list.
 *
 * The relationship scopes the table to the event being edited, so seat
 * history, status, and the existing mark-attended/cancel actions stay
 * consistent with the public enrollment flow. Keeping the table configuration
 * shared means future attendance export and certificate actions can be added
 * once and automatically stay scoped to the event.
 */
class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Pendaftaran Peserta';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Attendee names, emails, and phone numbers are registration data.
        // A staff member who can edit events must not automatically see them.
        return EventRegistrationResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return EventRegistrationsTable::configure($table, showEvent: false)
            ->heading('Pendaftaran Peserta')
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Pendaftaran dari halaman acara akan muncul di sini.');
    }
}
