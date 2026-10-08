<?php

namespace App\Jobs;

use App\Models\JobApplicationDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Second layer of M1: deletes applicant files that no database row points at.
 *
 * SubmitJobApplication already removes files when its transaction fails, which
 * covers the ordinary case deterministically. It cannot cover the process being
 * killed between the write and the rollback -- this can.
 *
 * Why it matters beyond tidiness: D11's retention purge and P8's erasure path
 * both work from job_application_documents rows. A file with no row is personal
 * data that neither can ever reach, retained indefinitely with no record of its
 * existence. That is the exact outcome those features exist to prevent.
 *
 * The age floor is the safety mechanism. Never delete a file younger than the
 * longest plausible in-flight request, or this job will race a legitimate
 * upload and destroy a CV mid-submission.
 */
class PurgeOrphanedApplicationDocuments implements ShouldQueue
{
    use Queueable;

    public const DISK = 'applications';

    /** Files younger than this are never touched. */
    public const MIN_AGE_HOURS = 24;

    public int $tries = 1;

    public function __construct(public bool $dryRun = false) {}

    public function handle(): void
    {
        $disk = Storage::disk(self::DISK);
        $known = JobApplicationDocument::where('disk', self::DISK)->pluck('path')->all();
        $known = array_flip($known);

        $cutoff = now()->subHours(self::MIN_AGE_HOURS)->getTimestamp();
        $deleted = 0;
        $bytes = 0;

        foreach ($disk->allFiles() as $path) {
            if (isset($known[$path])) {
                continue;
            }

            if ($disk->lastModified($path) > $cutoff) {
                // Too recent -- may belong to a request still in flight.
                continue;
            }

            $bytes += $disk->size($path);

            if (! $this->dryRun) {
                $disk->delete($path);
            }

            $deleted++;
        }

        if ($deleted > 0) {
            // Logged rather than silent: an orphan means SubmitJobApplication's
            // cleanup did not run, which is worth knowing about.
            Log::warning('Purged orphaned applicant documents.', [
                'count' => $deleted,
                'bytes' => $bytes,
                'dry_run' => $this->dryRun,
            ]);
        }
    }
}
