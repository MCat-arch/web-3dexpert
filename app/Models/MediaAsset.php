<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class MediaAsset extends Model
{
    protected $fillable = [
        'path', 'file_name', 'mime_type', 'size', 'sha256', 'storage_path', 'content',
    ];

    protected $hidden = ['content'];

    public static function storeFile(string $path, UploadedFile|string $file, ?string $fileName = null): self
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! $realPath || ! is_file($realPath)) {
            throw new RuntimeException("Media source does not exist: {$path}");
        }

        $stream = fopen($realPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open media source: {$path}");
        }

        try {
            $size = filesize($realPath);
            $sha256 = hash_file('sha256', $realPath);
            $mimeType = self::detectMimeType($realPath);

            if ($size === false || $sha256 === false) {
                throw new RuntimeException("Unable to inspect media source: {$path}");
            }

            return self::storeStream(
                $path,
                $fileName ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : basename($realPath)),
                $mimeType,
                $size,
                $sha256,
                $stream,
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    public static function storeStream(
        string $path,
        string $fileName,
        string $mimeType,
        int $size,
        string $sha256,
        mixed $stream,
    ): self {
        if (! is_resource($stream)) {
            throw new RuntimeException('Media content must be provided as a readable stream.');
        }

        self::assertValidPath($path);

        $existing = self::query()->where('path', $path)->first([
            'id', 'sha256', 'storage_path',
        ]);

        if ($existing) {
            if (! hash_equals($existing->sha256, $sha256)) {
                throw new RuntimeException("A different media file already uses path [{$path}].");
            }
        }

        $disk = Storage::disk('media');
        $fileCreated = false;

        if ($disk->exists($path)) {
            $storedHash = hash_file('sha256', $disk->path($path));

            if (
                $storedHash === false
                || (int) $disk->size($path) !== $size
                || ! hash_equals($sha256, $storedHash)
            ) {
                throw new RuntimeException("A different media file already exists at [{$path}].");
            }

            if ($existing?->storage_path === $path) {
                return $existing;
            }
        } else {
            rewind($stream);

            try {
                if (! $disk->writeStream($path, $stream)) {
                    throw new RuntimeException("Unable to store media source: {$path}");
                }

                $fileCreated = true;
                $storedHash = hash_file('sha256', $disk->path($path));

                if (
                    $storedHash === false
                    || (int) $disk->size($path) !== $size
                    || ! hash_equals($sha256, $storedHash)
                ) {
                    throw new RuntimeException("Media source changed while it was being stored: {$path}");
                }
            } catch (Throwable $exception) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }

                throw $exception;
            }
        }

        try {
            if ($existing) {
                $existing->forceFill([
                    'storage_path' => $path,
                    'content' => '',
                ])->save();

                return $existing;
            }

            return self::query()->create([
                'path' => $path,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'size' => $size,
                'sha256' => $sha256,
                'storage_path' => $path,
                'content' => '',
            ]);
        } catch (Throwable $exception) {
            if ($fileCreated) {
                $disk->delete($path);
            }

            throw $exception;
        }
    }

    public static function existsAt(string $path): bool
    {
        return self::query()->where('path', $path)->exists();
    }

    private static function assertValidPath(string $path): void
    {
        if (
            $path === ''
            || str_contains($path, '..')
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || preg_match('/\A[A-Za-z0-9_.\/ -]+\z/', $path) !== 1
        ) {
            throw new RuntimeException('The media path is invalid.');
        }
    }

    private static function detectMimeType(string $path): string
    {
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to inspect media source: {$path}");
        }

        try {
            $header = fread($stream, 8192);
        } finally {
            fclose($stream);
        }

        if ($header === false || $header === '') {
            throw new RuntimeException("Unable to read media type from source: {$path}");
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($header);

        if (! is_string($mimeType) || $mimeType === '') {
            throw new RuntimeException("Unable to detect media type for source: {$path}");
        }

        return $mimeType;
    }
}
