<?php

namespace App\Filament\Resources\Assignments;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\Assignments\Pages\CreateAssignment;
use App\Filament\Resources\Assignments\Pages\EditAssignment;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Filament\Resources\Assignments\RelationManagers\SubmissionsRelationManager;
use App\Filament\Resources\Assignments\Schemas\AssignmentForm;
use App\Filament\Resources\Assignments\Tables\AssignmentsTable;
use App\Filament\Support\NavGroup;
use App\Models\Assignment;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssignmentResource extends AdminResource
{
    protected static ?string $model = Assignment::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::PROGRAM;

    protected static ?int $navigationSort = 30;

    protected static string $accessArea = User::AREA_ASSIGNMENTS;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Tugas';

    protected static ?string $pluralModelLabel = 'Tugas';

    public static function form(Schema $schema): Schema
    {
        return AssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [SubmissionsRelationManager::class];
    }

    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        if (! $record->submissions()->exists()) {
            return true;
        }

        // Hard delete with student work stays admin/superadmin.
        return auth()->user()?->can(User::AREA_CONTENT) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignments::route('/'),
            'create' => CreateAssignment::route('/create'),
            'edit' => EditAssignment::route('/{record}/edit'),
        ];
    }
}
