@extends('layouts.app')

@section('title', __('customer.account_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <div class="page-banner">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1 class="page-banner-title" style="margin-bottom: 6px;">{{ __('customer.account_title') }}</h1>
                <p style="color: var(--on-dark-muted); font-size: 0.95rem; margin: 0;">
                    {{ __('customer.account_greeting', ['name' => $customer->name]) }}
                </p>
            </div>

            <form method="POST" action="{{ $lroute('customer.logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm" style="color: #ffffff; border-color: rgba(255,255,255,0.25);">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="margin-right: 6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>{{ __('customer.logout') }}</span>
                </button>
            </form>
        </div>
    </div>

    <div class="container" style="max-width: 960px; padding-top: 36px; padding-bottom: 70px;">
        @if (session('status'))
            <div class="alert alert-success" role="status" style="margin-bottom: 24px;">
                {{ session('status') }}
            </div>
        @endif

        {{-- Profil Singkat --}}
        <div class="card-light" style="padding: 24px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: var(--ink-900); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 700;">
                    {{ strtoupper(substr($customer->name, 0, 1)) }}
                </div>
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0 0 4px 0; color: var(--text);">{{ $customer->name }}</h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">{{ $customer->email }}</p>
                </div>
            </div>

            <div>
                @if ($customer->hasVerifiedEmail())
                    <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Terverifikasi
                    </span>
                @else
                    <a href="{{ $lroute('customer.verification.notice') }}" class="badge badge-amber" style="text-decoration: none;">
                        Belum Terverifikasi &rarr;
                    </a>
                @endif
            </div>
        </div>

        {{-- Grid Navigasi Utama Profil --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            {{-- 1. Kursus --}}
            <a href="{{ $lroute('customer.courses') }}" class="card-light" style="padding: 24px; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; transition: transform var(--transition), box-shadow var(--transition); border-top: 3px solid var(--gold);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <span style="width: 40px; height: 40px; border-radius: 8px; background: rgba(254, 202, 7, 0.15); color: var(--gold-hover); display: flex; align-items: center; justify-content: center;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                            </svg>
                        </span>
                        <span style="font-size: 0.85rem; color: var(--gold-hover); font-weight: 600;">Akses &rarr;</span>
                    </div>
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        {{ __('courses.my_courses_title') ?? 'Kursus Saya' }}
                    </h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                        Lihat materi dan video pembelajaran kursus yang Anda ikuti.
                    </p>
                </div>
            </a>

            {{-- 2. Aktivitas & Acara --}}
            <a href="{{ $lroute('customer.events') }}" class="card-light" style="padding: 24px; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; transition: transform var(--transition), box-shadow var(--transition); border-top: 3px solid #3b82f6;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <span style="width: 40px; height: 40px; border-radius: 8px; background: rgba(59, 130, 246, 0.15); color: #2563eb; display: flex; align-items: center; justify-content: center;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <span style="font-size: 0.85rem; color: #2563eb; font-weight: 600;">Akses &rarr;</span>
                    </div>
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        {{ __('events.mine_title') ?? 'Aktivitas & Acara' }}
                    </h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                        Cek tiket, jadwal webinar, dan pendaftaran event Anda.
                    </p>
                </div>
            </a>

            {{-- 3. Lamaran Kerja --}}
            <a href="{{ $lroute('customer.applications') }}" class="card-light" style="padding: 24px; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; transition: transform var(--transition), box-shadow var(--transition); border-top: 3px solid #10b981;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <span style="width: 40px; height: 40px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #059669; display: flex; align-items: center; justify-content: center;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </span>
                        <span style="font-size: 0.85rem; color: #059669; font-weight: 600;">Akses &rarr;</span>
                    </div>
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text); margin-bottom: 6px;">
                        {{ __('careers.mine_title') ?? 'Status Lamaran' }}
                    </h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                        Pantau status berkas dan tahapan seleksi karir yang Anda lamar.
                    </p>
                </div>
            </a>
        </div>
    </div>
@endsection