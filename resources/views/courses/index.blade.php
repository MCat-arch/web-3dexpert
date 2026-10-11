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
        {{-- Filter Level Kursus --}}
        <div style="display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; justify-content: center;">
            <a href="{{ $lroute('courses.index') }}"
               class="btn btn-sm {{ empty($currentLevel) ? 'btn-primary' : 'btn-outline' }}"
               style="{{ empty($currentLevel) ? '' : 'color: var(--text); border-color: var(--line-light);' }}">
                Semua Kursus
            </a>
            <a href="{{ $lroute('courses.index', ['level' => 'beginner']) }}"
               class="btn btn-sm {{ $currentLevel === 'beginner' ? 'btn-primary' : 'btn-outline' }}"
               style="{{ $currentLevel === 'beginner' ? '' : 'color: var(--text); border-color: var(--line-light);' }}">
                Pemula (Beginner)
            </a>
            <a href="{{ $lroute('courses.index', ['level' => 'intermediate']) }}"
               class="btn btn-sm {{ $currentLevel === 'intermediate' ? 'btn-primary' : 'btn-outline' }}"
               style="{{ $currentLevel === 'intermediate' ? '' : 'color: var(--text); border-color: var(--line-light);' }}">
                Menengah (Intermediate)
            </a>
            <a href="{{ $lroute('courses.index', ['level' => 'advanced']) }}"
               class="btn btn-sm {{ $currentLevel === 'advanced' ? 'btn-primary' : 'btn-outline' }}"
               style="{{ $currentLevel === 'advanced' ? '' : 'color: var(--text); border-color: var(--line-light);' }}">
                Ahli (Advance)
            </a>
        </div>

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
                        <span>Hubungi via WhatsApp</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection