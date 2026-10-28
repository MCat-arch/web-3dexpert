<?php

namespace App\Filament\Widgets;

use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The superadmin dashboard, in one grid.
 *
 * Every number here is all-time unless the label says otherwise (today, 7
 * days, 24 hours). The role-specific StatsOverview still serves the other
 * staff roles; this widget replaces it for the superadmin, together with the
 * table widgets, so the page stays a single readable summary.
 */
class SuperadminOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|array|null $columns = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    protected function getStats(): array
    {
        $data = Cache::remember(
            'dashboard:superadmin:overview',
            config('traffic.cache_seconds', 60),
            fn (): array => $this->summarise(),
        );

        return [
            Stat::make('Pelanggan aktif', $this->number($data['customers_active']))
                ->description($this->number($data['customers_verified_active']).' terverifikasi · '.$this->number($data['customers_unverified_active']).' belum verifikasi')
                ->descriptionIcon('heroicon-o-users')
                ->color($data['customers_unverified_active'] > 0 ? 'warning' : 'success'),

            Stat::make('Pelanggan aktif terverifikasi', $this->number($data['customers_verified_active']))
                ->description('Email terverifikasi dan akun aktif')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Pelanggan aktif belum verifikasi', $this->number($data['customers_unverified_active']))
                ->description('Email belum dikonfirmasi')
                ->descriptionIcon('heroicon-o-envelope')
                ->color($data['customers_unverified_active'] > 0 ? 'warning' : 'gray'),

            Stat::make('Total siswa', $this->number($data['students_total']))
                ->description('Pelanggan yang punya pendaftaran kursus')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('Total pendaftar acara', $this->number($data['event_registrations_total']))
                ->description($this->number($data['event_registrations_confirmed']).' terkonfirmasi')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('info'),

            Stat::make('Total pendaftar lowongan', $this->number($data['job_applications_total']))
                ->description('Lamaran masuk, semua status')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('primary'),

            Stat::make('Kunjungan hari ini', $this->number($data['visits_today']))
                ->description('Halaman publik, tanpa bot')
                ->descriptionIcon('heroicon-o-eye')
                ->color('primary'),

            Stat::make('Kunjungan 7 hari terakhir', $this->number($data['visits_7d']))
                ->description('Total tampilan halaman')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Pengunjung unik', $this->number($data['unique_today']))
                ->description('Hari ini, berdasarkan hash IP harian')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Rata-rata respons', $this->number($data['avg_ms_24h']).' ms')
                ->description('24 jam terakhir, dari '.$this->number($data['requests_24h']).' request')
                ->descriptionIcon('heroicon-o-bolt')
                ->color($data['avg_ms_24h'] > 800 ? 'warning' : 'success'),

            Stat::make('Error server', $this->number($data['server_errors_24h']))
                ->description($this->number($data['client_errors_24h']).' error klien (4xx), 24 jam')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($data['server_errors_24h'] > 0 ? 'danger' : 'gray'),

            Stat::make('Total acara', $this->number($data['events_total']))
                ->description('Semua acara, terbit maupun draf')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Total enrolment', $this->number($data['enrollments_total']))
                ->description($this->number($data['enrollments_active']).' aktif · '.$this->number($data['enrollments_pending']).' pending')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color('success'),

            Stat::make('Total acara terbit', $this->number($data['events_published']))
                ->description('Tampil di situs publik')
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('success'),

            Stat::make('Acara mendatang', $this->number($data['events_upcoming']))
                ->description('Terbit dan belum berlangsung')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Lowongan terbuka', $this->number($data['vacancies_open']))
                ->description('Masih menerima lamaran')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('primary'),

            Stat::make('Total pendapatan', 'Rp '.$this->number($data['revenue_total']))
                ->description('Semua pembayaran settlement')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summarise(): array
    {
        $activeCustomers = Customer::query()->where('is_active', true);
        $verifiedActive = (clone $activeCustomers)->whereNotNull('email_verified_at')->count();

        $enrollments = CourseEnrollment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_merge($this->trafficSummary(), [
            'customers_active' => (clone $activeCustomers)->count(),
            'customers_verified_active' => $verifiedActive,
            'customers_unverified_active' => (clone $activeCustomers)->whereNull('email_verified_at')->count(),

            'students_total' => CourseEnrollment::query()->distinct()->count('customer_id'),
            'enrollments_total' => (int) $enrollments->sum(),
            'enrollments_active' => (int) ($enrollments[CourseEnrollment::STATUS_ACTIVE] ?? 0),
            'enrollments_pending' => (int) ($enrollments[CourseEnrollment::STATUS_PENDING] ?? 0),

            'event_registrations_total' => EventRegistration::query()->count(),
            'event_registrations_confirmed' => EventRegistration::query()
                ->where('status', EventRegistration::STATUS_CONFIRMED)
                ->count(),

            'job_applications_total' => JobApplication::query()->count(),

            'events_total' => Event::query()->count(),
            'events_published' => Event::query()->where('is_published', true)->count(),
            'events_upcoming' => Event::query()
                ->where('is_published', true)
                ->where('starts_at', '>=', now())
                ->count(),

            'vacancies_open' => JobVacancy::query()
                ->where('is_published', true)
                ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
                ->count(),

            'revenue_total' => (int) Payment::query()
                ->where('status', Payment::STATUS_SETTLEMENT)
                ->sum('gross_amount'),
        ]);
    }

    /**
     * The grid must keep rendering while `site_traffic` is unavailable (before
     * the first migrate, or during maintenance), so every traffic query falls
     * back to zero instead of taking the dashboard down.
     *
     * @return array<string, int>
     */
    private function trafficSummary(): array
    {
        try {
            $base = DB::table('site_traffic')->where('is_bot', false);
            $today = now()->startOfDay();
            $last24h = (clone $base)->where('created_at', '>=', now()->subDay());

            return [
                'visits_today' => (clone $base)->where('created_at', '>=', $today)->count(),
                'visits_7d' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
                'unique_today' => (clone $base)
                    ->where('created_at', '>=', $today)
                    ->distinct()
                    ->count('visitor_hash'),
                'requests_24h' => (clone $last24h)->count(),
                'avg_ms_24h' => (int) round((float) (clone $last24h)->avg('duration_ms')),
                'server_errors_24h' => (clone $last24h)->where('status_code', '>=', 500)->count(),
                'client_errors_24h' => (clone $last24h)->whereBetween('status_code', [400, 499])->count(),
            ];
        } catch (Throwable) {
            return [
                'visits_today' => 0,
                'visits_7d' => 0,
                'unique_today' => 0,
                'requests_24h' => 0,
                'avg_ms_24h' => 0,
                'server_errors_24h' => 0,
                'client_errors_24h' => 0,
            ];
        }
    }

    private function number(int|string $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }
}
