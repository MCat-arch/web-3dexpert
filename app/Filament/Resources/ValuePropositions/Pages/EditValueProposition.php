<?php

namespace App\Filament\Resources\ValuePropositions\Pages;

use App\Filament\Resources\ValuePropositions\ValuePropositionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValueProposition extends EditRecord
{
    protected static string $resource = ValuePropositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
