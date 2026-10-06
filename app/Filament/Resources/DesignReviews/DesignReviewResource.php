<?php

namespace App\Filament\Resources\DesignReviews;

use App\Filament\Resources\DesignReviews\Pages\CreateDesignReview;
use App\Filament\Resources\DesignReviews\Pages\EditDesignReview;
use App\Filament\Resources\DesignReviews\Pages\ListDesignReviews;
use App\Filament\Resources\DesignReviews\Schemas\DesignReviewForm;
use App\Filament\Resources\DesignReviews\Tables\DesignReviewsTable;
use App\Models\DesignReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DesignReviewResource extends Resource
{
    protected static ?string $model = DesignReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
