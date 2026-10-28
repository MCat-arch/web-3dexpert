<?php

namespace App\Filament\Widgets;

use App\Models\AssignmentSubmission;
use App\Models\CourseEnrollment;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\MediaAsset;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * The stat cards are cheap aggregate queries and carry the role-specific
     * dashboard summary, so render them with the page instead of behind a
     * loading placeholder.
     */
    protected static bool $isLazy = false;

    /**
     * The superadmin gets SuperadminOverview instead: same data plus traffic,
     * customers and totals, in one grid. Other roles keep these cards.
     */
    public static function canView(): bool
    {
        return auth()->check() && ! (auth()->user()?->isSuperadmin() ?? false);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user?->can(User::AREA_EVENTS)) {
            $stats[] = Stat::make('Acara terbit', (string) Event::query()->where('is_published', true)->count())
                ->description('Total acara yang tampil di situs')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('primary');

            $stats[] = Stat::make('Acara mendatang', (string) Event::query()
                ->where('is_published', true)
                ->where('starts_at', '>=', now())
                ->count())
                ->description('Acara dengan jadwal ke depan')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info');
        }

        if ($user?->can(User::AREA_REGISTRATIONS)) {
            $stats[] = Stat::make('Pendaftar terkonfirmasi', (string) EventRegistration::query()
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->count())
                ->description('Peserta acara terkonfirmasi')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('success');
        }

        if ($user?->can(User::AREA_HIRING)) {
            $stats[] = Stat::make('Lowongan terbuka', (string) JobVacancy::query()
                ->where('is_published', true)
                ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
                ->count())
                ->description('Lowongan yang masih menerima lamaran')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('primary');

            $stats[] = Stat::make('Pelamar baru', (string) JobApplication::query()
                ->where('status', JobApplication::STATUS_SUBMITTED)
                ->count())
                ->description('Lamaran menunggu screening')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('warning');
        }

        if ($user?->can(User::AREA_ENROLLMENTS)) {
            $stats[] = Stat::make('Siswa aktif', (string) CourseEnrollment::query()
                ->where('status', CourseEnrollment::STATUS_ACTIVE)
                ->count())
                ->description('Pendaftaran kursus aktif')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('success');
        }

        if ($user?->can(User::AREA_ASSIGNMENTS)) {
            $stats[] = Stat::make('Tugas menunggu', (string) AssignmentSubmission::query()
                ->where('status', AssignmentSubmission::STATUS_SUBMITTED)
                ->count())
                ->description('Pengumpulan belum dinilai')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color('warning');
        }

        if ($user?->can(User::AREA_MEDIA)) {
            $stats[] = Stat::make('Aset media', (string) MediaAsset::query()->count())
                ->description('Berkas di perpustakaan media')
                ->descriptionIcon('heroicon-o-photo')
                ->color('gray');
        }

        if ($user?->can(User::AREA_PAYMENTS)) {
            $revenue = Payment::query()
                ->where('status', Payment::STATUS_SETTLEMENT)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('gross_amount');

            $stats[] = Stat::make('Pendapatan bulan ini', 'Rp '.number_format((float) $revenue, 0, ',', '.'))
                ->description('Total pembayaran settlement bulan berjalan')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success');
        }

        return $stats;
    }
}
