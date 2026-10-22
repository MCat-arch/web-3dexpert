<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\CourseProgress;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Kursus';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('enrolled_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['course' => fn ($course) => $course->withCount('lessons'), 'payment'])
                ->withCount('completions'))
            ->columns([
                TextColumn::make('course.title')
                    ->label('Kursus')
                    ->formatStateUsing(fn ($state, CourseEnrollment $record) => $record->course?->getTranslation('title', 'id'))
                    ->wrap(),
                TextColumn::make('status')
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
                TextColumn::make('progress')
                    ->label('Progres')
                    ->state(fn (CourseEnrollment $record) => CourseProgress::for($record)['percent'].'%'),
                TextColumn::make('payment.status')
                    ->label('Pembayaran')
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn () => auth()->user()?->can(User::AREA_PAYMENTS) ?? false),
                TextColumn::make('enrolled_at')->label('Terdaftar')->dateTime('d M Y'),
                TextColumn::make('activated_at')->label('Aktif sejak')->dateTime('d M Y')->placeholder('-'),
            ]);
    }
}
