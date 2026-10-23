<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    /**
     * Normalise the translation map before Filament fills the form.
     *
     * HasTranslations resolves this attribute to a locale string for normal
     * reads. The raw column can also be SQL NULL on legacy rows. Filament's
     * KeyValue state cast expects an array and calls array_key_first(), so a
     * null/string state crashed the whole edit page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['value_translations'] = $this->record->getTranslations('value_translations');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
