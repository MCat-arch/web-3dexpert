@extends('layouts.app')

@section('title', __('customer.login_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card :title="__('customer.login_title')" :subtitle="__('customer.login_subtitle')"
        :action="$lroute('customer.login')">

        <x-customer-field name="email" :label="__('customer.email')" type="email" required autocomplete="email" />
        <x-customer-field name="password" :label="__('customer.password')" type="password" required
            autocomplete="current-password" />

        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px;">
            <label for="remember" style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--text-muted); cursor: pointer;">
                <input id="remember" name="remember" type="checkbox" value="1"
                    style="width: 16px; height: 16px; accent-color: var(--gold); cursor: pointer;">
                {{ __('customer.login_remember') }}
            </label>

            <a href="{{ $lroute('customer.password.request') }}"
               style="font-size: 0.875rem; color: var(--gold-hover); font-weight: 500; text-decoration: none; white-space: nowrap;">
                {{ __('customer.login_forgot') }}
            </a>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            {{ __('customer.login_submit') }}
        </button>

        <x-slot:footer>
            {{ __('customer.login_no_account') }}
            <a href="{{ $lroute('customer.register') }}" style="font-weight: 600; color: var(--gold-hover);">
                {{ __('customer.login_register_link') }}
            </a>
        </x-slot:footer>
    </x-customer-auth-card>
@endsection