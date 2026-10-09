<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Contracts\View\View;

class CourseCatalogController extends Controller
{
    public const PER_PAGE = 9;

    public function __invoke(): View
    {
        $courses = Course::published()
            ->ordered()
            ->with(['coverMedia', 'sections.lessons'])
            ->paginate(self::PER_PAGE);

        return view('courses.index', compact('courses'));
    }
}
