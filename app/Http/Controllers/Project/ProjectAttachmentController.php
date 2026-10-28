<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ProjectAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one tracker attachment.
 *
 * The URL is signed and short-lived, minted only after a successful lookup
 * (code + contact). Being signed is not enough on its own: trackability is
 * re-checked here on every request, so hiding or cancelling a project stops
 * its files immediately even if someone kept a link.
 */
class ProjectAttachmentController extends Controller
{
    public function __invoke(ProjectAttachment $attachment): StreamedResponse
    {
        $project = $attachment->project;

        abort_unless($project && $project->isTrackable(), 404);

        // Deliverables belong to the finished project; a progress-only file is
        // available the whole time the project is trackable.
        abort_if($attachment->isDeliverable() && ! $project->isCompleted(), 404);

        $disk = Storage::disk(ProjectAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
