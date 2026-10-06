<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class MediaAsset extends Model
{
    private const STORAGE_CHUNK_BYTES = 1_048_576;

    protected $fillable = [
        'path', 'file_name', 'mime_type', 'size', 'sha256', 'content',
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
     * @param resource $stream
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
        self::assertPacketCapacity(self::STORAGE_CHUNK_BYTES);

        $existing = self::query()->where('path', $path)->first(['id', 'sha256']);

        if ($existing) {
            if (! hash_equals($existing->sha256, $sha256)) {
                throw new RuntimeException("A different media file already uses path [{$path}].");
            }

            return $existing;
        }

        rewind($stream);
        $now = now()->format('Y-m-d H:i:s');

        try {
            $id = DB::transaction(function () use ($path, $fileName, $mimeType, $size, $sha256, $stream, $now): int {
                $id = DB::table('media_assets')->insertGetId([
                    'path' => $path,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'sha256' => $sha256,
                    'content' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $driver = DB::connection()->getDriverName();
                $appendExpression = match ($driver) {
                    'mysql' => 'CONCAT(content, ?)',
                    'sqlite' => 'CAST(content || ? AS BLOB)',
                    default => throw new RuntimeException("Chunked media storage is not supported for database driver [{$driver}]."),
                };

                $statement = DB::connection()->getPdo()->prepare(
                    "UPDATE media_assets SET content = {$appendExpression} WHERE id = ?"
                );
                $bytesWritten = 0;
                $contentHash = hash_init('sha256');

                while (! feof($stream)) {
                    $chunk = fread($stream, self::STORAGE_CHUNK_BYTES);

                    if ($chunk === false) {
                        throw new RuntimeException("Unable to read media source: {$path}");
                    }

                    if ($chunk === '') {
                        break;
                    }

                    $bytesWritten += strlen($chunk);
                    hash_update($contentHash, $chunk);
                    $statement->bindValue(1, $chunk, PDO::PARAM_LOB);
                    $statement->bindValue(2, $id, PDO::PARAM_INT);
                    $statement->execute();
                }

                if ($bytesWritten !== $size || ! hash_equals($sha256, hash_final($contentHash))) {
                    throw new RuntimeException("Media source changed while it was being stored: {$path}");
                }

                return $id;
            });
        } catch (Throwable $exception) {
            $existing = self::query()->where('path', $path)->first(['id', 'sha256']);

            if ($existing && hash_equals($existing->sha256, $sha256)) {
                return $existing;
            }

            throw $exception;
        }

        return self::query()->whereKey($id)->firstOrFail(['id', 'path', 'file_name', 'mime_type', 'size', 'sha256']);
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

    private static function assertPacketCapacity(int $size): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $packet = DB::selectOne("SHOW VARIABLES LIKE 'max_allowed_packet'");
        $limit = (int) ($packet->Value ?? 0);

        if ($limit > 0 && $size > $limit - 1024) {
            throw new RuntimeException(
                "A media storage chunk is {$size} bytes, exceeding MySQL max_allowed_packet ({$limit} bytes). "
                .'Increase max_allowed_packet to at least 2 MB before importing or uploading media.'
            );
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
