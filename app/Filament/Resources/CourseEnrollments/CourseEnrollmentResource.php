<?php

namespace App\Filament\Resources\CourseEnrollments;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Filament\Resources\CourseEnrollments\Schemas\CourseEnrollmentInfolist;
use App\Filament\Resources\CourseEnrollments\Tables\CourseEnrollmentsTable;
use App\Filament\Support\NavGroup;
use App\Models\CourseEnrollment;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollmentResource extends AdminResource
{
    protected static ?string $model = CourseEnrollment::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::CUSTOMER;

    protected static ?int $navigationSort = 40;

    protected static string $accessArea = User::AREA_ENROLLMENTS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Siswa & Progress';

    protected static ?string $modelLabel = 'Siswa';

    protected static ?string $pluralModelLabel = 'Siswa & Progress';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'course', 'payment']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseEnrollmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseEnrollmentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseEnrollments::route('/'),
            'view' => ViewCourseEnrollment::route('/{record}'),
        ];
    }
}
