@extends('layouts.app')

@section('title', __('careers.form_title', ['position' => $vacancy->title]) . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-12 sm:py-16">
        <nav aria-label="Breadcrumb" class="text-sm text-neutral-500">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ $lroute('careers.index') }}" class="hover:text-amber-400">{{ __('careers.title') }}</a></li>
                <li aria-hidden="true">/</li>
                <li>
                    <a href="{{ $lroute('careers.show', ['vacancy' => $vacancy->slug]) }}" class="hover:text-amber-400">
                        {{ Str::limit($vacancy->title, 30) }}
                    </a>
                </li>
            </ol>
        </nav>

        <h1 class="mt-5 text-2xl font-bold tracking-tight text-white">
            {{ __('careers.form_title', ['position' => $vacancy->title]) }}
        </h1>

        @if (session('error'))
            <div role="alert" class="mt-6 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                {{ session('error') }}
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

        {{-- enctype is required for the upload to arrive at all. --}}
        <form method="POST"
              action="{{ $lroute('careers.apply.store', ['vacancy' => $vacancy->slug]) }}"
              enctype="multipart/form-data"
              class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="cover_letter" class="block text-sm font-medium text-neutral-300">
                    {{ __('careers.cover_letter') }}
                </label>
                <textarea id="cover_letter" name="cover_letter" rows="6"
                          class="mt-1.5 block w-full rounded-lg border border-white/15 bg-neutral-900 px-3 py-2.5
                                 text-sm text-white placeholder-neutral-500 transition
                                 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                >{{ old('cover_letter') }}</textarea>
                <p class="mt-1.5 text-xs text-neutral-500">{{ __('careers.cover_letter_hint') }}</p>
                @error('cover_letter')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="documents" class="block text-sm font-medium text-neutral-300">
                    {{ __('careers.documents') }}<span class="text-amber-400" aria-hidden="true">*</span>
                </label>
                <input id="documents" name="documents[]" type="file" multiple required
                       accept="application/pdf"
                       class="mt-1.5 block w-full rounded-lg border border-white/15 bg-neutral-900 px-3 py-2.5
                              text-sm text-neutral-300 file:mr-3 file:rounded file:border-0
                              file:bg-white/10 file:px-3 file:py-1.5 file:text-sm file:text-neutral-200">
                <p class="mt-1.5 text-xs text-neutral-500">
                    {{ __('careers.documents_hint', ['mb' => \App\Http\Requests\Careers\JobApplicationRequest::MAX_FILE_KB / 1024]) }}
                </p>
                {{-- accept= is a browser convenience only; the server checks
                     both extension and sniffed MIME type regardless. --}}
                @error('documents')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
                @error('documents.*')
                    <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                           transition hover:bg-amber-400">
                {{ __('careers.submit') }}
            </button>
        </form>
    </section>
@endsection
