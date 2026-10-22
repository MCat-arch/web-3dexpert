<?php

namespace App\Filament\Resources\PortfolioImages;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\PortfolioImages\Pages\CreatePortfolioImage;
use App\Filament\Resources\PortfolioImages\Pages\EditPortfolioImage;
use App\Filament\Resources\PortfolioImages\Pages\ListPortfolioImages;
use App\Filament\Resources\PortfolioImages\Schemas\PortfolioImageForm;
use App\Filament\Resources\PortfolioImages\Tables\PortfolioImagesTable;
use App\Filament\Support\NavGroup;
use App\Models\PortfolioImage;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PortfolioImageResource extends AdminResource
{
    protected static ?string $model = PortfolioImage::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::CONTENT;

    protected static ?int $navigationSort = 62;

    protected static string $accessArea = User::AREA_CONTENT;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    public static function form(Schema $schema): Schema
    {
        return PortfolioImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortfolioImagesTable::configure($table);
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
            'index' => ListPortfolioImages::route('/'),
            'create' => CreatePortfolioImage::route('/create'),
            'edit' => EditPortfolioImage::route('/{record}/edit'),
        ];
    }
}
