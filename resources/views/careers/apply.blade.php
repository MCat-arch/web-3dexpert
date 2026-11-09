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
                    <label for="cv" class="form-label">
                        {{ __('careers.cv') }}<span style="color: var(--gold-hover);" aria-hidden="true">*</span>
                    </label>
                    <input id="cv" name="cv" type="file" required accept="application/pdf"
                        class="form-input" style="padding: 8px;">
                    <p class="form-help">
                        {{ __('careers.cv_hint', ['mb' => \App\Http\Requests\Careers\JobApplicationRequest::MAX_CV_KB / 1024]) }}
                    </p>
                    @error('cv')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="portfolio" class="form-label">
                        {{ __('careers.portfolio') }}<span style="color: var(--gold-hover);" aria-hidden="true">*</span>
                    </label>
                    <input id="portfolio" name="portfolio" type="file" required accept="application/pdf"
                        class="form-input" style="padding: 8px;">
                    <p class="form-help">
                        {{ __('careers.portfolio_hint', ['mb' => \App\Http\Requests\Careers\JobApplicationRequest::MAX_PORTFOLIO_KB / 1024]) }}
                    </p>
                    @error('portfolio')
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