@extends('layouts.app')

@section('title', $event->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $event->description), 155))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 sm:py-16">
        <nav aria-label="Breadcrumb" class="text-sm text-neutral-500">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ $lroute('home') }}" class="hover:text-amber-400">{{ __('nav.home') }}</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ $lroute('events.index') }}" class="hover:text-amber-400">{{ __('events.title') }}</a></li>
                <li aria-hidden="true">/</li>
                <li class="text-neutral-300" aria-current="page">{{ Str::limit($event->title, 40) }}</li>
            </ol>
        </nav>

        <h1 class="mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $event->title }}</h1>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('events.when') }}</dt>
                <dd class="mt-1 text-sm text-neutral-200">
                    {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot;
                    {{ $event->starts_at->format('H:i') }}
                </dd>
            </div>

            @if ($event->venue)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('events.where') }}</dt>
                    <dd class="mt-1 text-sm text-neutral-200">{{ $event->venue }}</dd>
                </div>
            @endif
        </dl>

        @if ($event->description)
            <div class="mt-8 space-y-4 text-sm leading-relaxed text-neutral-300">
                @foreach (preg_split('/\R{2,}/', (string) $event->description) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        @endif

        {{-- Enrollment CTA. Every branch below is a distinct real state --
             collapsing any two is how a full event still shows a Register
             button, or a guest sees one that cannot work. --}}
        <div class="mt-10 rounded-xl border border-white/10 bg-neutral-900 p-6">
            @if ($registration && $registration->occupiesSeat())
                <p class="text-sm font-semibold text-emerald-400">{{ __('events.registered') }}</p>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ __('events.ticket_code') }}:
                    <span class="font-mono text-neutral-200">{{ $registration->ticket_code }}</span>
                </p>

                @if ($customerCan('cancel', $registration))
                    <form method="POST"
                          action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}"
                          class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-neutral-400 underline hover:text-white">
                            {{ __('events.cancel') }}
                        </button>
                    </form>
                @endif

            @elseif ($event->starts_at->isPast())
                <p class="text-sm text-neutral-500">{{ __('events.past') }}</p>

            @elseif (! $event->registrationIsOpen())
                <p class="text-sm text-neutral-500">{{ __('events.closed') }}</p>

            @elseif (! $event->hasSeatsLeft())
                <p class="text-sm text-red-400">{{ __('events.full') }}</p>

            @elseif (! $event->isFree())
                <p class="text-sm font-semibold text-amber-400">
                    {{ __('events.paid_unavailable') }}
                </p>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ __('events.paid_price', ['price' => number_format((float) $event->price, 0, ',', '.')]) }}
                </p>

            @elseif (auth('customer')->check())
                <form method="POST" action="{{ $lroute('events.enroll', ['event' => $event->slug]) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950
                                   transition hover:bg-amber-400">
                        {{ __('events.register') }}
                    </button>
                </form>

            @else
                <a href="{{ $lroute('customer.login') }}"
                   class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950
                          transition hover:bg-amber-400">
                    {{ __('events.register_login') }}
                </a>
            @endif

            @if ($event->registrationIsOpen() && ! $event->isUnlimited())
                <p class="mt-4 text-xs text-neutral-500">
                    {{ trans_choice('events.seats_left', $event->seatsLeft(), ['count' => $event->seatsLeft()]) }}
                </p>
            @endif
        </div>
    </section>
@endsection
