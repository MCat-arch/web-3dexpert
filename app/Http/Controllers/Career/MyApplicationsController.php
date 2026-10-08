<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyApplicationsController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('careers.mine', [
            'applications' => JobApplication::query()
                ->where('customer_id', $request->user('customer')->getKey())
                // Eager loaded: this list renders the position title per row,
                // and preventLazyLoading() throws outside production.
                ->with('vacancy')
                ->latest('submitted_at')
                ->get(),
        ]);
    }
}
