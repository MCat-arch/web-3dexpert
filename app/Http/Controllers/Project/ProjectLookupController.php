<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectLookupRequest;
use App\Support\ProjectLookup;
use Illuminate\View\View;

class ProjectLookupController extends Controller
{
    public function show(): View
    {
        return view('tracker.index', ['project' => null, 'searched' => false]);
    }

    /**
     * One negative branch, on purpose.
     *
     * Wrong code, wrong contact, and already-finished all arrive here as null
     * from ProjectLookup::find(). Rendering the same view with the same
     * message and the same status code is what makes them indistinguishable --
     * there is deliberately no match() on a reason, because having a reason
     * available is how a distinguishable message gets added later by accident.
     *
     * Results are never cached: this is per-customer data keyed by a secret.
     */
    public function find(ProjectLookupRequest $request, ProjectLookup $lookup): View
    {
        $project = $lookup->find(
            $request->string('project_code')->toString(),
            $request->string('verification')->toString(),
        );

        return view('tracker.index', [
            'project' => $project,
            'searched' => true,
        ]);
    }
}
