@extends('layouts.app')

@section('title', __('careers.mine_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <div class="page-banner">
        <div class="container">
            <h1 class="page-banner-title">{{ __('careers.mine_title') }}</h1>
        </div>
    </div>

    <div class="container" style="max-width: 860px; padding-bottom: 70px;">
        @if (session('status'))
            <div class="alert alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($applications->isEmpty())
            <x-empty-state :title="__('careers.mine_empty')" />
        @else
            <div style="display: flex; flex-direction: column; gap: 16px;">
                @foreach ($applications as $application)
                    <div class="card-light" style="padding: 24px;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                            <div>
                                <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 6px;">
                                    <a href="{{ $lroute('careers.show', ['vacancy' => $application->vacancy->slug]) }}"
                                        style="color: inherit; text-decoration: none;">
                                        {{ $application->vacancy->title }}
                                    </a>
                                </h2>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 6px;">
                                    {{ $application->vacancy->department }} &middot; {{ $application->vacancy->location }}
                                </p>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                                    {{ __('careers.submitted_at') }}: {{ $application->submitted_at->translatedFormat('j F Y') }}
                                </p>
                            </div>

                            @php
                                $statusClass = match ($application->status) {
                                    'offered' => 'badge-success',
                                    'submitted', 'screening', 'interview' => 'badge-amber',
                                    default => 'badge-dark',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                {{ __('careers.status_' . $application->status) }}
                            </span>
                        </div>

                        @if ($customerCan('withdraw', $application))
                            <form method="POST" action="{{ $lroute('careers.withdraw', ['application' => $application->id]) }}"
                                style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--line-light);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">
                                    {{ __('careers.withdraw') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection