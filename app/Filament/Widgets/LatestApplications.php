<?php

namespace App\Filament\Widgets;

use App\Models\JobApplication;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestApplications extends TableWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        // Superadmin sees the summary grid only; see SuperadminOverview.
        return (auth()->user()?->can(User::AREA_HIRING) ?? false)
            && ! (auth()->user()?->isSuperadmin() ?? false);
    }

    protected function getTableQuery(): Builder
    {
        return JobApplication::query()
            ->with('vacancy')
            ->latest('created_at')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Lamaran terbaru')
            ->paginated(false)
            ->columns([
                TextColumn::make('applicant_name')->label('Pelamar')->wrap(),
                TextColumn::make('vacancy.title')
                    ->label('Lowongan')
                    ->formatStateUsing(fn ($state, JobApplication $record) => $record->vacancy?->getTranslation('title', 'id') ?? '-')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        JobApplication::STATUS_SUBMITTED => 'Baru',
                        JobApplication::STATUS_SCREENING => 'Screening',
                        JobApplication::STATUS_INTERVIEW => 'Interview',
                        JobApplication::STATUS_OFFERED => 'Ditawarkan',
                        JobApplication::STATUS_REJECTED => 'Ditolak',
                        JobApplication::STATUS_WITHDRAWN => 'Ditarik',
                        default => $state,
                    }),
                TextColumn::make('created_at')->label('Masuk')->dateTime('d M Y H:i'),
            ]);
    }
}
