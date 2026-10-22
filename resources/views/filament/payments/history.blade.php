@if ($logs->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada riwayat audit untuk pembayaran ini.</p>
@else
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
        @foreach ($logs as $log)
            <div class="border-b border-gray-100 px-4 py-3 text-sm last:border-b-0 dark:border-white/5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $log->event }}</span>
                    <span class="text-xs text-gray-500">{{ $log->created_at?->format('d M Y H:i') }}</span>
                </div>
                @if ($log->description)
                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $log->description }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
