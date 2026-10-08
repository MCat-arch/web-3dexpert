@extends('layouts.app')

@section('title', __('customer.register_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.register_title')"
        :subtitle="__('customer.register_subtitle')"
        :action="$lroute('customer.register')">

        <x-customer-field name="name" :label="__('customer.name')" required autocomplete="name" />
        <x-customer-field name="email" :label="__('customer.email')" type="email" required autocomplete="email" />
        <x-customer-field name="phone" :label="__('customer.phone')" type="tel" autocomplete="tel"
                          hint="08xxxxxxxxxx" />
        <x-customer-field name="password" :label="__('customer.password')" type="password" required
                          autocomplete="new-password" />
        <x-customer-field name="password_confirmation" :label="__('customer.password_confirmation')"
                          type="password" required autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                       transition hover:bg-amber-400">
            {{ __('customer.register_submit') }}
        </button>

        <x-slot:footer>
            {{ __('customer.register_have_account') }}
            <a href="{{ $lroute('customer.login') }}" class="font-semibold text-amber-400 hover:text-amber-300">
                {{ __('customer.register_login_link') }}
            </a>
        </x-slot:footer>
    </x-customer-auth-card>
@endsection
