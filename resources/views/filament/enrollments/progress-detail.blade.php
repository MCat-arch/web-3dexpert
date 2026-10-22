@php
    $course = $record->course;
    $sections = $course?->sections ?? collect();
@endphp

<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Kurikulum &amp; Progress</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Status penyelesaian materi dan tugas siswa pada kursus ini.
        </p>
    </div>

    <div class="space-y-6 px-6 py-5">
        @forelse ($sections as $section)
            <div>
                <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $section->getTranslation('title', 'id') }}
                </h4>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                    @forelse ($section->lessons as $lesson)
                        <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-2 text-sm last:border-b-0 dark:border-white/5">
                            <span class="text-gray-700 dark:text-gray-200">
                                {{ $lesson->getTranslation('title', 'id') }}
                            </span>
                            <span class="shrink-0 text-xs font-medium {{ $completedLessonIds->has($lesson->id) ? 'text-success-600 dark:text-success-400' : 'text-gray-400' }}">
                                {{ $completedLessonIds->has($lesson->id) ? 'Selesai' : 'Belum selesai' }}
                            </span>
                        </div>
                    @empty
                        <div class="px-4 py-3 text-sm text-gray-500">Belum ada materi.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Kursus ini belum memiliki bab atau materi.</p>
        @endforelse

        <div>
            <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Tugas</h4>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                @forelse ($assignments as $assignment)
                    @php($submission = $assignment->submissions->first())
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 text-sm last:border-b-0 dark:border-white/5">
                        <div>
                            <div class="font-medium text-gray-800 dark:text-gray-100">
                                {{ $assignment->getTranslation('title', 'id') }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $assignment->lesson?->getTranslation('title', 'id') }}
                                @if ($assignment->due_at)
                                    &middot; Batas {{ $assignment->due_at->format('d M Y H:i') }}
                                @endif
                            </div>
                            @if ($submission?->feedback)
                                <div class="mt-1 text-xs text-gray-500">
                                    Umpan balik: {{ $submission->feedback }}
                                </div>
                            @endif
                        </div>

                        <div class="text-right">
                            @if (! $submission)
                                <span class="text-xs text-gray-400">Belum dikumpulkan</span>
                            @elseif ($submission->isGraded())
                                <span class="text-xs font-semibold text-success-600 dark:text-success-400">
                                    Dinilai: {{ $submission->score }} / {{ $assignment->max_score }}
                                </span>
                            @else
                                <span class="text-xs font-medium text-warning-600 dark:text-warning-400">Menunggu penilaian</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-3 text-sm text-gray-500">Belum ada tugas yang diterbitkan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
