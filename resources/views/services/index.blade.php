@extends('layouts.app')

@section('title', __('services.title') . ' & Harga | ' . $settings('company_name', config('app.name')))
@section('description', __('services.meta_description'))

@section('content')
    {{-- ========== PAGE BANNER ========== --}}
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('services.title') }} & Harga</span>
            </nav>

            <h1 class="page-banner-title">{{ __('services.title') }} & Harga</h1>
            <p class="page-banner-desc">{{ __('services.meta_description') }}</p>
        </div>
    </div>

    {{-- ========== SERVICES CATALOG ========== --}}
    <div class="container" style="padding-bottom: 60px;">
        @if ($services->isEmpty())
            <x-empty-state :title="__('services.empty_title')" description="Silakan hubungi kami untuk penawaran kustom." />
        @else
            <div class="grid grid-cols-3" style="gap: 28px;">
                @foreach ($services as $service)
                    @php
                        $waBase = $settings('company_whatsapp', 'https://wa.me/6282338389488');
                        $rawMsg = __('services.enquiry_message');
                        $msg = str_replace(':title', $service->title, $rawMsg ?: "Halo 3D Expert! Saya tertarik untuk konsultasi paket {$service->title}");
                        $waUrl = $waBase . (str_contains($waBase, '?') ? '&' : '?') . 'text=' . urlencode($msg);
                    @endphp

                    <article class="card-dark" style="padding: 32px 26px; display: flex; flex-direction: column; justify-content: space-between; border-top: 3px solid var(--gold); border-radius: var(--radius-card); transition: transform var(--transition), box-shadow var(--transition);"
                             onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div>
                            {{-- Header Card --}}
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
                                <h3 style="font-size: 1.35rem; font-weight: 700; color: #ffffff; line-height: 1.3; margin: 0;">
                                    {{ $service->title }}
                                </h3>
                                @if ($percent = $service->discount_percent)
                                    <span class="badge badge-amber" style="font-size: 0.75rem; white-space: nowrap;">
                                        Hemat {{ $percent }}%
                                    </span>
                                @elseif ($service->discount_text)
                                    <span class="badge badge-amber" style="font-size: 0.75rem; white-space: nowrap;">
                                        {{ $service->discount_text }}
                                    </span>
                                @endif
                            </div>

                            @if ($service->description)
                                <p style="font-size: 0.88rem; color: var(--on-dark-2); line-height: 1.6; margin-bottom: 20px;">
                                    {{ $service->description }}
                                </p>
                            @endif

                            {{-- Price Block --}}
                            <div style="margin-bottom: 24px; padding: 16px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--line-dark); border-radius: 8px;">
                                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--on-dark-muted); letter-spacing: 0.5px; display: block; margin-bottom: 4px;">
                                    Biaya Investasi
                                </span>

                                @if ($service->original_price)
                                    <p style="font-size: 0.875rem; color: var(--on-dark-muted); text-decoration: line-through; margin: 0 0 2px;">
                                        {{ __('services.currency') }} {{ number_format((float) $service->original_price, 0, ',', '.') }}
                                    </p>
                                @endif

                                @if ($service->price)
                                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--gold); line-height: 1.1;">
                                        {{ __('services.currency') }} {{ number_format((float) $service->price, 0, ',', '.') }}
                                    </div>
                                @else
                                    <div style="font-size: 1.35rem; font-weight: 700; color: var(--gold);">
                                        Harga Kustom / Hubungi Kami
                                    </div>
                                @endif
                            </div>

                            {{-- Features Checklist --}}
                            @if ($service->features->isNotEmpty())
                                <div style="margin-bottom: 28px;">
                                    <span style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--gold); display: block; margin-bottom: 12px; letter-spacing: 0.5px;">
                                        {{ __('services.whats_included') }}:
                                    </span>
                                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; font-size: 0.9rem; padding: 0; margin: 0;">
                                        @foreach ($service->features as $feature)
                                            @if ($feature->is_heading)
                                                <li style="font-weight: 700; color: #ffffff; padding-top: 8px; border-bottom: 1px solid var(--line-dark); padding-bottom: 4px;">
                                                    {{ $feature->label }}
                                                </li>
                                            @else
                                                <li style="display: flex; align-items: flex-start; gap: 10px; color: var(--on-dark-2); line-height: 1.45;">
                                                    <span style="color: var(--gold); font-weight: 700; flex-shrink: 0;">✓</span>
                                                    <span>{{ $feature->label }}</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                        {{-- Action Buttons --}}
                        <div style="display: flex; flex-direction: column; gap: 10px; padding-top: 18px; border-top: 1px solid var(--line-dark); margin-top: auto;">
                            <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block">
                                 {{ __('services.enquire_package') ?? 'Konsultasi Paket Ini' }}
                            </a>

                            @if ($service->brochure_path)
                                <a href="{{ route('media.show', ['path' => $service->brochure_path]) }}" target="_blank"
                                   class="btn btn-outline btn-sm btn-block" style="color: var(--on-dark-2); border-color: var(--line-dark);">
                                     {{ __('services.download_brochure') ?? 'Unduh Brosur Spesifikasi' }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ========== 🌟 SECTION FITUR TRACK PROGRESS ========== --}}
    <section style="background: var(--ink-800); border-top: 1px solid var(--line-dark); border-bottom: 1px solid var(--line-dark); padding: 70px 0;">
        <div class="container">
            <div style="max-width: 820px; margin: 0 auto; text-align: center; margin-bottom: 48px;">
                <h2 style="font-size: 2rem; font-weight: 800; color: #ffffff; margin-bottom: 14px; line-height: 1.3;">
                    Pantau Setiap Detik Pengerjaan dengan Fitur <span style="color: var(--gold);">Track Progress</span>
                </h2>
                <p style="font-size: 1rem; color: var(--on-dark-2); line-height: 1.7; margin: 0;">
                    Kami memahami waktu dan investasi Anda sangat berharga. Di 3D Expert, Anda tidak perlu cemas atau bertanya berulang kali via pesan singkat. Setiap proyek yang masuk dilengkapi sistem pelacakan otomatis yang dapat Anda pantau secara real-time kapan pun dan di mana pun.
                </p>
            </div>

            {{-- 4-Step Milestone Grid --}}
            <div class="grid grid-cols-4" style="gap: 20px; margin-bottom: 40px;">
                <div class="card-dark" style="padding: 24px 20px; border-left: 3px solid var(--gold); border-radius: var(--radius-card);">
                    
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--gold); font-weight: 700; display: block; margin-bottom: 4px;">Tahap 1</span>
                    <h4 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 8px;">Briefing & Dokumen</h4>
                    <p style="font-size: 0.84rem; color: var(--on-dark-2); line-height: 1.5; margin: 0;">
                        Pengumpulan sketsa, ukuran lahan, denah, dan referensi konsep dari klien.
                    </p>
                </div>

                <div class="card-dark" style="padding: 24px 20px; border-left: 3px solid var(--gold); border-radius: var(--radius-card);">

                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--gold); font-weight: 700; display: block; margin-bottom: 4px;">Tahap 2</span>
                    <h4 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 8px;">3D Modelling Presisi</h4>
                    <p style="font-size: 0.84rem; color: var(--on-dark-2); line-height: 1.5; margin: 0;">
                        Konstruksi bentuk 3 dimensi sesuai ukuran riil, pencahayaan, dan pemilihan material.
                    </p>
                </div>

                <div class="card-dark" style="padding: 24px 20px; border-left: 3px solid var(--gold); border-radius: var(--radius-card);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--gold); font-weight: 700; display: block; margin-bottom: 4px;">Tahap 3</span>
                    <h4 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 8px;">Draft Preview & Revisi</h4>
                    <p style="font-size: 0.84rem; color: var(--on-dark-2); line-height: 1.5; margin: 0;">
                        Klien melihat render awal, memberikan koreksi detail, dan penyesuaian hingga puas.
                    </p>
                </div>

                <div class="card-dark" style="padding: 24px 20px; border-left: 3px solid var(--gold); border-radius: var(--radius-card);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--gold); font-weight: 700; display: block; margin-bottom: 4px;">Tahap 4</span>
                    <h4 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 8px;">Serah Terima Master</h4>
                    <p style="font-size: 0.84rem; color: var(--on-dark-2); line-height: 1.5; margin: 0;">
                        Render resolusi tinggi (4K/8K), gambar kerja teknis CAD/PDF siap cetak dan siap bangun.
                    </p>
                </div>
            </div>

            {{-- CTA to Tracker Page --}}
            <div style="background: rgba(212, 160, 23, 0.08); border: 1px solid rgba(212, 160, 23, 0.3); border-radius: var(--radius-feat); padding: 28px 36px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div style="max-width: 600px;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: #ffffff; margin-bottom: 6px;">
                        Sudah Memiliki Kode Proyek?
                    </h3>
                    <p style="font-size: 0.9rem; color: var(--on-dark-2); margin: 0;">
                        Masukkan kode proyek unik dan nomor telepon terdaftar Anda untuk melihat timeline pengerjaan langsung.
                    </p>
                </div>
                <a href="{{ $lroute('tracker.show') }}" class="btn btn-primary btn-lg">
                    Buka Fitur Lacak Proyek;
                </a>
            </div>
        </div>
    </section>

    {{-- ========== ALUR PEMESANAN (HOW TO ORDER) ========== --}}
    <section class="section" style="padding: 70px 0;">
        <div class="container">
            <x-section-heading
                eyebrow="Workflow Profesional"
                title="Bagaimana Cara Memulai Proyek Bersama Kami?" />

            <div class="grid grid-cols-4" style="gap: 24px; text-align: center;">
                <div class="card-light" style="padding: 28px 20px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--gold); color: #000; font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">1</div>
                    <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">Pilih Paket & Hubungi</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                        Pilih paket layanan di atas atau konsultasikan kebutuhan desain khusus Anda via WhatsApp.
                    </p>
                </div>

                <div class="card-light" style="padding: 28px 20px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--gold); color: #000; font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">2</div>
                    <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">Kesepakatan & DP</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                        Konfirmasi penawaran biaya, jangka waktu pengerjaan, dan pembayaran DP awal.
                    </p>
                </div>

                <div class="card-light" style="padding: 28px 20px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--gold); color: #000; font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">3</div>
                    <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">Pengerjaan & Pantau</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                        Dapatkan Kode Proyek dan pantau setiap tahap di halaman Lacak Proyek secara real-time.
                    </p>
                </div>

                <div class="card-light" style="padding: 28px 20px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--gold); color: #000; font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">4</div>
                    <h4 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 8px;">Revisi & Serah Terima</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                        Sesi perbaikan hingga sesuai keinginan, disusul pelunasan dan penyerahan seluruh file master.
                    </p>
                </div>
            </div>
        </div>
    </section>


    {{-- ========== CUSTOM INQUIRY BANNER ========== --}}
    @if ($whatsapp = $settings('company_whatsapp'))
        <div class="container" style="padding: 60px 0 80px;">
            <div class="card-dark" style="padding: 40px; border-radius: var(--radius-feat); border: 1px solid var(--line-dark);">
                <div style="display: flex; flex-direction: column; gap: 20px; align-items: flex-start; justify-content: space-between;" class="flex-between">
                    <div style="max-width: 680px;">
                        <h2 style="font-size: 1.6rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">
                            Punya Kebutuhan Desain di Luar Paket di Atas?
                        </h2>
                        <p style="font-size: 0.95rem; color: var(--on-dark-2); line-height: 1.6; margin: 0;">
                            Kami siap melayani kebutuhan visualisasi arsitektur kustom, render skala kawasan perumahan, animasi 3D walkthrough, hingga tender proyek berskala besar.
                        </p>
                    </div>
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg">
                        <span>Konsultasi Sekarang</span>
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection
