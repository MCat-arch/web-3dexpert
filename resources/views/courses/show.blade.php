@extends('layouts.app')

@section('title', $course->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $course->description), 155))

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <a href="{{ $lroute('courses.index') }}">{{ __('courses.title') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ Str::limit($course->title, 40) }}</span>
            </nav>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                @if ($course->category)
                    <span class="badge badge-amber">
                        {{ $course->category }}
                    </span>
                @endif
                @if ($course->level)
                    <span class="badge badge-gold">
                        {{ __('courses.level_'.$course->level) }}
                    </span>
                @endif

                @if ($course->hasLms())
                    <span class="badge badge-dark">
                        {{ __('courses.lifetime_access') }}
                    </span>
                @else
                    <span class="badge badge-dark">
                        Mentoring Langsung
                    </span>
                @endif

                @if ($course->isFree())
                    <span class="badge badge-success">
                        {{ __('courses.free') }}
                    </span>
                @endif
            </div>

            <h1 class="page-banner-title">{{ $course->title }}</h1>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px;" class="grid-cols-2">
            {{-- Left column: Details and Syllabus --}}
            <div>
                @if ($course->description)
                    <div class="card-light" style="padding: 28px; margin-bottom: 30px;">
                        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 16px;">Deskripsi Kursus</h2>
                        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 1rem; line-height: 1.7; color: var(--text);">
                            @foreach (preg_split('/\R{2,}/', (string) $course->description) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Highlights Row --}}
                <div class="card-light" style="padding: 20px; margin-bottom: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; text-align: center;">
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">Total Pelajaran</span>
                        <strong style="font-size: 1.2rem; color: var(--text);">{{ $course->lessons->count() }} Materi</strong>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">Durasi</span>
                        <strong style="font-size: 1.2rem; color: var(--text);">
                            {{ $course->estimated_minutes ? ($course->estimated_minutes >= 60 ? round($course->estimated_minutes / 60, 1).' Jam' : $course->estimated_minutes.' Menit') : '-' }}
                        </strong>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">Akses</span>
                        <strong style="font-size: 1.2rem; color: var(--gold-hover);">Online Selamanya</strong>
                    </div>
                </div>

                {{-- Syllabus (LMS courses only) --}}
                @if ($course->hasLms())
                <div class="card-light" style="padding: 28px;">
                    <h2 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 8px;">{{ __('courses.syllabus') }}</h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 24px;">
                        {{ $sectionsPayload->count() }} Bagian &middot; {{ $course->lessons->count() }} Pelajaran
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        @forelse ($sectionsPayload as $sectionIndex => $sec)
                            <div style="border: 1px solid var(--line-light); border-radius: var(--radius-card); overflow: hidden;">
                                <div style="background: #f7f7f7; padding: 14px 18px; font-weight: 600; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line-light);">
                                    <span>{{ $sectionIndex + 1 }}. {{ $sec['title'] }}</span>
                                    <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">{{ count($sec['lessons']) }} materi</span>
                                </div>

                                <div style="display: flex; flex-direction: column;">
                                    @foreach ($sec['lessons'] as $les)
                                        <div style="padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span>{{ $les['unlocked'] ? '▶' : '🔒' }}</span>
                                                <span style="color: {{ $les['unlocked'] ? 'var(--text)' : 'var(--text-muted)' }};">
                                                    {{ $les['title'] }}
                                                </span>
                                                @if ($les['is_free_preview'])
                                                    <span class="badge badge-amber" style="font-size: 0.7rem;">
                                                        {{ __('courses.free_preview') }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div style="display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--text-muted);">
                                                @if ($les['duration_formatted'])
                                                    <span>{{ $les['duration_formatted'] }}</span>
                                                @endif

                                                @if ($les['unlocked'])
                                                    <a href="{{ $lroute('courses.player.show', ['course' => $course->slug, 'lesson' => $les['id']]) }}"
                                                       style="color: var(--gold-hover); font-weight: 600; text-decoration: none;">
                                                        Buka &rarr;
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p style="color: var(--text-muted); font-size: 0.95rem;">{{ __('courses.no_lessons') }}</p>
                        @endforelse
                    </div>
                </div>

                @else
                {{-- Advanced / real-world mentoring: no syllabus, show program highlights --}}
                <div class="card-light" style="padding: 28px; margin-bottom: 30px;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 16px;">Program Mentoring Langsung</h2>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; line-height: 1.7; color: var(--text);">
                        <li style="display: flex; gap: 10px;"><span style="color:var(--gold-hover);font-weight:700;">✓</span> Terlibat langsung dalam proyek arsitektur & interior nyata</li>
                        <li style="display: flex; gap: 10px;"><span style="color:var(--gold-hover);font-weight:700;">✓</span> Bimbingan intensif dari mentor 3D Expert berpengalaman</li>
                        <li style="display: flex; gap: 10px;"><span style="color:var(--gold-hover);font-weight:700;">✓</span> Pengukuran lapangan, konsep desain, hingga presentasi klien</li>
                        <li style="display: flex; gap: 10px;"><span style="color:var(--gold-hover);font-weight:700;">✓</span> Sertifikat & portofolio proyek nyata setelah selesai</li>
                        <li style="display: flex; gap: 10px;"><span style="color:var(--gold-hover);font-weight:700;">✓</span> Kuota sangat terbatas per batch</li>
                    </ul>
                </div>
                @endif
            </div>

            {{-- Right column: Pricing & Action Card --}}
            <div>
                <div class="card-light" style="padding: 24px; position: sticky; top: 90px;">
                    {{-- Media Preview --}}
                    <div style="width: 100%; aspect-ratio: 16 / 9; border-radius: var(--radius-card); overflow: hidden; background: #000000; margin-bottom: 20px;">
                        @if ($previewLesson && !empty($previewLesson['youtube_id']))
                            <x-video-embed
                                :youtube-id="$previewLesson['youtube_id']"
                                :title="$course->title . ' - Free Preview'" />
                        @elseif ($course->cover_url)
                            <img src="{{ $course->cover_url }}" alt="{{ $course->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center; color: var(--gold);">
                                🎬
                            </div>
                        @endif
                    </div>

                    <div style="display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('courses.total_payment') }}</span>
                            <div style="font-size: 1.85rem; font-weight: 700; color: var(--text);">
                                {{ $course->formattedPrice() }}
                            </div>
                        </div>
                        @if (! $course->isFree())
                            <span class="badge badge-gold">Sekali Bayar</span>
                        @endif
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px;">
                        @if ($course->hasLms())
                            {{-- LMS courses: enroll / buy / resume --}}
                            @if ($isEnrolled)
                                <a href="{{ $lroute('courses.player.show', ['course' => $course->slug]) }}" class="btn btn-primary btn-block btn-lg">
                                    {{ __('courses.resume_learning') }}
                                </a>
                            @elseif (auth('customer')->check())
                                <a href="{{ $lroute('courses.checkout.show', ['course' => $course->slug]) }}" class="btn btn-primary btn-block btn-lg">
                                    {{ $course->isFree() ? __('courses.enroll_now') : __('courses.buy_now') }}
                                </a>
                            @else
                                <a href="{{ $lroute('customer.login') }}" class="btn btn-primary btn-block btn-lg">
                                    {{ __('courses.enroll_now') }}
                                </a>
                                <p style="text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 6px;">
                                    Belum punya akun? <a href="{{ $lroute('customer.register') }}" style="color: var(--gold-hover); font-weight: 600;">Daftar akun</a>
                                </p>
                            @endif
                        @else
                            {{-- Advanced / real-world: WhatsApp CTA --}}
                            @php
                                $ctaUrl = $course->whatsappUrl($settings('company_whatsapp', 'https://wa.me/6282338389488'));
                            @endphp
                            <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer"
                               class="btn btn-primary btn-block btn-lg">
                                💬 Hubungi & Daftar via WhatsApp
                            </a>
                            <p style="text-align: center; font-size: 0.82rem; color: var(--text-muted); margin-top: 6px;">
                                Tim kami akan menghubungi Anda dalam 1×24 jam
                            </p>
                        @endif
                    </div>

                    <div style="border-top: 1px solid var(--line-light); padding-top: 16px;">
                        @if ($course->hasLms())
                            <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px;">Yang Anda Dapatkan:</h4>
                            <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem; color: var(--text);">
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Akses video materi HD selamanya</li>
                                <li style="display: flex; align-items: center; gap: 8px;">✓ File latihan & aset 3D pendukung</li>
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Belajar di smartphone, tablet, PC</li>
                            </ul>
                        @else
                            <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px;">Yang Anda Dapatkan:</h4>
                            <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem; color: var(--text);">
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Proyek nyata di portofolio Anda</li>
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Bimbingan intensif mentor senior</li>
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Sertifikat resmi 3D Expert</li>
                                <li style="display: flex; align-items: center; gap: 8px;">✓ Jaringan profesional arsitektur</li>
                            </ul>
                        @endif
                    </div>

                    @if ($whatsapp = $settings('company_whatsapp'))
                        <div style="border-top: 1px solid var(--line-light); padding-top: 16px; margin-top: 16px; text-align: center;">
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-decoration: none;">
                                💬 {{ __('courses.consultation_cta') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
