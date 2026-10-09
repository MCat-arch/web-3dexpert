@extends('layouts.app')

@section('title', __('courses.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('courses.meta_description'))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('courses.title') }}</span>
            </nav>

            <h1 class="page-banner-title">{{ __('courses.title') }}</h1>
            <p class="page-banner-desc">{{ __('courses.meta_description') }}</p>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        @if ($courses->isEmpty())
            <x-empty-state :title="__('courses.empty_title')" :description="__('courses.empty_description')" />
        @else
            <div class="grid grid-cols-3">
                @foreach ($courses as $course)
                    <x-course-card :course="$course" />
                @endforeach
            </div>

            @if ($courses->hasPages())
                <div style="margin-top: 40px; display: flex; justify-content: center;">
                    {{ $courses->onEachSide(1)->links() }}
                </div>
            @endif
        @endif

        {{-- Enterprise / Custom Training CTA via WhatsApp (D12) --}}
        @if ($whatsapp = $settings('company_whatsapp'))
            <div class="card-dark" style="margin-top: 60px; padding: 36px; border-radius: var(--radius-feat);">
                <div style="display: flex; flex-direction: column; gap: 20px; align-items: flex-start; justify-content: space-between;"
                    class="flex-between">
                    <div style="max-width: 720px;">
                        <span class="badge badge-gold" style="margin-bottom: 12px;">In-House & Corporate Training</span>
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #ffffff; margin-bottom: 10px;">
                            Butuh Pelatihan Kustom untuk Tim atau Studio Anda?
                        </h2>
                        <p style="font-size: 0.95rem; color: var(--on-dark-2); line-height: 1.6; margin: 0;">
                            Kami menyediakan program pelatihan privat & korporasi dengan kurikulum fleksibel yang disesuaikan
                            dengan workflow proyek Anda.
                        </p>
                    </div>
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg">
                        <span>Hubungi via WhatsApp &rarr;</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection