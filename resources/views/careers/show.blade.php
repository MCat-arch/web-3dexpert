@extends('layouts.app')

@section('title', $vacancy->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $vacancy->description), 155))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 sm:py-16">
        <nav aria-label="Breadcrumb" class="text-sm text-neutral-500">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ $lroute('home') }}" class="hover:text-amber-400">{{ __('nav.home') }}</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ $lroute('careers.index') }}" class="hover:text-amber-400">{{ __('careers.title') }}</a></li>
                <li aria-hidden="true">/</li>
                <li class="text-neutral-300" aria-current="page">{{ Str::limit($vacancy->title, 40) }}</li>
            </ol>
        </nav>

        <h1 class="mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $vacancy->title }}</h1>

        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('careers.department') }}</dt>
                <dd class="mt-1 text-sm text-neutral-200">{{ $vacancy->department }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('careers.location') }}</dt>
                <dd class="mt-1 text-sm text-neutral-200">{{ $vacancy->location }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">{{ __('careers.employment_type') }}</dt>
                <dd class="mt-1 text-sm text-neutral-200">{{ $vacancy->employment_type }}</dd>
            </div>
        </dl>

        @if ($vacancy->description)
            <div class="mt-8">
                <h2 class="text-xl font-semibold text-white">{{ __('careers.description') }}</h2>
                <div class="mt-3 space-y-4 text-sm leading-relaxed text-neutral-300">
                    @foreach (preg_split('/\R{2,}/', (string) $vacancy->description) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($vacancy->requirement)
            <div class="mt-8">
                <h2 class="text-xl font-semibold text-white">{{ __('careers.requirement') }}</h2>
                <div class="mt-3 space-y-4 text-sm leading-relaxed text-neutral-300">
                    @foreach (preg_split('/\R{2,}/', (string) $vacancy->requirement) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-10 rounded-xl border border-white/10 bg-neutral-900 p-6">
            @if ($application && $application->isActive())
                <p class="text-sm font-semibold text-emerald-400">{{ __('careers.applied') }}</p>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ __('careers.status_' . $application->status) }}
                </p>
                <a href="{{ $lroute('customer.applications') }}"
                   class="mt-4 inline-block text-sm text-amber-400 underline hover:text-amber-300">
                    {{ __('careers.mine_title') }}
                </a>

            @elseif (! $vacancy->is_open)
                <p class="text-sm text-neutral-500">{{ __('careers.closed') }}</p>

            @elseif (auth('customer')->check())
                <a href="{{ $lroute('careers.apply', ['vacancy' => $vacancy->slug]) }}"
                   class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950
                          transition hover:bg-amber-400">
                    {{ __('careers.apply') }}
                </a>

            @else
                <a href="{{ $lroute('customer.login') }}"
                   class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950
                          transition hover:bg-amber-400">
                    {{ __('careers.apply_login') }}
                </a>
            @endif
        </div>

        {{--
            D13: an optional per-vacancy HR link, and a QUESTION channel -- not
            a second way to apply.

            Deliberately subordinate to Apply above, and it renders ONLY when
            contact_url is set. There is no fallback to company_whatsapp: that
            is the sales number, and routing a candidate there is wrong for
            both sides. An equal-weight link here would also split the funnel,
            and CVs arriving over WhatsApp sit outside the pipeline, the D11
            retention window, and the P8 audit log.
        --}}
        @if ($vacancy->contact_url)
            <p class="mt-6 text-sm text-neutral-500">
                {{ __('careers.ask_question') }}
                <a href="{{ $vacancy->contact_url }}" target="_blank" rel="noopener noreferrer"
                   class="text-neutral-300 underline hover:text-amber-400">
                    {{ __('careers.ask_question_cta') }}
                </a>
            </p>
        @endif
    </section>
@endsection
