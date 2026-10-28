<?php

namespace App\Filament\Widgets;

use App\Models\CourseEnrollment;
use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Site-level performance and totals for the superadmin.
 *
 * Traffic numbers come from the `site_traffic` table written by
 * TrackSiteTraffic (bots excluded). Everything is cached for a minute so a
 * dashboard refresh is a handful of Redis reads instead of aggregate scans.
 */
class PerfOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    protected function getStats(): array
    {
        $data = Cache::remember(
            'dashboard:perf:summary',
            config('traffic.cache_seconds', 60),
            fn (): array => $this->summarise(),
        );

        return [
            Stat::make('Kunjungan hari ini', $this->format($data['visits_today']))
                ->description('Halaman publik, tanpa bot')
                ->descriptionIcon('heroicon-o-eye')
                ->color('primary'),

            Stat::make('Kunjungan 7 hari', $this->format($data['visits_7d']))
                ->description('Total tampilan halaman')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Pengunjung unik hari ini', $this->format($data['unique_today']))
                ->description('Berdasarkan hash IP harian')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Rata-rata respons 24 jam', $this->format($data['avg_ms_24h']).' ms')
                ->description('Dari '.$this->format($data['requests_24h']).' request terukur')
                ->descriptionIcon('heroicon-o-bolt')
                ->color($data['avg_ms_24h'] > 800 ? 'warning' : 'success'),

            Stat::make('Error server 24 jam', $this->format($data['server_errors_24h']))
                ->description($this->format($data['client_errors_24h']).' error klien (4xx)')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($data['server_errors_24h'] > 0 ? 'danger' : 'gray'),

            Stat::make('Total acara', $this->format($data['events_total']))
                ->description($this->format($data['events_published']).' terbit · '.$this->format($data['events_upcoming']).' mendatang')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Total enrollment', $this->format($data['enrollments_total']))
                ->description($this->format($data['enrollments_active']).' aktif · '.$this->format($data['enrollments_pending']).' pending')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('Antrian gagal', $this->format($data['failed_jobs']))
                ->description('Job di tabel failed_jobs')
                ->descriptionIcon('heroicon-o-queue-list')
                ->color($data['failed_jobs'] > 0 ? 'danger' : 'gray'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summarise(): array
    {
        $traffic = $this->trafficSummary();

        $enrollments = CourseEnrollment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_merge($traffic, [
            'events_total' => Event::query()->count(),
            'events_published' => Event::query()->where('is_published', true)->count(),
            'events_upcoming' => Event::query()->where('is_published', true)->where('starts_at', '>=', now())->count(),
            'enrollments_total' => (int) $enrollments->sum(),
            'enrollments_active' => (int) ($enrollments[CourseEnrollment::STATUS_ACTIVE] ?? 0),
            'enrollments_pending' => (int) ($enrollments[CourseEnrollment::STATUS_PENDING] ?? 0),
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
        ]);
    }

    /**
     * The widget must keep rendering while the first deploy has not migrated
     * `site_traffic` yet (and if the table is ever dropped for maintenance).
     *
     * @return array<string, int>
     */
    private function trafficSummary(): array
    {
        try {
            $today = now()->startOfDay();

            $base = DB::table('site_traffic')->where('is_bot', false);

            $visitsToday = (clone $base)->where('created_at', '>=', $today)->count();
            $uniqueToday = (clone $base)
                ->where('created_at', '>=', $today)
                ->distinct()
                ->count('visitor_hash');
            $visits7d = (clone $base)->where('created_at', '>=', now()->subDays(7))->count();

            $last24h = (clone $base)->where('created_at', '>=', now()->subDay());
            $requests24h = (clone $last24h)->count();
            $avgMs = (int) round((float) (clone $last24h)->avg('duration_ms'));
            $serverErrors = (clone $last24h)->where('status_code', '>=', 500)->count();
            $clientErrors = (clone $last24h)->whereBetween('status_code', [400, 499])->count();

            return [
                'visits_today' => $visitsToday,
                'visits_7d' => $visits7d,
                'unique_today' => $uniqueToday,
                'requests_24h' => $requests24h,
                'avg_ms_24h' => $avgMs,
                'server_errors_24h' => $serverErrors,
                'client_errors_24h' => $clientErrors,
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

    private function format(int|string $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }
}
