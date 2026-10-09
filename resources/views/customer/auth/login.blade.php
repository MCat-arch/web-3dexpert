@extends('layouts.app')

@section('title', __('customer.login_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card :title="__('customer.login_title')" :subtitle="__('customer.login_subtitle')"
        :action="$lroute('customer.login')">

        <x-customer-field name="email" :label="__('customer.email')" type="email" required autocomplete="email" />
        <x-customer-field name="password" :label="__('customer.password')" type="password" required
            autocomplete="current-password" />

        <div class="flex items-center justify-between gap-4">
            <label for="remember" class="flex items-center gap-2 text-sm text-neutral-400">
                <input id="remember" name="remember" type="checkbox" value="1"
                    class="rounded border-white/20 bg-neutral-900 text-amber-500 focus:ring-amber-500">
                {{ __('customer.login_remember') }}
            </label>

            <a href="{{ $lroute('customer.password.request') }}" class="text-sm text-amber-400 hover:text-amber-300">
                {{ __('customer.login_forgot') }}
            </a>
        </div>

        <button type="submit" class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                           transition hover:bg-amber-400">
            {{ __('customer.login_submit') }}
        </button>

        <x-slot:footer>
            {{ __('customer.login_no_account') }}
            <a href="{{ $lroute('customer.register') }}" class="font-semibold text-amber-400 hover:text-amber-300">
                {{ __('customer.login_register_link') }}
            </a>
        </x-slot:footer>
    </x-customer-auth-card>
@endsection