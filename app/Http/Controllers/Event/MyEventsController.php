<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyEventsController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('events.mine', [
            'registrations' => EventRegistration::query()
                ->where('customer_id', $request->user('customer')->getKey())
                // Eager-loaded: this list renders event titles and dates, and
                // preventLazyLoading() would throw outside production.
                ->with('event')
                ->latest('registered_at')
                ->get(),
        ]);
    }
}
