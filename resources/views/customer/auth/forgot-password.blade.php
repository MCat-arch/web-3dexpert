@extends('layouts.app')

@section('title', __('customer.forgot_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.forgot_title')"
        :subtitle="__('customer.forgot_subtitle')"
        :action="$lroute('customer.password.email')">

        <x-customer-field name="email" :label="__('customer.email')" type="email" required autocomplete="email" />

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            {{ __('customer.forgot_submit') }}
        </button>

        <x-slot:footer>
            <a href="{{ $lroute('customer.login') }}" style="font-weight: 600; color: var(--gold-hover);">
                &larr; {{ __('customer.login_title') }}
            </a>
        </x-slot:footer>
    </x-customer-auth-card>
@endsection
