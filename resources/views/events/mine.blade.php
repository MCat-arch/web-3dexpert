@extends('layouts.app')

@section('title', __('events.mine_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:py-16">
        <h1 class="text-2xl font-bold tracking-tight text-white">{{ __('events.mine_title') }}</h1>

        @if ($registrations->isEmpty())
            <div class="mt-8">
                <x-empty-state :title="__('events.mine_empty')" />
            </div>
        @else
            <ul class="mt-8 space-y-4">
                @foreach ($registrations as $registration)
                    <li class="rounded-xl border border-white/10 bg-neutral-900 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-white">
                                    <a href="{{ $lroute('events.show', ['event' => $registration->event->slug]) }}"
                                       class="hover:text-amber-400">
                                        {{ $registration->event->title }}
                                    </a>
                                </h2>
                                <p class="mt-1 text-sm text-neutral-400">
                                    {{ $registration->event->starts_at->translatedFormat('l, j F Y') }} &middot;
                                    {{ $registration->event->starts_at->format('H:i') }}
                                </p>
                                <p class="mt-2 text-xs text-neutral-500">
                                    {{ __('events.ticket_code') }}:
                                    <span class="font-mono text-neutral-300">{{ $registration->ticket_code }}</span>
                                </p>
                            </div>

                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-500/15 text-emerald-400' => $registration->status === 'confirmed' || $registration->status === 'attended',
                                'bg-amber-500/15 text-amber-400' => $registration->status === 'pending',
                                'bg-white/10 text-neutral-400' => $registration->status === 'cancelled',
                            ])>
                                {{ __('events.status_' . $registration->status) }}
                            </span>
                        </div>

                        @if ($customerCan('cancel', $registration))
                            <form method="POST"
                                  action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}"
                                  class="mt-4">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-neutral-400 underline hover:text-white">
                                    {{ __('events.cancel') }}
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
