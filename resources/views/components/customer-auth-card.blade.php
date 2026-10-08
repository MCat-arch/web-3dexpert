@props(['title', 'subtitle' => null, 'action', 'method' => 'POST'])

{{-- Shared frame for the five customer auth forms, so the markup and the
     error handling live in one place rather than five. --}}
<section class="mx-auto flex max-w-md flex-col px-4 py-12 sm:py-16">
    <h1 class="text-2xl font-bold tracking-tight text-white">{{ $title }}</h1>

    @if ($subtitle)
        <p class="mt-2 text-sm text-neutral-400">{{ $subtitle }}</p>
    @endif

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="{{ $method }}" action="{{ $action }}" class="mt-8 space-y-5">
        @csrf
        {{ $slot }}
    </form>

    @if (isset($footer))
        <p class="mt-6 text-center text-sm text-neutral-400">{{ $footer }}</p>
    @endif
</section>
