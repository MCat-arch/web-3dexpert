@extends('layouts.app')

@section('title', __('careers.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('careers.meta_description'))

@section('content')
    <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 sm:py-16">
        <x-section-heading
            :eyebrow="__('careers.eyebrow')"
            :title="__('careers.title')"
            :subtitle="__('careers.meta_description')"
            align="left" />

        @if ($vacancies->isEmpty())
            <div class="mt-10">
                <x-empty-state :title="__('careers.empty_title')" :description="__('careers.empty_description')" />
            </div>
        @else
            <ul class="mt-10 space-y-4">
                @foreach ($vacancies as $vacancy)
                    <li class="rounded-xl border border-white/10 bg-neutral-900 p-5 transition hover:border-amber-500/40">
                        <h3 class="text-base font-semibold text-white">
                            <a href="{{ $lroute('careers.show', ['vacancy' => $vacancy->slug]) }}"
                               class="hover:text-amber-400">
                                {{ $vacancy->title }}
                            </a>
                        </h3>

                        <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-neutral-400">
                            <div class="flex gap-1.5">
                                <dt class="sr-only">{{ __('careers.department') }}</dt>
                                <dd>{{ $vacancy->department }}</dd>
                            </div>
                            <div class="flex gap-1.5">
                                <dt class="sr-only">{{ __('careers.location') }}</dt>
                                <dd>{{ $vacancy->location }}</dd>
                            </div>
                            <div class="flex gap-1.5">
                                <dt class="sr-only">{{ __('careers.employment_type') }}</dt>
                                <dd class="text-neutral-500">{{ $vacancy->employment_type }}</dd>
                            </div>
                        </dl>

                        @if ($vacancy->closes_at)
                            <p class="mt-2 text-xs text-neutral-500">
                                {{ __('careers.closes_at') }}:
                                {{ $vacancy->closes_at->translatedFormat('j F Y') }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($vacancies->hasPages())
                <div class="mt-10">{{ $vacancies->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </section>
@endsection
