@extends('layouts.app')

@section('title', __('careers.mine_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:py-16">
        <h1 class="text-2xl font-bold tracking-tight text-white">{{ __('careers.mine_title') }}</h1>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($applications->isEmpty())
            <div class="mt-8">
                <x-empty-state :title="__('careers.mine_empty')" />
            </div>
        @else
            <ul class="mt-8 space-y-4">
                @foreach ($applications as $application)
                    <li class="rounded-xl border border-white/10 bg-neutral-900 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-white">
                                    <a href="{{ $lroute('careers.show', ['vacancy' => $application->vacancy->slug]) }}"
                                       class="hover:text-amber-400">
                                        {{ $application->vacancy->title }}
                                    </a>
                                </h2>
                                <p class="mt-1 text-sm text-neutral-400">
                                    {{ $application->vacancy->department }} &middot;
                                    {{ $application->vacancy->location }}
                                </p>
                                <p class="mt-2 text-xs text-neutral-500">
                                    {{ __('careers.submitted_at') }}:
                                    {{ $application->submitted_at->translatedFormat('j F Y') }}
                                </p>
                            </div>

                            {{-- Status only. internal_notes is $hidden on the
                                 model and staff-only in the policy: a
                                 candidate must never see staff commentary
                                 about themselves. --}}
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-500/15 text-emerald-400' => $application->status === 'offered',
                                'bg-amber-500/15 text-amber-400' => in_array($application->status, ['submitted', 'screening', 'interview'], true),
                                'bg-white/10 text-neutral-400' => in_array($application->status, ['rejected', 'withdrawn'], true),
                            ])>
                                {{ __('careers.status_' . $application->status) }}
                            </span>
                        </div>

                        @if ($customerCan('withdraw', $application))
                            <form method="POST"
                                  action="{{ $lroute('careers.withdraw', ['application' => $application->id]) }}"
                                  class="mt-4">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-neutral-400 underline hover:text-white">
                                    {{ __('careers.withdraw') }}
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
