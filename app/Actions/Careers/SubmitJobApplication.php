<?php

namespace App\Actions\Careers;

use App\Exceptions\JobApplicationException;
use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobVacancy;
use App\Notifications\Careers\ApplicationReceived;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Records a job application and stores its documents.
 *
 * The hard part here is not the insert, it is that **the filesystem is not
 * transactional**. If the transaction rolls back after a file is written, the
 * row disappears and the file stays -- an orphaned CV that no row points at.
 *
 * That is worse than it sounds: D11's retention purge and P8's erasure path
 * both work from job_application_documents rows, so neither can reach a file
 * they do not know about. The result is personal data retained indefinitely
 * with no record of it, which is precisely what those features exist to
 * prevent.
 *
 * Two layers handle it:
 *
 *   1. Here: every path written is tracked, and deleted if the transaction
 *      fails. Deterministic for the common case.
 *   2. PurgeOrphanedApplicationDocuments: a scheduled sweep for files with no
 *      matching row, older than a safe window. Layer 1 cannot survive the
 *      process being killed mid-transaction; this can.
 */
class SubmitJobApplication
{
    /** @var array<int, array{disk: string, path: string}> */
    private array $written = [];

    /**
     * @param  array<int, UploadedFile>  $documents
     */
    public function __invoke(
        JobVacancy $vacancy,
        Customer $customer,
        array $documents,
        ?string $coverLetter = null,
    ): JobApplication {
        if (! $customer->canEnroll()) {
            throw JobApplicationException::notVerified();
        }

        if (! $vacancy->is_open) {
            throw JobApplicationException::vacancyClosed();
        }

        $existing = JobApplication::where('job_vacancy_id', $vacancy->getKey())
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($existing && $existing->isActive()) {
            throw JobApplicationException::alreadyApplied();
        }

        $this->written = [];

        try {
            $application = DB::transaction(function () use ($vacancy, $customer, $documents, $coverLetter, $existing) {
                $application = $this->record($vacancy, $customer, $coverLetter, $existing);

                // A reapply replaces the previous attempt's documents rather
                // than accumulating both sets.
                $this->discardPreviousDocuments($application);

                foreach ($documents as $index => $file) {
                    $this->store($application, $file, $index);
                }

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            /*
             * The pre-check above and the insert are not atomic, so two
             * concurrent submissions from the same customer both pass it.
             * Without this the loser gets a 500 from a double-clicked form.
             */
            $this->discardWrittenFiles();

            throw JobApplicationException::alreadyApplied();
        } catch (\Throwable $e) {
            // Any other failure: the row is gone, so the files must go too.
            $this->discardWrittenFiles();

            throw $e;
        }

        // After commit: a queued job must never be able to read a row that a
        // rollback is about to remove.
        DB::afterCommit(fn () => $customer->notify(
            new ApplicationReceived($application->fresh(['vacancy']))
        ));

        return $application;
    }

    private function record(
        JobVacancy $vacancy,
        Customer $customer,
        ?string $coverLetter,
        ?JobApplication $existing,
    ): JobApplication {
        $attributes = [
            /*
             * Identity snapshotted at submission.
             *
             * This is the user's stated requirement -- the admin must be able
             * to read this "even if the user ... already deleted". Because
             * these columns are copies rather than a join, deleting the
             * account nulls customer_id and leaves everything else intact.
             */
            'applicant_name' => $customer->name,
            'applicant_email' => $customer->email,
            'applicant_phone' => $customer->phone,

            'cover_letter' => $coverLetter,
            'status' => JobApplication::STATUS_SUBMITTED,
            'status_changed_at' => now(),
            'submitted_at' => now(),
            'reviewed_by' => null,
        ];

        // A withdrawn row is reused: the (job_vacancy_id, customer_id) unique
        // index has no portable partial form in MySQL, so reapplying updates
        // rather than inserting alongside.
        if ($existing) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return JobApplication::create($attributes + [
            'job_vacancy_id' => $vacancy->getKey(),
            'customer_id' => $customer->getKey(),
        ]);
    }

    private function discardPreviousDocuments(JobApplication $application): void
    {
        foreach ($application->documents as $document) {
            $document->deleteFile();
            $document->delete();
        }
    }

    private function store(JobApplication $application, UploadedFile $file, int $index): void
    {
        /*
         * The on-disk name is GENERATED, never the uploaded one.
         *
         * A user-supplied filename on disk is how path traversal and
         * extension confusion get in, and it is what the legacy upload did:
         * uniqid().'_'.$_FILES[...]['name']. The original is kept only as a
         * column, for display and for the download filename.
         */
        $name = Str::uuid()->toString().'.pdf';
        $directory = 'applications/'.$application->getKey();

        $path = $file->storeAs($directory, $name, 'applications');

        $this->written[] = ['disk' => 'applications', 'path' => $path];

        JobApplicationDocument::create([
            'job_application_id' => $application->getKey(),
            // First file is the CV; the rest are supporting documents. A
            // per-file type picker is more UI than this flow needs.
            'type' => $index === 0 ? JobApplicationDocument::TYPE_CV : JobApplicationDocument::TYPE_OTHER,
            'disk' => 'applications',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', Storage::disk('applications')->path($path)),
        ]);
    }

    private function discardWrittenFiles(): void
    {
        foreach ($this->written as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        $this->written = [];
    }
}
