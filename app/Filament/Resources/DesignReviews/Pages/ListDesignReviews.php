<?php

namespace App\Filament\Resources\DesignReviews\Pages;

use App\Filament\Resources\DesignReviews\DesignReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDesignReviews extends ListRecords
{
    protected static string $resource = DesignReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
