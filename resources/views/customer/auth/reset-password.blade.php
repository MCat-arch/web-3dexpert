@extends('layouts.app')

@section('title', __('customer.reset_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.reset_title')"
        :action="$lroute('customer.password.update')">

        <input type="hidden" name="token" value="{{ $token }}">

        <x-customer-field name="email" :label="__('customer.email')" type="email" :value="$email" required
                          autocomplete="email" />
        <x-customer-field name="password" :label="__('customer.password')" type="password" required
                          autocomplete="new-password" />
        <x-customer-field name="password_confirmation" :label="__('customer.password_confirmation')"
                          type="password" required autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                       transition hover:bg-amber-400">
            {{ __('customer.reset_submit') }}
        </button>
    </x-customer-auth-card>
@endsection
