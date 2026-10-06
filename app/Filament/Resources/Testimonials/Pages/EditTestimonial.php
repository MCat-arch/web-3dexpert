<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditTestimonial extends EditRecord
{
    protected static string $resource = TestimonialResource::class;

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $isHighlighted = (bool) ($this->data['is_highlighted'] ?? false);

        if ($record->is_highlighted && ! $isHighlighted
            && $record::query()->highlighted()->count() <= $record::MIN_HIGHLIGHTED) {
            throw ValidationException::withMessages([
                'data.is_highlighted' => 'Minimal dua testimoni harus tetap ditampilkan sebagai unggulan.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
