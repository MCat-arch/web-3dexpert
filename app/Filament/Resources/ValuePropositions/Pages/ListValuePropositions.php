<?php

namespace App\Filament\Resources\ValuePropositions\Pages;

use App\Filament\Resources\ValuePropositions\ValuePropositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValuePropositions extends ListRecords
{
    protected static string $resource = ValuePropositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
