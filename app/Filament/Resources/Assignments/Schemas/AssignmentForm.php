<?php

namespace App\Filament\Resources\Assignments\Schemas;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Materi')
                ->columns(3)
                ->schema([
                    Select::make('course_id')
                        ->label('Kursus')
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->options(fn () => Course::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Course $course) => [
                                $course->getKey() => $course->getTranslation('title', 'id'),
                            ])
                            ->all())
                        ->afterStateUpdated(function (Set $set) {
                            $set('course_section_id', null);
                            $set('course_lesson_id', null);
                        })
                        ->afterStateHydrated(function (Select $component, Set $set) {
                            $record = $component->getRecord();

                            if (! $record instanceof Assignment) {
                                return;
                            }

                            $lesson = $record->lesson()->with('section')->first();

                            $set('course_id', $lesson?->section?->course_id);
                            $set('course_section_id', $lesson?->course_section_id);
                        }),

                    Select::make('course_section_id')
                        ->label('Bab')
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->options(fn ($get) => CourseSection::query()
                            ->where('course_id', $get('course_id'))
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (CourseSection $section) => [
                                $section->getKey() => $section->getTranslation('title', 'id'),
                            ])
                            ->all())
                        ->afterStateUpdated(fn (Set $set) => $set('course_lesson_id', null)),

                    Select::make('course_lesson_id')
                        ->label('Materi')
                        ->required()
                        ->searchable()
                        ->options(fn ($get) => CourseLesson::query()
                            ->where('course_section_id', $get('course_section_id'))
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn (CourseLesson $lesson) => [
                                $lesson->getKey() => $lesson->getTranslation('title', 'id'),
                            ])
                            ->all()),
                ]),

            Section::make('Tugas')
                ->schema([
                    TextInput::make('title.id')
                        ->label('Judul (Indonesia)')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('title.en')
                        ->label('Judul (Inggris)')
                        ->maxLength(255),
                    Textarea::make('instructions.id')
                        ->label('Instruksi (Indonesia)')
                        ->rows(5),
                    Textarea::make('instructions.en')
                        ->label('Instruksi (Inggris)')
                        ->rows(5),
                ]),

            Section::make('Penilaian & Publikasi')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('due_at')
                        ->label('Batas pengumpulan')
                        ->seconds(false)
                        ->nullable(),
                    TextInput::make('max_score')
                        ->label('Nilai maksimum')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->default(100)
                        ->required(),
                    Toggle::make('is_published')
                        ->label('Tampilkan ke siswa')
                        ->default(false),
                ]),
        ]);
    }
}
