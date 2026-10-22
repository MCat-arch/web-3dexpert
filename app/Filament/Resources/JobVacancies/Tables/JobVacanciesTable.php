<?php

namespace App\Filament\Resources\JobVacancies\Tables;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobVacancy;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class JobVacanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('applications'))
            ->columns([
                TextColumn::make('title')
                    ->label('Posisi')
                    ->formatStateUsing(fn ($state, JobVacancy $record) => $record->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('department')->label('Departemen')->searchable()->toggleable(),
                TextColumn::make('location')->label('Lokasi')->searchable()->toggleable(),
                TextColumn::make('employment_type')->label('Jenis')->toggleable(),

                TextColumn::make('applications_count')
                    ->label('Pelamar')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('closes_at')
                    ->label('Batas lamaran')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tanpa batas')
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Terbit')
                    ->boolean(),

                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->toggleable()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Terbit'),

                Filter::make('open')
                    ->label('Masih menerima lamaran')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->open()),

                SelectFilter::make('department')
                    ->label('Departemen')
                    ->options(fn () => JobVacancy::query()
                        ->whereNotNull('department')
                        ->distinct()
                        ->orderBy('department')
                        ->pluck('department', 'department')
                        ->all()),
            ])
            ->recordActions([
                Action::make('applications')
                    ->label('Lihat pelamar')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->url(fn (JobVacancy $record) => JobApplicationResource::getUrl('index', [
                        'tableFilters' => [
                            'vacancy' => ['value' => $record->getKey()],
                        ],
                    ])),

                EditAction::make(),

                DeleteAction::make()
                    ->disabled(fn (JobVacancy $record) => $record->applications()->exists())
                    ->tooltip(fn (JobVacancy $record) => $record->applications()->exists()
                        ? 'Lowongan dengan lamaran tidak dapat dihapus.'
                        : null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Terbitkan')
                        ->icon('heroicon-o-eye')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each->update(['is_published' => true]);

                            Notification::make()->success()->title('Lowongan diterbitkan')->send();
                        }),

                    BulkAction::make('unpublish')
                        ->label('Turunkan')
                        ->icon('heroicon-o-eye-slash')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each->update(['is_published' => false]);

                            Notification::make()->success()->title('Lowongan diturunkan')->send();
                        }),
                ]),
            ]);
    }
}
