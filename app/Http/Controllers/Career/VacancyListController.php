<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use Illuminate\View\View;

class VacancyListController extends Controller
{
    public function __invoke(): View
    {
        return view('careers.index', [
            // scopeOpen() already combines published + not-yet-closed.
            'vacancies' => JobVacancy::open()->latest('created_at')->paginate(20),
        ]);
    }
}
