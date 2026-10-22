<?php

namespace App\Filament\Resources\Courses\Pages;

use App\Filament\Resources\Courses\CourseResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $this->warnIfEmptyCurriculum();
    }

    private function warnIfEmptyCurriculum(): void
    {
        $record = $this->getRecord();

        if (! $record->hasLms() || ! $record->is_published || $record->lessons()->exists()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Kurikulum belum diisi')
            ->body('Kursus LMS ini sudah diterbitkan tetapi belum memiliki materi. Tambahkan bab dan materi melalui bagian Kurikulum.')
            ->persistent()
            ->send();
    }
}
