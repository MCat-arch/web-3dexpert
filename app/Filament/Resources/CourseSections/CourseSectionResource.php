<?php

namespace App\Filament\Resources\CourseSections;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\CourseSections\Pages\EditCourseSection;
use App\Filament\Resources\CourseSections\Pages\ListCourseSections;
use App\Filament\Resources\CourseSections\RelationManagers\LessonsRelationManager;
use App\Filament\Resources\CourseSections\Tables\CourseSectionsTable;
use App\Filament\Support\NavGroup;
use App\Models\CourseSection;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CourseSectionResource extends AdminResource
{
    protected static ?string $model = CourseSection::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::PROGRAM;

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Kurikulum';

    protected static string $accessArea = User::AREA_CURRICULUM;

    protected static ?string $modelLabel = 'Bab';

    protected static ?string $pluralModelLabel = 'Bab';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title.id')
                ->label('Judul bab (Indonesia)')
                ->required()
                ->maxLength(255),

            TextInput::make('title.en')
                ->label('Judul bab (Inggris)')
                ->maxLength(255),

            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CourseSectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LessonsRelationManager::class,
        ];
    }

    public static function canCreate(): bool
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
            'index' => ListCourseSections::route('/'),
            'edit' => EditCourseSection::route('/{record}/edit'),
        ];
    }
}
