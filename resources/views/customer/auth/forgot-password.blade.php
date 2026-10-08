@extends('layouts.app')

@section('title', __('customer.forgot_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.forgot_title')"
        :subtitle="__('customer.forgot_subtitle')"
        :action="$lroute('customer.password.email')">

        <x-customer-field name="email" :label="__('customer.email')" type="email" required autocomplete="email" />

        <button type="submit"
                class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                       transition hover:bg-amber-400">
            {{ __('customer.forgot_submit') }}
        </button>

        <x-slot:footer>
            <a href="{{ $lroute('customer.login') }}" class="font-semibold text-amber-400 hover:text-amber-300">
                {{ __('customer.login_title') }}
            </a>
        </x-slot:footer>
    </x-customer-auth-card>
@endsection
