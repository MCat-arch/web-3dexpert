<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Service;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __invoke(): View
    {
        $services = Service::published()
            ->ordered()
            ->with('features')
            ->get();

        $faqs = Faq::published()
            ->ordered()
            ->get();

        return view('services.index', compact('services', 'faqs'));
    }
}
