@extends('layouts.app')

@section('title', $vacancy->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $vacancy->description), 155))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <a href="{{ $lroute('careers.index') }}">{{ __('careers.title') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ Str::limit($vacancy->title, 40) }}</span>
            </nav>

            <h1 class="page-banner-title">{{ $vacancy->title }}</h1>
        </div>
    </div>

    <div class="container" style="max-width: 860px; padding-bottom: 70px;">
        <div class="card-light" style="padding: 28px; margin-bottom: 30px;">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--line-light);">
                <div>
                    <span
                        style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">
                        {{ __('careers.department') }}
                    </span>
                    <p style="font-size: 1rem; font-weight: 500; margin: 0;">{{ $vacancy->department }}</p>
                </div>
                <div>
                    <span
                        style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">
                        {{ __('careers.location') }}
                    </span>
                    <p style="font-size: 1rem; font-weight: 500; margin: 0;">{{ $vacancy->location }}</p>
                </div>
                <div>
                    <span
                        style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">
                        {{ __('careers.employment_type') }}
                    </span>
                    <p style="font-size: 1rem; font-weight: 500; margin: 0;">{{ $vacancy->employment_type }}</p>
                </div>
            </div>

            @if ($vacancy->description)
                <div style="margin-top: 24px;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 12px;">{{ __('careers.description') }}</h2>
                    <div
                        style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; line-height: 1.7; color: var(--text);">
                        @foreach (preg_split('/\R{2,}/', (string) $vacancy->description) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($vacancy->requirement)
                <div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--line-light);">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 12px;">{{ __('careers.requirement') }}</h2>
                    <div
                        style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; line-height: 1.7; color: var(--text);">
                        @foreach (preg_split('/\R{2,}/', (string) $vacancy->requirement) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Application Action Card --}}
        <div class="card-light" style="padding: 24px; background: #fafafa;">
            @if ($application && $application->isActive())
                <p style="font-size: 1.05rem; font-weight: 600; color: var(--success); margin-bottom: 6px;">
                    {{ __('careers.applied') }}
                </p>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px;">
                    {{ __('careers.status_' . $application->status) }}
                </p>
                <a href="{{ $lroute('customer.applications') }}" class="btn btn-secondary btn-sm">
                    {{ __('careers.mine_title') }}
                </a>

            @elseif (!$vacancy->is_open)
                <p style="color: var(--text-muted); margin: 0;">{{ __('careers.closed') }}</p>

            @elseif (auth('customer')->check())
                <a href="{{ $lroute('careers.apply', ['vacancy' => $vacancy->slug]) }}" class="btn btn-primary btn-lg">
                    {{ __('careers.apply') }}
                </a>

            @else
                <a href="{{ $lroute('customer.login') }}" class="btn btn-primary btn-lg">
                    {{ __('careers.apply_login') }}
                </a>
            @endif
        </div>

        @if ($vacancy->contact_url)
            <div style="margin-top: 24px; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
                {{ __('careers.ask_question') }}
                <a href="{{ $vacancy->contact_url }}" target="_blank" rel="noopener noreferrer"
                    style="color: var(--gold-hover); font-weight: 600; margin-left: 4px;">
                    {{ __('careers.ask_question_cta') }}
                </a>
            </div>
        @endif
    </div>
@endsection