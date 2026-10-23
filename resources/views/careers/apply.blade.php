@extends('layouts.app')

@section('title', __('careers.form_title', ['position' => $vacancy->title]) . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('careers.index') }}">{{ __('careers.title') }}</a>
                <span>/</span>
                <a
                    href="{{ $lroute('careers.show', ['vacancy' => $vacancy->slug]) }}">{{ Str::limit($vacancy->title, 30) }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('careers.apply') }}</span>
            </nav>

            <h1 class="page-banner-title">
                {{ __('careers.form_title', ['position' => $vacancy->title]) }}
            </h1>
        </div>
    </div>

    <div class="container" style="max-width: 680px; padding-bottom: 70px;">
        @if (session('error'))
            <div class="alert alert-error" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul style="list-style-position: inside; margin: 0; padding: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card-light" style="padding: 32px;">
            <form method="POST" action="{{ $lroute('careers.apply.store', ['vacancy' => $vacancy->slug]) }}"
                enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="cover_letter" class="form-label">
                        {{ __('careers.cover_letter') }}
                    </label>
                    <textarea id="cover_letter" name="cover_letter" rows="6"
                        class="form-textarea">{{ old('cover_letter') }}</textarea>
                    <p class="form-help">{{ __('careers.cover_letter_hint') }}</p>
                    @error('cover_letter')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="portfolio_url" class="form-label">
                        {{ __('careers.portfolio_url') }}
                    </label>
                    <input id="portfolio_url" name="portfolio_url" type="url" inputmode="url"
                        class="form-input" value="{{ old('portfolio_url') }}"
                        placeholder="https://">
                    <p class="form-help">{{ __('careers.portfolio_url_hint') }}</p>
                    @error('portfolio_url')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="documents" class="form-label">
                        {{ __('careers.documents') }}<span style="color: var(--gold-hover);" aria-hidden="true">*</span>
                    </label>
                    <input id="documents" name="documents[]" type="file" multiple required accept="application/pdf"
                        class="form-input" style="padding: 8px;">
                    <p class="form-help">
                        {{ __('careers.documents_hint', ['mb' => \App\Http\Requests\Careers\JobApplicationRequest::MAX_FILE_KB / 1024]) }}
                    </p>
                    @error('documents')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                    @error('documents.*')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 10px;">
                    {{ __('careers.submit') }}
                </button>
            </form>
        </div>
    </div>
@endsection