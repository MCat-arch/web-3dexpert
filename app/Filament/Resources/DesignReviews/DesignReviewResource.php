<?php

namespace App\Filament\Resources\DesignReviews;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\DesignReviews\Pages\CreateDesignReview;
use App\Filament\Resources\DesignReviews\Pages\EditDesignReview;
use App\Filament\Resources\DesignReviews\Pages\ListDesignReviews;
use App\Filament\Resources\DesignReviews\Schemas\DesignReviewForm;
use App\Filament\Resources\DesignReviews\Tables\DesignReviewsTable;
use App\Filament\Support\NavGroup;
use App\Models\DesignReview;
use App\Models\User;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DesignReviewResource extends AdminResource
{
    protected static ?string $model = DesignReview::class;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::CONTENT;

    protected static ?int $navigationSort = 50;

    protected static string $accessArea = User::AREA_CONTENT;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return DesignReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DesignReviewsTable::configure($table);
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
            'index' => ListDesignReviews::route('/'),
            'create' => CreateDesignReview::route('/create'),
            'edit' => EditDesignReview::route('/{record}/edit'),
        ];
    }
}
