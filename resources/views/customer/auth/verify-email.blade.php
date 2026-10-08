@extends('layouts.app')

@section('title', __('customer.verify_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <x-customer-auth-card
        :title="__('customer.verify_title')"
        :subtitle="__('customer.verify_notice')"
        :action="$lroute('customer.verification.resend')">

        <button type="submit"
                class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-neutral-950
                       transition hover:bg-amber-400">
            {{ __('customer.verify_resend') }}
        </button>
    </x-customer-auth-card>

    {{-- Logout is a separate POST form: it must not be nested inside the
         resend form, and it must not be a GET link (guide.md 10.4). --}}
    <div class="mx-auto max-w-md px-4 pb-12 text-center">
        <form method="POST" action="{{ $lroute('customer.logout') }}">
            @csrf
            <button type="submit" class="text-sm text-neutral-400 hover:text-white">
                {{ __('customer.logout') }}
            </button>
        </form>
    </div>
@endsection
