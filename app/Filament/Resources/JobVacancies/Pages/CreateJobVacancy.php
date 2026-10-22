<?php

namespace App\Filament\Resources\JobVacancies\Pages;

use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Models\JobVacancy;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateJobVacancy extends CreateRecord
{
    protected static string $resource = JobVacancyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->guardPublishDate($data, null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardPublishDate(array $data, ?JobVacancy $record): void
    {
        if (! ($data['is_published'] ?? false) || blank($data['closes_at'] ?? null)) {
            return;
        }

        $closesAt = Carbon::parse($data['closes_at']);

        if (! $closesAt->isPast()) {
            return;
        }

        // On edit, leave already-published past vacancies editable so staff can
        // fix content or unpublish them; only block a new publication.
        if ($record?->is_published && $record->closes_at?->equalTo($closesAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'data.closes_at' => 'Lowongan tidak dapat dipublikasikan dengan batas lamaran yang sudah lewat.',
        ]);
    }
}
