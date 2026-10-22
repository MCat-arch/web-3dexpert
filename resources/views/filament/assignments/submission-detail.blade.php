@php
    $submission->loadMissing(['assignment', 'customer', 'grader']);
@endphp

<div class="space-y-4 text-sm">
    <div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Siswa</p>
        <p class="text-gray-900 dark:text-gray-100">
            {{ $submission->customer?->name ?? 'Pelanggan terhapus' }}
            @if ($submission->customer?->email)
                <span class="text-gray-500">— {{ $submission->customer->email }}</span>
            @endif
        </p>
    </div>

    <div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Dikumpulkan</p>
        <p class="text-gray-900 dark:text-gray-100">{{ $submission->submitted_at?->format('d M Y H:i') ?? '-' }}</p>
    </div>

    <div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Teks jawaban</p>
        @if (filled($submission->submitted_text))
            <p class="whitespace-pre-line text-gray-900 dark:text-gray-100">{{ $submission->submitted_text }}</p>
        @else
            <p class="text-gray-500">Tidak ada teks jawaban.</p>
        @endif
    </div>

    <div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Berkas</p>
        @if ($submission->hasFile())
            <a href="{{ route('admin.assignment-submissions.download', $submission) }}"
               class="text-primary-600 hover:underline"
               target="_blank">
                {{ $submission->original_filename ?: 'Unduh berkas' }}
            </a>
        @else
            <p class="text-gray-500">Tidak ada berkas.</p>
        @endif
    </div>

    <div>
        <p class="font-semibold text-gray-500 dark:text-gray-400">Penilaian</p>
        @if ($submission->isGraded())
            <p class="text-gray-900 dark:text-gray-100">
                {{ $submission->score }} / {{ $submission->assignment?->max_score ?? 100 }}
                @if ($submission->grader)
                    <span class="text-gray-500">oleh {{ $submission->grader->name }}</span>
                @endif
            </p>
            @if (filled($submission->feedback))
                <p class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $submission->feedback }}</p>
            @endif
        @else
            <p class="text-amber-600">Belum dinilai.</p>
        @endif
    </div>
</div>
