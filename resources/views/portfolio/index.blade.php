@extends('layouts.app')

@section('title', __('portfolio.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('portfolio.meta_description'))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('portfolio.title') }}</span>
            </nav>

            <h1 class="page-banner-title">{{ __('portfolio.title') }}</h1>
            <p class="page-banner-desc">{{ __('portfolio.meta_description') }}</p>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        @if ($portfolios->isEmpty())
            <x-empty-state :title="__('portfolio.empty_title')" :description="__('portfolio.empty_description')" />
        @else
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 24px;">
                {{ trans_choice('portfolio.result_count', $portfolios->total(), ['count' => number_format($portfolios->total(), 0, ',', '.')]) }}
            </p>

            <div class="grid grid-cols-3">
                @foreach ($portfolios as $portfolio)
                    <x-portfolio-card :portfolio="$portfolio" light />
                @endforeach
            </div>

            @if ($portfolios->hasPages())
                <div style="margin-top: 40px; display: flex; justify-content: center;">
                    {{ $portfolios->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection