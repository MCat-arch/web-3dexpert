@extends('layouts.app')

@section('title', __('errors.404_title'))

@section('content')
    <div class="mx-auto max-w-xl px-4 py-24 text-center sm:px-6">
        <p class="text-6xl font-bold text-amber-500">404</p>
        <h1 class="mt-4 text-2xl font-bold text-white">{{ __('errors.404_title') }}</h1>
        <p class="mt-3 text-sm text-neutral-400">{{ __('errors.404_description') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}"
               class="rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950 transition hover:bg-amber-400">
                {{ __('nav.home') }}
            </a>
            <a href="{{ route('home') }}#portofolio"
               class="rounded-lg border border-white/15 px-6 py-3 text-sm font-semibold text-neutral-200 transition hover:text-white">
                {{ __('nav.portfolio') }}
            </a>
        </div>
    </div>
@endsection
