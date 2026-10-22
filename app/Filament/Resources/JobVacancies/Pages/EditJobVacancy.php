<?php

namespace App\Filament\Resources\JobVacancies\Pages;

use App\Filament\Resources\JobVacancies\JobVacancyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EditJobVacancy extends EditRecord
{
    protected static string $resource = JobVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['is_published'] ?? false) && filled($data['closes_at'] ?? null)) {
            $closesAt = Carbon::parse($data['closes_at']);
            $record = $this->getRecord();

            $newlyPast = $closesAt->isPast()
                && (! $record->is_published || ! $record->closes_at?->equalTo($closesAt));

            if ($newlyPast) {
                throw ValidationException::withMessages([
                    'data.closes_at' => 'Lowongan tidak dapat dipublikasikan dengan batas lamaran yang sudah lewat.',
                ]);
            }
        }

        return $data;
    }
}
