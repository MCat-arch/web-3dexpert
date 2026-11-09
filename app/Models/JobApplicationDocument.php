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
     * Streams the file inline so staff can read it in the browser.
     *
     * Only PDFs are shown inline: every upload passes both an extension and a
     * content-sniffing check, and this method falls back to an attachment if a
     * stored MIME type is ever anything else. That keeps an "uploaded file
     * rendered inline" from becoming a stored-XSS vector if the validation is
     * ever loosened.
     *
     * No public URL is ever generated for these: the disk is private and
     * outside the document root, so the only way to read one is through
     * ApplicationDocumentController, which authorises first.
     */
    public function view(): StreamedResponse
    {
        if ($this->mime_type !== 'application/pdf') {
            return $this->download();
        }

        return Storage::disk($this->disk)->response(
            $this->path,
            $this->original_filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Streams the file as an attachment (kept for the non-PDF fallback).
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
