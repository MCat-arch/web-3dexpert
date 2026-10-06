<?php

namespace App\Filament\Resources\PortfolioImages\Pages;

use App\Filament\Resources\PortfolioImages\PortfolioImageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePortfolioImage extends CreateRecord
{
    protected static string $resource = PortfolioImageResource::class;
}
