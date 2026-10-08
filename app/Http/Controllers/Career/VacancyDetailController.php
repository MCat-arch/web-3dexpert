<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacancyDetailController extends Controller
{
    public function __invoke(Request $request, JobVacancy $vacancy): View
    {
        abort_unless($vacancy->is_published, 404);

        $customer = $request->user('customer');

        return view('careers.show', [
            'vacancy' => $vacancy,
            // Drives the CTA state. Resolved here so Blade stays free of
            // queries (guide.md section 3).
            'application' => $customer
                ? JobApplication::where('job_vacancy_id', $vacancy->getKey())
                    ->where('customer_id', $customer->getKey())
                    ->first()
                : null,
        ]);
    }
}
