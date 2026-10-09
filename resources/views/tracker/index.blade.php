@extends('layouts.app')

@section('title', __('tracker.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('tracker.meta_description'))

@section('content')
    <div class="page-banner">
        <div class="container">
            <h1 class="page-banner-title">{{ __('tracker.title') }}</h1>
            <p class="page-banner-desc">{{ __('tracker.meta_description') }}</p>
        </div>
    </div>

    <div class="container" style="max-width: 720px; padding-bottom: 70px;">
        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul style="list-style-position: inside; margin: 0; padding: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-light" style="padding: 32px; margin-bottom: 30px;">
            <form method="POST" action="{{ $lroute('tracker.find') }}">
                @csrf

                <x-customer-field
                    name="project_code"
                    :label="__('tracker.code')"
                    :hint="__('tracker.code_hint')"
                    required />

                <x-customer-field
                    name="verification"
                    :label="__('tracker.verification')"
                    :hint="__('tracker.verification_hint')"
                    required />

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 10px;">
                    {{ __('tracker.submit') }}
                </button>
            </form>
        </div>

        @if ($searched && ! $project)
            <div class="card-light" style="padding: 24px; text-align: center; color: var(--text-muted);">
                {{ __('tracker.not_found') }}
            </div>
        @endif

        @if ($project)
            <div class="card-light" style="padding: 32px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--line-light); flex-wrap: wrap;">
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">{{ __('tracker.project_title') }}</span>
                        <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text); margin: 0;">{{ $project->title }}</h2>
                    </div>

                    <span class="badge {{ $project->status === 'in_progress' ? 'badge-success' : 'badge-amber' }}">
                        {{ __('tracker.status_' . $project->status) }}
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">
                    @if ($project->current_stage)
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('tracker.current_stage') }}</span>
                            <strong style="font-size: 0.95rem; color: var(--text);">{{ $project->current_stage }}</strong>
                        </div>
                    @endif

                    @if ($project->started_at)
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('tracker.started_at') }}</span>
                            <span style="font-size: 0.95rem; color: var(--text);">{{ $project->started_at->translatedFormat('j F Y') }}</span>
                        </div>
                    @endif

                    @if ($project->estimated_completion_at)
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('tracker.estimated_completion') }}</span>
                            <span style="font-size: 0.95rem; color: var(--text);">{{ $project->estimated_completion_at->translatedFormat('j F Y') }}</span>
                        </div>
                    @endif
                </div>

                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; padding-top: 16px; border-top: 1px solid var(--line-light);">{{ __('tracker.timeline') }}</h3>

                @if ($project->updates->isEmpty())
                    <p style="font-size: 0.9rem; color: var(--text-muted);">{{ __('tracker.no_updates') }}</p>
                @else
                    <div class="tracker-timeline">
                        @foreach ($project->updates as $update)
                            <div class="tracker-step {{ $update->status === 'done' ? 'is-done' : ($update->status === 'in_progress' ? 'is-current' : '') }}">
                                <div class="tracker-dot">
                                    @if ($update->status === 'done')
                                        <span style="font-size: 10px;">✓</span>
                                    @endif
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 10px;">
                                    <strong style="font-size: 0.95rem; color: var(--text);">{{ $update->stage_name }}</strong>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $update->occurred_at->translatedFormat('j F Y') }}</span>
                                </div>
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 2px 0;">
                                    {{ __('tracker.update_' . $update->status) }}
                                </p>
                                @if ($update->note)
                                    <p style="font-size: 0.9rem; color: var(--text); margin-top: 4px;">{{ $update->note }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($wa = $settings('company_whatsapp'))
            <div style="margin-top: 24px; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
                {{ __('tracker.help') }}
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" style="color: var(--gold-hover); font-weight: 600; margin-left: 4px;">
                    {{ __('tracker.help_cta') }}
                </a>
            </div>
        @endif
    </div>
@endsection
