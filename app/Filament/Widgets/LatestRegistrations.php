<?php

namespace App\Filament\Widgets;

use App\Models\EventRegistration;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestRegistrations extends TableWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        // The superadmin dashboard is the summary grid plus the traffic chart;
        // this table would only repeat one of its numbers.
        return (auth()->user()?->can(User::AREA_REGISTRATIONS) ?? false)
            && ! (auth()->user()?->isSuperadmin() ?? false);
    }

    protected function getTableQuery(): Builder
    {
        return EventRegistration::query()
            ->with('event')
            ->latest('registered_at')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pendaftaran terbaru')
            ->paginated(false)
            ->columns([
                TextColumn::make('attendee_name')->label('Nama')->wrap(),
                TextColumn::make('event.title')
                    ->label('Acara')
                    ->formatStateUsing(fn ($state, EventRegistration $record) => $record->event?->getTranslation('title', 'id') ?? '-')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EventRegistration::STATUS_CONFIRMED => 'Terkonfirmasi',
                        EventRegistration::STATUS_ATTENDED => 'Hadir',
                        EventRegistration::STATUS_CANCELLED => 'Dibatalkan',
                        default => 'Menunggu',
                    })
                    ->color(fn (string $state) => match ($state) {
                        EventRegistration::STATUS_CONFIRMED, EventRegistration::STATUS_ATTENDED => 'success',
                        EventRegistration::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('registered_at')->label('Didaftarkan')->dateTime('d M Y H:i'),
            ]);
    }
}
