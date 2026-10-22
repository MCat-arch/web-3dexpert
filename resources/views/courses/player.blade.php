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
                        {{ __('courses.back_to_course') }}
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

            {{-- Progress bar (enrolled students only) --}}
            @if ($enrollment && $progress && $isEnrolled)
                <div class="card-dark" style="padding: 16px 20px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--on-dark-2);">
                            {{ __('courses.your_progress') }}
                        </span>
                        <span style="font-size: 0.85rem; color: var(--gold);">
                            {{ $progress['completed'] }} / {{ $progress['total'] }} &middot; {{ $progress['percent'] }}%
                        </span>
                    </div>
                    <div style="height: 8px; background: var(--ink-800); border-radius: 999px; overflow: hidden;">
                        <div style="height: 100%; width: {{ $progress['percent'] }}%; background: var(--gold);"></div>
                    </div>
                </div>
            @endif

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
                                <div style="font-size: 2.5rem; margin-bottom: 12px; color: var(--gold);">&#9679;</div>
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
                                <span class="badge badge-amber">{{ $lessonPayload['duration_formatted'] }}</span>
                            @endif
                        </div>

                        @if (!empty($lessonPayload['body']))
                            <div style="font-size: 0.95rem; line-height: 1.7; color: var(--on-dark-2);">
                                {!! nl2br(e($lessonPayload['body'])) !!}
                            </div>
                        @endif

                        @if (!empty($lessonPayload['attachment_url']))
                            <div style="margin-top: 20px;">
                                <a href="{{ $lessonPayload['attachment_url'] }}"
                                   class="btn btn-primary btn-sm"
                                   target="_blank"
                                   rel="noopener">
                                    {{ __('courses.download_attachment') }}
                                </a>
                            </div>
                        @endif

                        @if ($isEnrolled)
                            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line-dark); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                @if (in_array($currentLesson->id, $completedLessonIds, true))
                                    <span class="badge badge-success">{{ __('courses.completed') }}</span>
                                @else
                                    <form method="POST"
                                          action="{{ $lroute('courses.lesson.complete', ['course' => $course->slug, 'lesson' => $currentLesson->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ __('courses.mark_complete') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($isEnrolled && $assignments->isNotEmpty())
                        <div class="card-dark" style="padding: 28px; margin-top: 24px;" id="tugas">
                            <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff; margin-bottom: 18px;">
                                {{ __('courses.assignments') }}
                            </h2>

                            <div style="display: flex; flex-direction: column; gap: 18px;">
                                @foreach ($assignments as $assignment)
                                    @php
                                        $submission = $assignment->submissions->first();
                                    @endphp
                                    <div style="border: 1px solid var(--line-dark); border-radius: var(--radius-card); padding: 18px;">
                                        <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 8px;">
                                            <h3 style="font-size: 1rem; font-weight: 600; color: #ffffff; margin: 0;">
                                                {{ $assignment->getTranslation('title', app()->getLocale()) }}
                                            </h3>
                                            @if ($assignment->due_at)
                                                <span style="font-size: 0.8rem; color: {{ $assignment->due_at->isPast() ? 'var(--danger, #f87171)' : 'var(--on-dark-muted)' }};">
                                                    {{ __('courses.assignment_due') }}: {{ $assignment->due_at->format('d M Y H:i') }}
                                                </span>
                                            @endif
                                        </div>

                                        @if (filled($assignment->instructions))
                                            <div style="font-size: 0.9rem; line-height: 1.7; color: var(--on-dark-2); white-space: pre-line; margin-bottom: 14px;">
                                                {{ $assignment->getTranslation('instructions', app()->getLocale()) }}
                                            </div>
                                        @endif

                                        @if ($submission)
                                            <div style="font-size: 0.85rem; color: var(--on-dark-2); margin-bottom: 12px;">
                                                @if ($submission->isGraded())
                                                    <span class="badge badge-success">
                                                        {{ __('courses.assignment_graded') }}:
                                                        {{ $submission->score }} / {{ $assignment->max_score }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-amber">{{ __('courses.assignment_pending_grade') }}</span>
                                                @endif
                                                @if ($submission->hasFile())
                                                    <a href="{{ $lroute('courses.assignment.download', ['course' => $course->slug, 'assignment' => $assignment->id]) }}"
                                                       style="color: var(--gold-hover); margin-left: 10px;">
                                                        {{ __('courses.download_submission') }}
                                                    </a>
                                                @endif
                                            </div>

                                            @if (filled($submission->feedback))
                                                <div style="font-size: 0.85rem; color: var(--on-dark-2); margin-bottom: 12px;">
                                                    <strong>{{ __('courses.assignment_feedback_label') }}:</strong>
                                                    {{ $submission->feedback }}
                                                </div>
                                            @endif
                                        @endif

                                        @if ($submission?->isGraded())
                                            <p style="font-size: 0.85rem; color: var(--on-dark-muted); margin: 0;">
                                                {{ __('courses.assignment_locked_graded') }}
                                            </p>
                                        @elseif ($assignment->due_at?->isPast())
                                            <p style="font-size: 0.85rem; color: var(--on-dark-muted); margin: 0;">
                                                {{ __('courses.assignment_deadline_passed') }}
                                            </p>
                                        @else
                                            <form method="POST"
                                                  action="{{ $lroute('courses.assignment.submit', ['course' => $course->slug, 'assignment' => $assignment->id]) }}"
                                                  enctype="multipart/form-data"
                                                  style="display: flex; flex-direction: column; gap: 10px;">
                                                @csrf
                                                <textarea name="submitted_text"
                                                          rows="3"
                                                          maxlength="10000"
                                                          placeholder="{{ __('courses.assignment_text_placeholder') }}"
                                                          style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line-dark); background: var(--ink-900); color: #ffffff; resize: vertical;">{{ old('submitted_text', $submission?->submitted_text) }}</textarea>

                                                <input type="file"
                                                       name="file"
                                                       accept=".pdf,.zip,.jpg,.jpeg,.png"
                                                       style="font-size: 0.85rem; color: var(--on-dark-2);">

                                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        {{ $submission ? __('courses.resubmit_assignment') : __('courses.submit_assignment') }}
                                                    </button>
                                                    <span style="font-size: 0.75rem; color: var(--on-dark-muted);">
                                                        {{ __('courses.assignment_file_hint') }}
                                                    </span>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                                            @if (!empty($les['completed']))
                                                <span style="color: var(--gold); font-weight: 700;" aria-label="{{ __('courses.completed') }}">&#10003;</span>
                                            @endif
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