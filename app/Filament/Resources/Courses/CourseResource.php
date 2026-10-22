<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use App\Filament\Support\NavGroup;
use App\Models\Course;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament admin resource for managing courses.
 *
 * Modular structure under Resources/Courses/ so it can be
 * extracted to a dedicated plugin if the courses module grows.
 *
 *  CourseResource.php         – wiring / metadata
 *  Schemas/CourseForm.php     – create & edit form fields
 *  Tables/CoursesTable.php    – list columns, filters, actions
 *  Pages/ListCourses.php
 *  Pages/CreateCourse.php
 *  Pages/EditCourse.php
 */
class CourseResource extends AdminResource
{
    protected static ?string $model = Course::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::PROGRAM;

    protected static ?int $navigationSort = 30;

    protected static string $accessArea = User::AREA_CURRICULUM;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'Kursus';

    protected static ?string $pluralModelLabel = 'Kursus';

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }

    /**
     * Curriculum admins may author content but not destroy a course. Course
     * deletion stays with admin/superadmin (AREA_CONTENT).
     */
    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && (auth()->user()?->can(User::AREA_CONTENT) ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can(User::AREA_CONTENT) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
