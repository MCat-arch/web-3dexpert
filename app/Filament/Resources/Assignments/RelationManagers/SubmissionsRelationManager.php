<?php

namespace App\Filament\Resources\Assignments\RelationManagers;

use App\Actions\Courses\GradeAssignmentSubmission;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Pengumpulan';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'assignment']))
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Siswa')
                    ->formatStateUsing(fn ($state, AssignmentSubmission $record) => $record->customer?->name ?? 'Pelanggan terhapus')
                    ->description(fn (AssignmentSubmission $record) => $record->customer?->email)
                    ->wrap(),
                TextColumn::make('submitted_at')->label('Dikumpulkan')->dateTime('d M Y H:i'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === AssignmentSubmission::STATUS_GRADED ? 'Sudah dinilai' : 'Menunggu')
                    ->color(fn (string $state) => $state === AssignmentSubmission::STATUS_GRADED ? 'success' : 'warning'),
                TextColumn::make('score')
                    ->label('Nilai')
                    ->formatStateUsing(fn ($state, AssignmentSubmission $record) => $state === null
                        ? '-'
                        : $state.' / '.$record->assignment?->max_score)
                    ->placeholder('-'),
                TextColumn::make('graded_at')->label('Dinilai pada')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('viewSubmission')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detail pengumpulan')
                    ->modalContent(fn (AssignmentSubmission $record) => view(
                        'filament.assignments.submission-detail',
                        ['submission' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),

                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (AssignmentSubmission $record) => $record->hasFile())
                    ->url(fn (AssignmentSubmission $record) => route('admin.assignment-submissions.download', $record))
                    ->openUrlInNewTab(),

                Action::make('grade')
                    ->label('Nilai')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (AssignmentSubmission $record) => auth()->user()?->can(User::AREA_ASSIGNMENTS)
                        && $record->assignment !== null)
                    ->schema([
                        TextInput::make('score')
                            ->label('Nilai')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(fn (AssignmentSubmission $record) => $record->assignment?->max_score ?? 100),
                        Textarea::make('feedback')
                            ->label('Umpan balik (opsional)')
                            ->rows(4)
                            ->maxLength(3000),
                    ])
                    ->fillForm(fn (AssignmentSubmission $record) => [
                        'score' => $record->score,
                        'feedback' => $record->feedback,
                    ])
                    ->action(function (AssignmentSubmission $record, array $data) {
                        app(GradeAssignmentSubmission::class)(
                            $record,
                            (int) $data['score'],
                            $data['feedback'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Pengumpulan dinilai')
                            ->send();
                    }),

                DeleteAction::make()
                    ->visible(fn (AssignmentSubmission $record) => auth()->user()?->can(User::AREA_CONTENT) ?? false),
            ]);
    }
}
