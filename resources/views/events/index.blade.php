@extends('layouts.app')

@section('title', __('events.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('events.meta_description'))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('events.title') }}</span>
            </nav>

            <h1 class="page-banner-title">{{ __('events.title') }}</h1>
            <p class="page-banner-desc">{{ __('events.meta_description') }}</p>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        @if ($events->isEmpty())
            <x-empty-state :title="__('events.empty_title')" :description="__('events.empty_description')" />
        @else
            <div class="grid grid-cols-3">
                @foreach ($events as $event)
                    <x-event-card :event="$event" />
                @endforeach
            </div>

            @if ($events->hasPages())
                <div style="margin-top: 40px; display: flex; justify-content: center;">
                    {{ $events->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection