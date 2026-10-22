<?php

namespace App\Filament\Resources\Assignments\Tables;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['lesson.section.course'])
                ->withCount([
                    'submissions',
                    'submissions as pending_submissions_count' => fn (Builder $q) => $q->where('status', 'submitted'),
                ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Tugas')
                    ->formatStateUsing(fn ($state, Assignment $record) => $record->getTranslation('title', 'id'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('course_title')
                    ->label('Kursus')
                    ->state(fn (Assignment $record) => $record->lesson?->section?->course?->getTranslation('title', 'id') ?? '-')
                    ->wrap(),
                TextColumn::make('lesson.title')
                    ->label('Materi')
                    ->formatStateUsing(fn ($state, Assignment $record) => $record->lesson?->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('due_at')->label('Batas')->dateTime('d M Y H:i')->placeholder('Tanpa batas')->sortable(),
                IconColumn::make('is_published')->label('Terbit')->boolean(),
                TextColumn::make('submissions_count')->label('Pengumpulan')->badge()->color('info'),
                TextColumn::make('pending_submissions_count')
                    ->label('Belum dinilai')
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('max_score')->label('Nilai maks')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Terbit'),
                SelectFilter::make('course')
                    ->label('Kursus')
                    ->options(fn () => Course::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [
                            $course->getKey() => $course->getTranslation('title', 'id'),
                        ])
                        ->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $courseId) => $q->whereHas(
                            'lesson.section',
                            fn (Builder $section) => $section->where('course_id', $courseId),
                        ),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Assignment $record) => $record->submissions()->exists()
                        && ! (auth()->user()?->can(User::AREA_CONTENT) ?? false))
                    ->tooltip(fn (Assignment $record) => $record->submissions()->exists()
                        && ! (auth()->user()?->can(User::AREA_CONTENT) ?? false)
                        ? 'Tugas dengan pengumpulan tidak dapat dihapus. Turunkan publikasinya saja.'
                        : null),
            ]);
    }
}
