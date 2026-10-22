<?php

namespace App\Filament\Resources\CourseSections\Tables;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\CourseSections\CourseSectionResource;
use App\Models\Course;
use App\Models\CourseSection;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('course_id')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('course')
                ->withCount('lessons'))
            ->columns([
                TextColumn::make('course.title')
                    ->label('Kursus')
                    ->formatStateUsing(fn ($state, CourseSection $record) => $record->course?->getTranslation('title', 'id'))
                    ->wrap(),

                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Bab')
                    ->formatStateUsing(fn ($state, CourseSection $record) => $record->getTranslation('title', 'id'))
                    ->wrap(),

                TextColumn::make('lessons_count')
                    ->label('Materi')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label('Kursus')
                    ->searchable()
                    ->options(fn () => Course::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [
                            $course->getKey() => $course->getTranslation('title', 'id'),
                        ])
                        ->all()),
            ])
            ->recordActions([
                Action::make('manageLessons')
                    ->label('Kelola materi')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (CourseSection $record) => CourseSectionResource::getUrl('edit', [
                        'record' => $record,
                    ])),

                Action::make('openCourse')
                    ->label('Buka kursus')
                    ->icon('heroicon-o-academic-cap')
                    ->url(fn (CourseSection $record) => CourseResource::getUrl('edit', [
                        'record' => $record->course_id,
                    ])),
            ]);
    }
}
