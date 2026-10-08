<?php

namespace App\Http\Controllers;

use App\Models\JobApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The ONLY way an applicant document can be read.
 *
 * The 'applications' disk lives under storage_path, outside the document root,
 * and no signed or public URL is ever generated for these files. So there is
 * exactly one path to the bytes, and it authorises first.
 *
 * That is the whole design: the legacy site's RCE came from uploads landing
 * somewhere the web server would serve directly, with no check at all
 * (finding 3.1-5). Here the web server cannot reach them even in principle.
 *
 * Staff only -- see JobApplicationPolicy::viewDocuments. Not granted to the
 * owning applicant: they already hold the file they uploaded, and every extra
 * party who can fetch a CV is another way for it to leak.
 */
class ApplicationDocumentController extends Controller
{
    public function __invoke(Request $request, JobApplicationDocument $document): StreamedResponse
    {
        /*
         * Resolved explicitly against the `web` guard. This route is
         * staff-only, and Gate::authorize() reading the default guard happens
         * to be correct here -- but it is written out so the intent survives a
         * future change to auth.defaults.guard.
         */
        Gate::forUser($request->user('web'))
            ->authorize('viewDocuments', $document->application);

        abort_unless($document->exists(), 404);

        // P8 logs this: who downloaded whose CV, and when. Applicant documents
        // are the densest PII in the application, so access is auditable.
        return $document->download();
    }
}
