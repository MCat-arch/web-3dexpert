<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\RelationManagers\UpdatesRelationManager;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Filament\Support\NavGroup;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Manual entry is the only path (A1 confirmed): projects are maintained here,
 * with no external project-management system to sync from, and the legacy dump
 * verified to contain no project data at all.
 *
 * A16 remains open: if pre-existing project data turns up, get the real file
 * and its columns first, then add a CSV importer matched to that shape. Do not
 * build one against a guessed layout.
 */
class ProjectResource extends AdminResource
{
    protected static ?string $model = Project::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::CUSTOMER;

    protected static ?int $navigationSort = 20;

    protected static string $accessArea = User::AREA_PROJECTS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'Proyek';

    protected static ?string $pluralModelLabel = 'Proyek';

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [UpdatesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
