@extends('layouts.app')

@section('title', __('customer.verify_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.verify_title')"
        :subtitle="__('customer.verify_notice')"
        :action="$lroute('customer.verification.resend')">

        <button type="submit" class="btn btn-primary btn-block">
            {{ __('customer.verify_resend') }}
        </button>
    </x-customer-auth-card>

    {{-- Logout is a separate POST form: must not be nested inside the
         resend form, and must not be a GET link (guide.md 10.4). --}}
    <div class="auth-logout-wrap" style="margin-top: 16px; text-align: center;">
        <div style="display: flex; justify-content: center; gap: 20px; font-size: 0.875rem;">
            <a href="{{ $lroute('customer.login') }}" class="font-semibold text-amber-400 hover:text-amber-300">
                {{ __('customer.login_title') }}
            </a>
            <span style="color: var(--text-muted);">|</span>
            <a href="{{ $lroute('customer.register') }}" class="font-semibold text-amber-400 hover:text-amber-300">
                {{ __('customer.register_title') }}
            </a>
        </div>

        <form method="POST" action="{{ $lroute('customer.logout') }}" style="margin-top: 12px;">
            @csrf
            <button type="submit" class="auth-logout-btn" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.875rem;">
                {{ __('customer.logout') }}
            </button>
        </form>
    </div>
@endsection