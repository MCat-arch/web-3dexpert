<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationDocument extends Model
{
    use HasFactory;

    public const TYPE_CV = 'cv';

    public const TYPE_PORTFOLIO = 'portfolio';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'job_application_id', 'type', 'disk', 'path',
        'original_filename', 'mime_type', 'size_bytes', 'checksum',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return [self::TYPE_CV, self::TYPE_PORTFOLIO, self::TYPE_CERTIFICATE, self::TYPE_OTHER];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Streams the file as an attachment.
     *
     * No public URL is ever generated for these: the disk is private and
     * outside the document root, so the only way to read one is through
     * ApplicationDocumentController, which authorises first.
     *
     * Forced to 'attachment' rather than 'inline' deliberately -- an uploaded
     * file rendered inline in the browser is a stored-XSS vector if the MIME
     * check is ever loosened.
     */
    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download(
            $this->path,
            $this->original_filename,
            [
                'Content-Type' => $this->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** Human-readable size for the admin table. */
    public function humanSize(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb < 1024
            ? number_format($kb, 0).' KB'
            : number_format($kb / 1024, 1).' MB';
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
