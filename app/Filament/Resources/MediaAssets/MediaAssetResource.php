<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\MediaAssets\Pages\ViewMediaAsset;
use App\Filament\Resources\MediaAssets\Schemas\MediaAssetInfolist;
use App\Filament\Resources\MediaAssets\Tables\MediaAssetsTable;
use App\Filament\Support\NavGroup;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\MediaUsage;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MediaAssetResource extends AdminResource
{
    protected static ?string $model = MediaAsset::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::CONTENT;

    protected static ?int $navigationSort = 90;

    protected static string $accessArea = User::AREA_MEDIA;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'Media';

    protected static ?string $pluralModelLabel = 'Media';

    /**
     * The legacy table has a BLOB `content` column. Never select it for a
     * listing; the media controller streams it only when a file is requested.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select([
            'id',
            'path',
            'file_name',
            'mime_type',
            'size',
            'sha256',
            'storage_path',
            'created_at',
            'updated_at',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MediaAssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaAssetsTable::configure($table);
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
        return auth()->user()?->isSuperadmin()
            && ! MediaUsage::isReferenced($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'view' => ViewMediaAsset::route('/{record}'),
        ];
    }
}
