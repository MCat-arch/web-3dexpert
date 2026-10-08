<?php

namespace App\Http\Controllers\Career;

use App\Actions\Careers\SubmitJobApplication;
use App\Exceptions\JobApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Careers\JobApplicationRequest;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobApplicationController extends Controller
{
    public function create(Request $request, JobVacancy $vacancy): View
    {
        abort_unless($vacancy->is_open, 404);

        return view('careers.apply', ['vacancy' => $vacancy]);
    }

    public function store(
        JobApplicationRequest $request,
        JobVacancy $vacancy,
        SubmitJobApplication $submit,
    ): RedirectResponse {
        abort_unless($vacancy->is_published, 404);

        try {
            $submit(
                $vacancy,
                $request->user('customer'),
                $request->file('documents', []),
                $request->input('cover_letter'),
            );
        } catch (JobApplicationException $e) {
            // Expected business outcomes -- closed, already applied -- are
            // flashed rather than raised as faults.
            return back()->withInput()->with('error', $e->userMessage());
        }

        return redirect()
            ->to(Locale::route('customer.applications'))
            ->with('status', __('careers.submitted'));
    }

    public function withdraw(Request $request, JobApplication $application): RedirectResponse
    {
        /*
         * Gate::forUser, never $this->authorize().
         *
         * The default guard is `web`, so the Gate would hand
         * JobApplicationPolicy a User where this path needs a Customer -- the
         * check would fail closed for the real applicant and present as
         * "withdraw does nothing".
         */
        Gate::forUser($request->user('customer'))->authorize('withdraw', $application);

        $application->forceFill([
            'status' => JobApplication::STATUS_WITHDRAWN,
            'status_changed_at' => now(),
        ])->save();

        return back()->with('status', __('careers.withdrawn'));
    }
}
