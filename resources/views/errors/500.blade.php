@extends('layouts.app')

@section('title', __('errors.500_title'))

@section('content')
    <div class="mx-auto max-w-xl px-4 py-24 text-center sm:px-6">
        <p class="text-6xl font-bold text-amber-500">500</p>
        <h1 class="mt-4 text-2xl font-bold text-white">{{ __('errors.500_title') }}</h1>
        {{-- Never echo the exception. Legacy koneksi.php did exactly that:
             die("Koneksi gagal: " . $e->getMessage()), leaking DB internals. --}}
        <p class="mt-3 text-sm text-neutral-400">{{ __('errors.500_description') }}</p>
        <a href="{{ route('home') }}"
           class="mt-8 inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950 transition hover:bg-amber-400">
            {{ __('nav.home') }}
        </a>
    </div>
@endsection
