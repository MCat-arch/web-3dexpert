@extends('layouts.app')

@section('title', __('events.title') . ' | ' . $settings('company_name', config('app.name')))
@section('description', __('events.meta_description'))

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
        <x-section-heading
            :eyebrow="__('events.eyebrow')"
            :title="__('events.title')"
            :subtitle="__('events.meta_description')"
            align="left" />

        @if ($events->isEmpty())
            <div class="mt-10">
                <x-empty-state :title="__('events.empty_title')" :description="__('events.empty_description')" />
            </div>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($events as $event)
                    <x-event-card :event="$event" />
                @endforeach
            </div>

            @if ($events->hasPages())
                <div class="mt-10">{{ $events->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </section>
@endsection
