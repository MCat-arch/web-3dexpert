<?php

namespace App\Support;

use App\Models\MediaAsset;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

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
                [$mimeType, $extension] = self::validatedType($file, $mimeTypes);

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

    /**
     * Upload field for a foreign key that points at media_assets.id rather
     * than a path column. Course lesson attachments use this form.
     */
    public static function assetIdField(
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
            ->saveUploadedFileUsing(static function (TemporaryUploadedFile $file) use ($directory, $mimeTypes): int {
                [, $extension] = self::validatedType($file, $mimeTypes);

                $path = $directory.'/'.Str::ulid().'.'.$extension;
                $asset = MediaAsset::storeFile($path, $file, $file->getClientOriginalName());

                return (int) $asset->getKey();
            })
            ->getUploadedFileUsing(static function ($file): ?array {
                $asset = MediaAsset::query()->find($file);

                if (! $asset) {
                    return null;
                }

                return [
                    'name' => $asset->file_name,
                    'size' => $asset->size,
                    'type' => $asset->mime_type,
                    'url' => route('media.show', ['path' => $asset->storage_path ?? $asset->path]),
                ];
            });

        return $image ? $field->image() : $field;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     * @return array{0: string, 1: string}
     */
    private static function validatedType(TemporaryUploadedFile $file, array $mimeTypes): array
    {
        $mimeType = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
            'application/pdf' => ['pdf'],
            'application/zip' => ['zip'],
            'application/x-zip-compressed' => ['zip'],
        ];

        if (
            ! in_array($mimeType, $mimeTypes, true)
            || ! in_array($extension, $allowedExtensions[$mimeType] ?? [], true)
        ) {
            throw new RuntimeException('The uploaded file type or extension is not allowed.');
        }

        return [$mimeType, $extension];
    }
}
