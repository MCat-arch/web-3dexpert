<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class CourseCatalogController extends Controller
{
    public const PER_PAGE = 9;

    public function __invoke(Request $request): View
    {
        $currentLevel = $request->query('level');
        $query = Course::published()->ordered();

        if (in_array($currentLevel, ['beginner', 'intermediate', 'advanced'], true)) {
            $query->where('level', $currentLevel);
        }

        $courses = $query
            ->with(['coverMedia', 'lessons', 'sections.lessons'])
            ->withCount('lessons')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('courses.index', compact('courses', 'currentLevel'));
    }
}
