<?php

namespace App\Filament\Resources\EventRegistrations;

use App\Filament\Resources\EventRegistrations\Pages\ListEventRegistrations;
use App\Filament\Resources\EventRegistrations\Tables\EventRegistrationsTable;
use App\Models\EventRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only, with two actions: mark attended, and cancel.
 *
 * No create and no edit form. Registrations come from the public enrollment
 * flow, which claims a seat atomically -- a staff-created row would not
 * increment seats_taken, and an edited status would not release one. Both
 * actions below go through the same code paths the public flow uses.
 */
class EventRegistrationResource extends Resource
{
    protected static ?string $model = EventRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $modelLabel = 'Pendaftaran';

    protected static ?string $pluralModelLabel = 'Pendaftaran Acara';

    public static function table(Table $table): Table
    {
        return EventRegistrationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventRegistrations::route('/'),
        ];
    }
}
