<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacancyListController extends Controller
{
    public function __invoke(Request $request): View
    {
        $currentType = $request->query('type');
        $query = JobVacancy::open()->latest('created_at');

        if ($currentType === 'internship') {
            $query->where(function ($q) {
                $q->where('employment_type', 'like', '%intern%')
                  ->orWhere('employment_type', 'like', '%magang%');
            });
        } elseif ($currentType === 'full-time') {
            $query->where(function ($q) {
                $q->where('employment_type', 'like', '%full%')
                  ->orWhere('employment_type', 'like', '%karyawan%');
            });
        }

        return view('careers.index', [
            'vacancies' => $query->paginate(20)->withQueryString(),
            'currentType' => $currentType,
        ]);
    }
}
