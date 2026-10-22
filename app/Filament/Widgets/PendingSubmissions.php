<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assignments\AssignmentResource;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingSubmissions extends TableWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can(User::AREA_ASSIGNMENTS) ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return AssignmentSubmission::query()
            ->where('status', AssignmentSubmission::STATUS_SUBMITTED)
            ->with(['assignment', 'customer'])
            ->latest('submitted_at')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tugas menunggu penilaian')
            ->paginated(false)
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Siswa')
                    ->formatStateUsing(fn ($state, AssignmentSubmission $record) => $record->customer?->name ?? '-')
                    ->wrap(),
                TextColumn::make('assignment.title')
                    ->label('Tugas')
                    ->formatStateUsing(fn ($state, AssignmentSubmission $record) => $record->assignment?->getTranslation('title', 'id') ?? '-')
                    ->wrap(),
                TextColumn::make('submitted_at')->label('Dikumpulkan')->dateTime('d M Y H:i'),
                TextColumn::make('open')
                    ->label('')
                    ->state(fn () => 'Buka tugas')
                    ->url(fn (AssignmentSubmission $record) => AssignmentResource::getUrl('edit', [
                        'record' => $record->assignment_id,
                    ])),
            ]);
    }
}
