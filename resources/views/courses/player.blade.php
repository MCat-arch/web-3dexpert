@extends('layouts.app')

@section('title', $lessonPayload['title'] . ' - ' . $course->title)

@section('content')
    <div style="background: var(--ink-950); color: #ffffff; padding: 24px 0 60px 0; min-height: 80vh;">
        <div class="container" style="max-width: 1400px;">
            {{-- Top nav bar --}}
            <div
                style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--line-dark); flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <a href="{{ $lroute('courses.show', ['course' => $course->slug]) }}" class="btn btn-dark btn-sm">
                        &larr; {{ __('courses.back_to_course') }}
                    </a>
                    <span style="font-size: 1rem; font-weight: 600; color: #ffffff;">
                        {{ $course->title }}
                    </span>
                </div>

                @if ($customer)
                    <span class="badge badge-dark" style="font-size: 0.8rem;">
                        {{ __('courses.student_label') }}: {{ $customer->name }}
                    </span>
                @endif
            </div>

            {{-- 2 Columns: Video area + Syllabus sidebar --}}
            <div class="course-player-container">
                <div>
                    {{-- Video Player Container --}}
                    <div
                        style="position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000000; border-radius: var(--radius-card); overflow: hidden; border: 1px solid var(--line-dark); margin-bottom: 24px;">
                        @if (!empty($lessonPayload['youtube_id']))
                            <x-video-embed :youtube-id="$lessonPayload['youtube_id']" :title="$lessonPayload['title']" />

                            {{-- Student Watermark Deterrent --}}
                            @if ($customer)
                                <div
                                    style="position: absolute; bottom: 16px; right: 16px; z-index: 10; pointer-events: none; user-select: none; font-family: monospace; font-size: 10px; color: rgba(255,255,255,0.3); background: rgba(0,0,0,0.6); padding: 2px 6px; border-radius: 4px;">
                                    {{ $customer->email }}
                                </div>
                            @endif
                        @else
                            <div
                                style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; padding: 40px; text-align: center; color: var(--on-dark-2);">
                                <div style="font-size: 2.5rem; margin-bottom: 12px;">📄</div>
                                <h3 style="font-size: 1.25rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">{{ __('courses.lesson_reading_material') }}</h3>
                                <p style="font-size: 0.9rem; max-width: 480px; margin: 0;">{{ __('courses.reading_material_desc') }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Lesson Description --}}
                    <div class="card-dark" style="padding: 28px;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
                            <h1 style="font-size: 1.5rem; font-weight: 700; color: #ffffff; margin: 0;">
                                {{ $lessonPayload['title'] }}</h1>
                            @if ($lessonPayload['duration_formatted'])
                                <span class="badge badge-amber">⏱ {{ $lessonPayload['duration_formatted'] }}</span>
                            @endif
                        </div>

                        @if (!empty($lessonPayload['content']))
                            <div style="font-size: 0.95rem; line-height: 1.7; color: var(--on-dark-2);">
                                {!! nl2br(e($lessonPayload['content'])) !!}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Syllabus Sidebar --}}
                <div class="syllabus-sidebar">
                    <div
                        style="padding: 16px 20px; background: var(--ink-800); border-bottom: 1px solid var(--line-dark); font-weight: 700; font-size: 0.9rem; color: var(--gold); text-transform: uppercase; letter-spacing: 0.08em;">
                        {{ __('courses.syllabus_list') }}
                    </div>

                    <div style="max-height: 600px; overflow-y: auto;">
                        @foreach ($sectionsPayload as $secIndex => $sec)
                            <div class="syllabus-section-header">
                                {{ $secIndex + 1 }}. {{ $sec['title'] }}
                            </div>

                            @foreach ($sec['lessons'] as $les)
                                @php
                                    $isCurrent = $les['id'] === $currentLesson->id;
                                @endphp
                                @if ($les['unlocked'])
                                    <a href="{{ $lroute('courses.player.show', ['course' => $course->slug, 'lesson' => $les['id']]) }}"
                                        class="syllabus-lesson-item {{ $isCurrent ? 'is-current' : '' }}">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span>{{ $isCurrent ? '▶' : '✓' }}</span>
                                            <span
                                                style="color: {{ $isCurrent ? '#000000' : 'inherit' }}; font-weight: {{ $isCurrent ? '700' : '400' }};">
                                                {{ $les['title'] }}
                                            </span>
                                        </div>
                                        @if ($les['duration_formatted'])
                                            <span
                                                style="font-size: 0.75rem; color: var(--text-muted);">{{ $les['duration_formatted'] }}</span>
                                        @endif
                                    </a>
                                @else
                                    <div class="syllabus-lesson-item"
                                        style="color: var(--text-muted); cursor: not-allowed; opacity: 0.6;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span>🔒</span>
                                            <span>{{ $les['title'] }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection