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

        {{-- One card per customer-facing area. P4 adds "my courses" here. --}}
        <div class="mt-10 grid gap-4 sm:grid-cols-2">
            <a href="{{ $lroute('customer.events') }}"
               class="group rounded-xl border border-white/10 bg-neutral-900 p-5 transition
                      hover:border-amber-500/40">
                <h2 class="text-base font-semibold text-white group-hover:text-amber-400">
                    {{ __('events.mine_title') }}
                </h2>
                <p class="mt-1.5 text-sm text-neutral-400">{{ __('events.title') }}</p>
            </a>

            <a href="{{ $lroute('customer.applications') }}"
               class="group rounded-xl border border-white/10 bg-neutral-900 p-5 transition
                      hover:border-amber-500/40">
                <h2 class="text-base font-semibold text-white group-hover:text-amber-400">
                    {{ __('careers.mine_title') }}
                </h2>
                <p class="mt-1.5 text-sm text-neutral-400">{{ __('careers.title') }}</p>
            </a>
        </div>
    </section>
@endsection
