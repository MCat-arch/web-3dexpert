@extends('layouts.app')

@section('title', __('customer.account_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:py-16">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">{{ __('customer.account_title') }}</h1>
                <p class="mt-2 text-sm text-neutral-400">
                    {{ __('customer.account_greeting', ['name' => $customer->name]) }}
                </p>
            </div>

            <form method="POST" action="{{ $lroute('customer.logout') }}">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-white/15 px-4 py-2 text-sm font-semibold text-neutral-200
                               transition hover:border-amber-500/50 hover:text-amber-400">
                    {{ __('customer.logout') }}
                </button>
            </form>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        {{-- Panels are added here by later phases: my events (P5), my
             applications (P6), my courses (P4). --}}
        <div class="mt-10 rounded-xl border border-dashed border-white/15 px-6 py-16 text-center">
            <p class="text-sm text-neutral-400">{{ __('customer.account_empty') }}</p>
        </div>
    </section>
@endsection
