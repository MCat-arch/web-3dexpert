<?php

namespace App\Support;

use App\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Illuminate\Support\Str;

class MediaUpload
{
    public static function field(
        string $name,
        string $directory,
        array $mimeTypes,
        int $maxKilobytes,
        bool $image = false,
    ): FileUpload {
        $field = FileUpload::make($name)
            ->disk('local')
            ->directory($directory)
            ->acceptedFileTypes($mimeTypes)
            ->maxSize($maxKilobytes)
            ->saveUploadedFileUsing(static function (TemporaryUploadedFile $file) use ($directory, $mimeTypes): string {
                $mimeType = $file->getMimeType();
                $extension = strtolower($file->getClientOriginalExtension());
                $allowedExtensions = [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                    'image/gif' => ['gif'],
                    'application/pdf' => ['pdf'],
                ];

                if (
                    ! in_array($mimeType, $mimeTypes, true)
                    || ! in_array($extension, $allowedExtensions[$mimeType] ?? [], true)
                ) {
                    throw new RuntimeException('The uploaded file type or extension is not allowed.');
                }

                $path = $directory.'/'.Str::ulid().'.'.$extension;

                return MediaAsset::storeFile($path, $file)->path;
            })
            ->getUploadedFileUsing(static function (string $file): ?array {
                $asset = MediaAsset::query()
                    ->where('path', $file)
                    ->first(['path', 'file_name', 'mime_type', 'size']);

                if (! $asset) {
                    return null;
                }

                return [
                    'name' => $asset->file_name,
                    'size' => $asset->size,
                    'type' => $asset->mime_type,
                    'url' => route('media.show', ['path' => $asset->path]),
                ];
            });

        return $image ? $field->image() : $field;
    }
}
