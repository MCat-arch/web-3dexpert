<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    private const ALLOWED_MIMES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    public function show(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..') || str_starts_with($path, '/'), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::ALLOWED_MIMES[$extension]), 404);

        $asset = MediaAsset::query()
            ->where('path', $path)
            ->first(['path', 'file_name', 'mime_type', 'storage_path']);
        abort_unless($asset, 404);
        abort_unless($asset->mime_type === self::ALLOWED_MIMES[$extension], 404);

        return response()->stream(
            function () use ($asset, $path): void {
                if (filled($asset->storage_path)) {
                    $stream = Storage::disk('media')->readStream($asset->storage_path);
                    abort_unless(is_resource($stream), 404);

                    try {
                        fpassthru($stream);
                    } finally {
                        fclose($stream);
                    }

                    return;
                }

                $connection = DB::connection();
                $pdo = $connection->getPdo();
                $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
                $useBufferedQueries = $isMysql
                    ? $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY)
                    : null;

                if ($isMysql) {
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                }

                $statement = null;

                try {
                    $statement = $pdo->prepare('SELECT content FROM media_assets WHERE path = ?');
                    $statement->bindValue(1, $path);
                    $statement->execute();
                    $content = null;
                    $statement->bindColumn(1, $content, PDO::PARAM_LOB);

                    abort_unless($statement->fetch(PDO::FETCH_BOUND), 404);

                    if (is_resource($content)) {
                        fpassthru($content);
                        fclose($content);
                    } else {
                        echo (string) $content;
                    }
                } finally {
                    $statement?->closeCursor();

                    if ($isMysql) {
                        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $useBufferedQueries);
                    }
                }
            },
            200,
            [
                'Content-Type' => $asset->mime_type,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    $extension === 'pdf' ? 'attachment' : 'inline',
                    $asset->file_name,
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'public, max-age=2592000',
            ],
        );
    }
}
