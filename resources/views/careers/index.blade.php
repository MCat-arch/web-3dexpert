@extends('layouts.app')

@section('title', __('careers.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('careers.meta_description'))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('careers.title') }}</span>
            </nav>

            <h1 class="page-banner-title">{{ __('careers.title') }}</h1>
            <p class="page-banner-desc">{{ __('careers.meta_description') }}</p>
        </div>
    </div>

    <div class="container" style="max-width: 900px; padding-bottom: 70px;">
        @if ($vacancies->isEmpty())
            <x-empty-state :title="__('careers.empty_title')" :description="__('careers.empty_description')" />
        @else
            <div style="display: flex; flex-direction: column; gap: 16px;">
                @foreach ($vacancies as $vacancy)
                    <div class="card-light" style="padding: 24px;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                            <div>
                                <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px;">
                                    <a href="{{ $lroute('careers.show', ['vacancy' => $vacancy->slug]) }}"
                                        style="color: inherit; text-decoration: none;">
                                        {{ $vacancy->title }}
                                    </a>
                                </h3>

                                <div
                                    style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">
                                    <span>🏢 {{ $vacancy->department }}</span>
                                    <span>📍 {{ $vacancy->location }}</span>
                                    <span>💼 {{ $vacancy->employment_type }}</span>
                                </div>

                                @if ($vacancy->closes_at)
                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                                        {{ __('careers.closes_at') }}: {{ $vacancy->closes_at->translatedFormat('j F Y') }}
                                    </p>
                                @endif
                            </div>

                            <a href="{{ $lroute('careers.show', ['vacancy' => $vacancy->slug]) }}" class="btn btn-outline btn-sm"
                                style="color: var(--text); border-color: var(--line-light);">
                                {{ __('careers.view_detail') ?? 'Detail &rarr;' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($vacancies->hasPages())
                <div style="margin-top: 40px; display: flex; justify-content: center;">
                    {{ $vacancies->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection