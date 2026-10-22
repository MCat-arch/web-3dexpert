<?php

namespace App\Filament\Resources\CourseEnrollments\Tables;

use App\Actions\Courses\GrantCourseAccess;
use App\Actions\Courses\RevokeCourseAccess;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\User;
use App\Support\CourseProgress;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('enrolled_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['customer', 'course' => fn ($course) => $course->withCount('lessons'), 'payment'])
                ->withCount('completions')
                ->withMax('completions', 'completed_at'))
            ->columns([
                TextColumn::make('student_name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('student_email')->label('Email')->searchable()->copyable(),
                TextColumn::make('student_phone')->label('Telepon')->searchable()->toggleable(),
                TextColumn::make('course.title')
                    ->label('Kursus')
                    ->formatStateUsing(fn ($state, CourseEnrollment $record) => $record->course?->getTranslation('title', 'id'))
                    ->wrap()
                    ->sortable(),
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
                    ->label('Progress')
                    ->state(function (CourseEnrollment $record) {
                        $progress = CourseProgress::for($record);

                        return $progress['completed'].' / '.$progress['total'].' ('.$progress['percent'].'%)';
                    })
                    ->badge()
                    ->color(fn (CourseEnrollment $record) => match (true) {
                        CourseProgress::for($record)['percent'] >= 100 => 'success',
                        CourseProgress::for($record)['percent'] > 0 => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('payment.status')
                    ->label('Pembayaran')
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn () => auth()->user()?->can(User::AREA_PAYMENTS) ?? false),
                TextColumn::make('enrolled_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
                TextColumn::make('activated_at')->label('Aktif sejak')->dateTime('d M Y')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('Kursus')
                    ->relationship('course', 'slug')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        CourseEnrollment::STATUS_ACTIVE => 'Aktif',
                        CourseEnrollment::STATUS_PENDING => 'Menunggu',
                        CourseEnrollment::STATUS_REVOKED => 'Dicabut',
                    ]),
                Filter::make('progress')
                    ->label('Progress')
                    ->schema([
                        Select::make('value')
                            ->label('Status progress')
                            ->options([
                                'not_started' => 'Belum dimulai',
                                'in_progress' => 'Sedang berjalan',
                                'completed' => 'Selesai',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        $completed = '(select count(*) from lesson_completions where lesson_completions.course_enrollment_id = course_enrollments.id)';
                        $total = '(select count(*) from course_lessons inner join course_sections on course_sections.id = course_lessons.course_section_id where course_sections.course_id = course_enrollments.course_id)';

                        return match ($value) {
                            'not_started' => $query->doesntHave('completions'),
                            'in_progress' => $query
                                ->whereHas('completions')
                                ->whereRaw("{$completed} < {$total}"),
                            'completed' => $query
                                ->whereRaw("{$total} > 0 and {$completed} >= {$total}"),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                self::grantAction(header: true),

                Action::make('exportComingSoon')
                    ->label('Ekspor CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->disabled()
                    ->tooltip('Ekspor belum termasuk dalam rilis ini.'),
            ])
            ->recordActions([
                ViewAction::make(),

                self::grantAction(header: false),

                Action::make('revokeAccess')
                    ->label('Cabut akses')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (CourseEnrollment $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan (opsional)')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (CourseEnrollment $record, array $data) {
                        app(RevokeCourseAccess::class)($record, $data['reason'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Akses kursus dicabut')
                            ->send();
                    }),
            ]);
    }

    private static function grantAction(bool $header): Action
    {
        $action = Action::make('grantAccess')
            ->label('Beri akses')
            ->icon('heroicon-o-key')
            ->requiresConfirmation()
            ->modalHeading('Beri akses kursus')
            ->modalDescription('Akses diberikan tanpa membuat pembayaran. Harga dan refund tetap menjadi kewenangan admin.');

        if (! $header) {
            $action
                ->visible(fn (CourseEnrollment $record) => $record->customer !== null
                    && in_array($record->status, [
                        CourseEnrollment::STATUS_PENDING,
                        CourseEnrollment::STATUS_REVOKED,
                    ], true))
                ->action(function (CourseEnrollment $record) {
                    app(GrantCourseAccess::class)($record->course, $record->customer);

                    Notification::make()->success()->title('Akses kursus diaktifkan')->send();
                });

            return $action;
        }

        return $action
            ->schema([
                Select::make('customer_id')
                    ->label('Pelanggan')
                    ->required()
                    ->searchable()
                    ->searchPrompt('Ketik minimal 3 karakter: nama atau email')
                    ->getSearchResultsUsing(function (string $search): array {
                        $search = trim($search);

                        if (mb_strlen($search) < 3) {
                            return [];
                        }

                        return Customer::query()
                            ->where(fn ($query) => $query
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%'))
                            ->orderBy('name')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Customer $customer) => [
                                $customer->getKey() => $customer->name.' — '.$customer->email,
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $customer = Customer::query()->find($value);

                        return $customer ? $customer->name.' — '.$customer->email : null;
                    }),
                Select::make('course_id')
                    ->label('Kursus')
                    ->required()
                    ->searchable()
                    ->options(fn () => Course::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Course $course) => [
                            $course->getKey() => $course->getTranslation('title', 'id').' ('.$course->slug.')',
                        ])
                        ->all()),
                Textarea::make('reason')
                    ->label('Alasan (opsional)')
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(function (array $data) {
                $customer = Customer::query()->findOrFail($data['customer_id']);
                $course = Course::query()->findOrFail($data['course_id']);

                app(GrantCourseAccess::class)($course, $customer, $data['reason'] ?? null);

                Notification::make()->success()->title('Akses kursus diberikan')->send();
            });
    }
}
