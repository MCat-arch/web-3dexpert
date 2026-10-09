@props(['course'])

<article class="card-light">
    {{-- Cover Thumbnail --}}
    <div class="card-thumb-wrapper">
        @if ($course->cover_url)
            <img src="{{ $course->cover_url }}"
                 alt="{{ $course->title }}"
                 loading="lazy"
                 class="card-thumb">
        @else
            <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center; background: var(--ink-900); color: var(--gold);">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.25 9.75v-4.5m0 4.5h4.5m-4.5 0l6-6m-3 18c-8.284 0-15-6.716-15-15V4.5A2.25 2.25 0 014.5 2.25h15A2.25 2.25 0 0121.75 4.5v15a2.25 2.25 0 01-2.25 2.25z"/>
                </svg>
            </div>
        @endif

        {{-- Overlaid Badges --}}
        <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap;">
            @if ($course->level)
                <span class="badge badge-dark">
                    {{ __('courses.level_'.$course->level) }}
                </span>
            @endif

            @if ($course->isFree())
                <span class="badge badge-success">
                    {{ __('courses.free') }}
                </span>
            @else
                <span class="badge badge-amber" style="background: var(--gold); color: #000;">
                    {{ $course->formattedPrice() }}
                </span>
            @endif
        </div>
    </div>

    {{-- Card Body --}}
    <div class="card-body">
        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;">
            @if ($course->estimated_minutes)
                <span>
                    ⏱ {{ $course->estimated_minutes >= 60 ? __('courses.duration_hours', ['count' => round($course->estimated_minutes / 60, 1)]) : __('courses.duration_minutes', ['count' => $course->estimated_minutes]) }}
                </span>
            @endif
            <span>
                📚 {{ __('courses.lessons_count', ['count' => $course->lessons->count()]) }}
            </span>
        </div>

        <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--text); margin-bottom: 8px; line-height: 1.35;">
            <a href="{{ $lroute('courses.show', ['course' => $course->slug]) }}" style="color: inherit; text-decoration: none;">
                {{ $course->title }}
            </a>
        </h3>

        @if ($course->excerpt)
            <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; flex: 1 1 auto;">
                {{ Str::limit($course->excerpt, 100) }}
            </p>
        @endif

        <div style="margin-top: auto; padding-top: 14px; border-top: 1px solid var(--line-light); display: flex; align-items: center; justify-content: space-between;">
            <div>
                @if ($course->instructor_name)
                    <span style="font-size: 0.825rem; color: var(--text-muted);">{{ $course->instructor_name }}</span>
                @endif
            </div>
            <a href="{{ $lroute('courses.show', ['course' => $course->slug]) }}" class="btn btn-primary btn-sm">
                {{ __('courses.view_detail') ?? 'Lihat Kursus' }}
            </a>
        </div>
    </div>
</article>
