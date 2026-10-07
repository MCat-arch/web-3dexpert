@extends('layouts.app')

@section('title', __('portfolio.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('portfolio.meta_description'))

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
        <nav aria-label="Breadcrumb" class="text-sm text-neutral-400">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ $lroute('home') }}" class="hover:text-amber-400">{{ __('nav.home') }}</a></li>
                <li aria-hidden="true">/</li>
                <li class="text-neutral-300" aria-current="page">{{ __('portfolio.title') }}</li>
            </ol>
        </nav>

        <div class="mt-6">
            <x-section-heading
                :eyebrow="__('portfolio.eyebrow')"
                :title="__('portfolio.title')"
                :subtitle="__('portfolio.meta_description')"
                align="left" />
        </div>

        @if ($portfolios->isEmpty())
            <div class="mt-10">
                <x-empty-state
                    :title="__('portfolio.empty_title')"
                    :description="__('portfolio.empty_description')" />
            </div>
        @else
            <p class="mt-8 text-sm text-neutral-400">
                {{ trans_choice('portfolio.result_count', $portfolios->total(), ['count' => number_format($portfolios->total(), 0, ',', '.')]) }}
            </p>

            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($portfolios as $portfolio)
                    <x-portfolio-card :portfolio="$portfolio" />
                @endforeach
            </div>

            @if ($portfolios->hasPages())
                <div class="mt-10">
                    {{ $portfolios->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
