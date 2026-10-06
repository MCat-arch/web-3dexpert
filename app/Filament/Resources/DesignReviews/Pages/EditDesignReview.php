<?php

namespace App\Filament\Resources\DesignReviews\Pages;

use App\Filament\Resources\DesignReviews\DesignReviewResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDesignReview extends EditRecord
{
    protected static string $resource = DesignReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
