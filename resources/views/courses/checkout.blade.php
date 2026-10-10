@extends('layouts.app')

@section('title', __('courses.checkout_title') . ' | ' . $course->title)

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <a href="{{ $lroute('courses.index') }}">{{ __('courses.title') }}</a>
                <span>/</span>
                <a
                    href="{{ $lroute('courses.show', ['course' => $course->slug]) }}">{{ Str::limit($course->title, 30) }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('courses.checkout_title') }}</span>
            </nav>

            <h1 class="page-banner-title">{{ __('courses.checkout_title') }}</h1>
            <p class="page-banner-desc">{{ __('courses.checkout_subtitle') }}</p>
        </div>
    </div>

    <div class="container" style="max-width: 1000px; padding-bottom: 70px;">
        <form method="POST" action="{{ $lroute('courses.checkout.store', ['course' => $course->slug]) }}">
            @csrf

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 32px;" class="grid-cols-2">
                {{-- Left: Student Info --}}
                <div class="card-light" style="padding: 28px;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 20px;">
                        👤 {{ __('courses.student_info') }}
                    </h2>

                    <div class="form-group">
                        <label for="student_name" class="form-label">
                            {{ __('courses.student_name') }}<span style="color: var(--gold-hover);">*</span>
                        </label>
                        <input type="text" id="student_name" name="student_name"
                            value="{{ old('student_name', $customer->name) }}" required class="form-input">
                        @error('student_name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="student_email" class="form-label">
                            {{ __('courses.student_email') }}
                        </label>
                        <input type="email" id="student_email" name="student_email" value="{{ $customer->email }}" disabled
                            class="form-input" style="background: #f5f5f5; cursor: not-allowed;">
                        <p class="form-help">Email akun terverifikasi Anda</p>
                    </div>

                    <div class="form-group">
                        <label for="student_phone" class="form-label">
                            {{ __('courses.student_phone') }}<span style="color: var(--gold-hover);">*</span>
                        </label>
                        <input type="tel" id="student_phone" name="student_phone"
                            value="{{ old('student_phone', $customer->phone) }}" required placeholder="Contoh: 08123456789"
                            class="form-input">
                        <p class="form-help">Digunakan untuk konfirmasi dan update materi</p>
                        @error('student_phone')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div style="margin-top: 24px; padding: 18px; border: 1px solid var(--line-light); border-radius: var(--radius-card); background: #fafafa;">
                        <label class="form-label" style="margin-bottom: 8px;"> Metode Pembayaran</label>
                        <input type="hidden" name="payment_method" value="qris">
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #fff; border: 2px solid var(--gold); border-radius: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                
                                <div>
                                    <strong style="display: block; font-size: 0.95rem; color: var(--text);">QRIS Dinamis</strong>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">BCA, Mandiri, BRI, GoPay, OVO, DANA, ShopeePay</span>
                                </div>
                            </div>
                            <span class="badge badge-gold" style="font-size: 0.75rem;">Otomatis</span>
                        </div>
                    </div>
                </div>

                {{-- Right: Order Summary --}}
                <div>
                    <div class="card-light" style="padding: 24px; position: sticky; top: 90px;">
                        <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 20px;">
                            📋 {{ __('courses.order_summary') }}
                        </h2>

                        <div style="padding-bottom: 16px; border-bottom: 1px solid var(--line-light); margin-bottom: 16px;">
                            <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 6px;">{{ $course->title }}</h3>
                            <span class="badge badge-gold">{{ __('courses.level_' . $course->level) }}</span>
                        </div>

                        <div
                            style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; font-size: 0.95rem;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Biaya Kursus</span>
                                <span style="font-weight: 600;">{{ $course->formattedPrice() }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Biaya Admin</span>
                                <span style="font-weight: 600; color: var(--success);">GRATIS</span>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--line-light); font-size: 1.15rem; font-weight: 700;">
                                <span>{{ __('courses.total_payment') }}</span>
                                <span style="color: var(--text);">{{ $course->formattedPrice() }}</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            {{ $course->isFree() ? __('courses.confirm_enrollment') : __('courses.continue_payment') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection