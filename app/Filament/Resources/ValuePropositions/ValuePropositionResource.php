<?php

namespace App\Filament\Resources\ValuePropositions;

use App\Filament\Resources\ValuePropositions\Pages\CreateValueProposition;
use App\Filament\Resources\ValuePropositions\Pages\EditValueProposition;
use App\Filament\Resources\ValuePropositions\Pages\ListValuePropositions;
use App\Filament\Resources\ValuePropositions\Schemas\ValuePropositionForm;
use App\Filament\Resources\ValuePropositions\Tables\ValuePropositionsTable;
use App\Models\ValueProposition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValuePropositionResource extends Resource
{
    protected static ?string $model = ValueProposition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ValuePropositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValuePropositionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListValuePropositions::route('/'),
            'create' => CreateValueProposition::route('/create'),
            'edit' => EditValueProposition::route('/{record}/edit'),
        ];
    }
}
