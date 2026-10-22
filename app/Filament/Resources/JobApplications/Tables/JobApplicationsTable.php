<?php

namespace App\Filament\Resources\JobApplications\Tables;

use App\Models\JobApplication;
use App\Notifications\Careers\ApplicationStatusChanged;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            // Both are rendered per row; preventLazyLoading() throws outside
            // production without this.
            ->modifyQueryUsing(fn ($query) => $query->with(['vacancy', 'documents']))
            ->columns([
                TextColumn::make('vacancy.title')
                    ->label('Posisi')
                    ->formatStateUsing(fn ($state, $record) => $record->vacancy?->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),

                // The snapshot, not the live relation -- this is what survives
                // account deletion.
                TextColumn::make('applicant_name')->label('Pelamar')->searchable(),
                TextColumn::make('applicant_email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('applicant_phone')->label('WhatsApp')->toggleable(),

                TextColumn::make('documents_count')
                    ->label('Dokumen')
                    ->state(fn (JobApplication $record) => $record->documents->count())
                    ->badge(),

                IconColumn::make('account')
                    ->label('Akun')
                    ->state(fn (JobApplication $record) => ! $record->applicantAccountDeleted())
                    ->boolean()
                    ->tooltip(fn (JobApplication $record) => $record->applicantAccountDeleted()
                        ? 'Akun pelamar sudah dihapus. Data lamaran tetap tersimpan.'
                        : 'Akun pelamar masih aktif.'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('careers.status_'.$state))
                    ->color(fn (string $state) => match ($state) {
                        JobApplication::STATUS_OFFERED => 'success',
                        JobApplication::STATUS_INTERVIEW, JobApplication::STATUS_SCREENING => 'warning',
                        JobApplication::STATUS_REJECTED, JobApplication::STATUS_WITHDRAWN => 'gray',
                        default => 'info',
                    }),

                TextColumn::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(JobApplication::statuses())
                        ->mapWithKeys(fn (string $s) => [$s => __('careers.status_'.$s)])
                        ->all()
                ),

                SelectFilter::make('vacancy')->relationship('vacancy', 'slug')->label('Posisi'),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('changeStatus')
                    ->label('Ubah status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (JobApplication $record) => ! $record->isWithdrawn())
                    ->schema([
                        Select::make('status')
                            ->label('Status baru')
                            ->options(
                                collect(JobApplication::statuses())
                                    ->reject(fn (string $s) => $s === JobApplication::STATUS_WITHDRAWN)
                                    ->mapWithKeys(fn (string $s) => [$s => __('careers.status_'.$s)])
                                    ->all()
                            )
                            ->required(),

                        Textarea::make('internal_notes')
                            ->label('Catatan internal')
                            ->rows(3)
                            // Staff-only. $hidden on the model and gated by
                            // JobApplicationPolicy::viewNotes, so it cannot
                            // reach the candidate.
                            ->helperText('Hanya terlihat oleh staf. Tidak dikirim ke pelamar.'),
                    ])
                    ->action(function (JobApplication $record, array $data) {
                        $oldStatus = $record->status;

                        $record->forceFill([
                            'status' => $data['status'],
                            'status_changed_at' => now(),
                            'internal_notes' => $data['internal_notes'] ?? $record->internal_notes,
                            'reviewed_by' => auth()->id(),
                        ])->save();

                        app(AuditLogger::class)->statusChanged(
                            $record,
                            $oldStatus,
                            (string) $record->status,
                            'application.status_changed',
                        );

                        // Carries the status only, never the notes.
                        $record->customer?->notify(new ApplicationStatusChanged($record->fresh(['vacancy'])));
                    }),
            ]);
    }
}
