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

    <div class="container tracker-page">
        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul class="tracker-form-errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-light tracker-form-card">
            <h2 class="tracker-form-title">{{ __('tracker.form_title') }}</h2>
            <p class="tracker-form-intro">{{ __('tracker.form_intro') }}</p>

            <form method="POST" action="{{ $lroute('tracker.find') }}">
                @csrf

                <div class="tracker-form-grid">
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
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    {{ __('tracker.submit') }}
                </button>
            </form>
        </div>

        @if ($searched && ! $project)
            <div class="card-light tracker-empty" role="status">
                <x-ui-icon name="x" :size="28" />
                <p>{{ __('tracker.not_found') }}</p>
            </div>
        @endif

        @if ($project)
            @php
                $progress = $project->progressPercent();

                $statusClass = match ($project->status) {
                    \App\Models\Project::STATUS_COMPLETED => 'badge-success',
                    \App\Models\Project::STATUS_ON_HOLD => 'badge-amber',
                    default => 'badge-gold',
                };
            @endphp

            <article class="card-light tracker-card">
                <header class="tracker-head">
                    <div class="tracker-head-main">
                        <span class="tracker-code">{{ $project->project_code }}</span>
                        <h2 class="tracker-title">{{ $project->title }}</h2>
                        <p class="tracker-head-meta">
                            <x-ui-icon name="users" :size="15" />
                            {{ $project->customer_name }}
                        </p>
                    </div>

                    <span class="badge {{ $statusClass }}">
                        {{ __('tracker.status_' . $project->status) }}
                    </span>
                </header>

                <div class="tracker-body">
                    {{-- Progress: the number first, because that is what the
                         customer opens this page to see. --}}
                    <section class="tracker-block" aria-labelledby="tracker-progress-heading">
                        <div class="tracker-block-head">
                            <h3 id="tracker-progress-heading">{{ __('tracker.overall_progress') }}</h3>
                            <span class="tracker-percent">{{ $progress }}%</span>
                        </div>

                        <div class="tracker-bar"
                             role="progressbar"
                             aria-valuenow="{{ $progress }}"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-label="{{ __('tracker.overall_progress') }}">
                            <span class="tracker-bar-fill" style="width: {{ $progress }}%"></span>
                        </div>

                        @if ($project->current_stage)
                            <p class="tracker-stage">
                                <span>{{ __('tracker.current_stage') }}</span>
                                <strong>{{ $project->current_stage }}</strong>
                            </p>
                        @endif
                    </section>

                    {{-- Dates, grouped together instead of scattered between
                         other facts. --}}
                    <section class="tracker-block" aria-labelledby="tracker-dates-heading">
                        <h3 id="tracker-dates-heading">{{ __('tracker.dates') }}</h3>

                        <dl class="tracker-facts">
                            <div class="tracker-fact">
                                <dt>{{ __('tracker.started_at') }}</dt>
                                <dd>{{ $project->started_at?->translatedFormat('j F Y') ?? '—' }}</dd>
                            </div>

                            <div class="tracker-fact">
                                <dt>{{ __('tracker.estimated_completion') }}</dt>
                                <dd>{{ $project->estimated_completion_at?->translatedFormat('j F Y') ?? '—' }}</dd>
                            </div>

                            <div class="tracker-fact">
                                <dt>{{ __('tracker.date_completed') }}</dt>
                                <dd>{{ $project->completed_at?->translatedFormat('j F Y') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="tracker-block" aria-labelledby="tracker-timeline-heading">
                        <h3 id="tracker-timeline-heading">{{ __('tracker.timeline') }}</h3>

                        @if ($project->updates->isEmpty())
                            <p class="tracker-muted">{{ __('tracker.no_updates') }}</p>
                        @else
                            <div class="tracker-timeline">
                                @foreach ($project->updates as $update)
                                    <div class="tracker-step {{ $update->status === 'done' ? 'is-done' : ($update->status === 'in_progress' ? 'is-current' : '') }}">
                                        <div class="tracker-dot">
                                            @if ($update->status === 'done')
                                                <span class="tracker-dot-check">✓</span>
                                            @endif
                                        </div>

                                        <div class="tracker-step-head">
                                            <strong class="tracker-step-name">{{ $update->stage_name }}</strong>
                                            <span class="tracker-step-date">{{ $update->occurred_at?->translatedFormat('j M Y') }}</span>
                                        </div>

                                        <span class="badge tracker-step-badge {{ $update->status === 'done' ? 'badge-success' : ($update->status === 'in_progress' ? 'badge-gold' : 'badge-dark') }}">
                                            {{ __('tracker.update_' . $update->status) }}
                                        </span>

                                        @if ($update->note)
                                            <p class="tracker-step-note">{{ $update->note }}</p>
                                        @endif

                                        @if ($update->attachments->isNotEmpty())
                                            <div class="tracker-files tracker-files-compact">
                                                @foreach ($update->attachments as $file)
                                                    @include('tracker.partials.file', ['file' => $file])
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($project->progressAttachments->isNotEmpty())
                        <section class="tracker-block" aria-labelledby="tracker-files-heading">
                            <h3 id="tracker-files-heading">{{ __('tracker.attachments') }}</h3>
                            <p class="tracker-muted">{{ __('tracker.attachments_note') }}</p>

                            <div class="tracker-files">
                                @foreach ($project->progressAttachments as $file)
                                    @include('tracker.partials.file', ['file' => $file])
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($project->isCompleted())
                        <section class="tracker-block tracker-final" aria-labelledby="tracker-final-heading">
                            <div class="tracker-final-head">
                                <span class="tracker-final-icon">
                                    <x-ui-icon name="check-circle" :size="22" />
                                </span>
                                <div>
                                    <h3 id="tracker-final-heading">{{ __('tracker.final_heading') }}</h3>
                                    <p class="tracker-muted">
                                        {{ $project->completed_at
                                            ? __('tracker.final_completed_on', ['date' => $project->completed_at->translatedFormat('j F Y')])
                                            : __('tracker.final_heading') }}
                                    </p>
                                </div>
                            </div>

                            <p class="tracker-final-note">
                                {{ $project->summary ?: __('tracker.final_default_note') }}
                            </p>

                            @if ($project->deliverables->isNotEmpty())
                                <div class="tracker-files">
                                    @foreach ($project->deliverables as $file)
                                        @include('tracker.partials.file', ['file' => $file])
                                    @endforeach
                                </div>
                            @else
                                <p class="tracker-muted">{{ __('tracker.final_no_files') }}</p>
                            @endif
                        </section>
                    @endif
                </div>
            </article>
        @endif

        @if ($wa = $settings('company_whatsapp'))
            <div class="tracker-help">
                {{ __('tracker.help') }}
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer">
                    {{ __('tracker.help_cta') }}
                </a>
            </div>
        @endif
    </div>
@endsection
