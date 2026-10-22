@extends('layouts.app')

@section('title', __('courses.my_courses_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <div class="page-banner">
        <div class="container">
            <h1 class="page-banner-title">{{ __('courses.my_courses_title') }}</h1>
            <p style="color: var(--on-dark-muted); font-size: 0.95rem; margin-top: 6px;">{{ __('courses.my_courses_subtitle') }}</p>
        </div>
    </div>

    <div class="container" style="max-width: 860px; padding-top: 24px; padding-bottom: 70px;">
        @if ($enrollments->isEmpty())
            <div style="margin-top: 20px;">
                <x-empty-state
                    :title="__('courses.my_courses_empty')"
                    :description="__('courses.browse_catalog')" />
                <div style="margin-top: 24px; text-align: center;">
                    <a href="{{ $lroute('courses.index') }}" class="btn btn-primary">
                        {{ __('courses.browse_catalog') }}
                    </a>
                </div>
            </div>
        @else
            <div style="display: flex; flex-direction: column; gap: 16px;">
                @foreach ($enrollments as $enrollment)
                    <div class="card-light" style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                            <div style="display: flex; gap: 16px; align-items: center;">
                                <div style="width: 80px; height: 56px; border-radius: 6px; overflow: hidden; background: var(--ink-900); flex-shrink: 0;">
                                    @if ($enrollment->course && $enrollment->course->cover_url)
                                        <img src="{{ $enrollment->course->cover_url }}" alt="{{ $enrollment->course->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--gold);">
                                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                        @if ($enrollment->isActive())
                                            <span class="badge badge-success">
                                                {{ __('courses.active') }}
                                            </span>
                                        @else
                                            <span class="badge badge-amber">
                                                {{ __('courses.pending_payment') }}
                                            </span>
                                        @endif

                                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                                            Terdaftar {{ $enrollment->enrolled_at ? $enrollment->enrolled_at->translatedFormat('j M Y') : '' }}
                                        </span>
                                    </div>

                                    <h2 style="font-size: 1.05rem; font-weight: 600; margin: 0;">
                                        @if ($enrollment->course)
                                            <a href="{{ $lroute('courses.show', ['course' => $enrollment->course->slug]) }}" style="color: inherit; text-decoration: none;">
                                                {{ $enrollment->course->title }}
                                            </a>
                                        @endif
                                    </h2>
                                </div>
                            </div>

                            <div>
                                @if ($enrollment->isActive())
                                    <a href="{{ $lroute('courses.player.show', ['course' => $enrollment->course->slug]) }}"
                                       class="btn btn-primary btn-sm">
                                        {{ __('courses.resume_learning') }}
                                    </a>
                                @elseif ($enrollment->payment)
                                    <a href="{{ $lroute('courses.payment.show', ['order_id' => $enrollment->payment->order_id]) }}"
                                       class="btn btn-outline btn-sm" style="color: var(--gold-hover); border-color: var(--gold-hover);">
                                        Lihat Instruksi Bayar
                                    </a>
                                @endif
                            </div>
                        </div>

                        @php($courseProgress = $progress[$enrollment->getKey()] ?? null)
                        @if ($courseProgress && $enrollment->isActive())
                            <div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">
                                    <span>{{ __('courses.your_progress') }}</span>
                                    <span>{{ $courseProgress['completed'] }} / {{ $courseProgress['total'] }} &middot; {{ $courseProgress['percent'] }}%</span>
                                </div>
                                <div style="height: 7px; background: var(--line-light); border-radius: 999px; overflow: hidden;">
                                    <div style="height: 100%; width: {{ $courseProgress['percent'] }}%; background: var(--gold-hover);"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
