<?php

namespace App\Filament\Resources\CourseEnrollments\Schemas;

use App\Models\Assignment;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\CourseProgress;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseEnrollmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Siswa')
                ->columns(3)
                ->schema([
                    TextEntry::make('student_name')->label('Nama'),
                    TextEntry::make('student_email')->label('Email')->copyable(),
                    TextEntry::make('student_phone')->label('Telepon')->placeholder('-'),
                    TextEntry::make('course.title')
                        ->label('Kursus')
                        ->formatStateUsing(fn ($state, CourseEnrollment $record) => $record->course?->getTranslation('title', 'id')),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => match ($state) {
                            CourseEnrollment::STATUS_ACTIVE => 'Aktif',
                            CourseEnrollment::STATUS_PENDING => 'Menunggu',
                            CourseEnrollment::STATUS_REVOKED => 'Dicabut',
                            default => $state,
                        })
                        ->color(fn (string $state) => match ($state) {
                            CourseEnrollment::STATUS_ACTIVE => 'success',
                            CourseEnrollment::STATUS_PENDING => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('payment.status')
                        ->label('Pembayaran')
                        ->badge()
                        ->placeholder('-')
                        ->visible(fn () => auth()->user()?->can(User::AREA_PAYMENTS) ?? false),
                    TextEntry::make('enrolled_at')->label('Terdaftar')->dateTime('d M Y H:i'),
                    TextEntry::make('activated_at')->label('Aktif sejak')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('progress_summary')
                        ->label('Progress')
                        ->state(function (CourseEnrollment $record) {
                            $progress = CourseProgress::for($record);

                            return $progress['completed'].' / '.$progress['total'].' materi ('.$progress['percent'].'%)';
                        }),
                ]),

            ViewEntry::make('curriculum_detail')
                ->view('filament.enrollments.progress-detail')
                ->viewData(function (CourseEnrollment $record) {
                    $record->loadMissing([
                        'customer',
                        'course.sections.lessons',
                        'completions',
                    ]);

                    $completedLessonIds = $record->completions
                        ->pluck('course_lesson_id')
                        ->flip();

                    $assignments = Assignment::query()
                        ->whereHas('lesson.section', fn ($query) => $query->where('course_id', $record->course_id))
                        ->published()
                        ->with([
                            'lesson',
                            'submissions' => fn ($query) => $query->where('customer_id', $record->customer_id),
                        ])
                        ->orderBy('due_at')
                        ->get();

                    return [
                        'record' => $record,
                        'completedLessonIds' => $completedLessonIds,
                        'assignments' => $assignments,
                    ];
                })
                ->columnSpanFull(),
        ]);
    }
}
