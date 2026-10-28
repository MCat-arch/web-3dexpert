<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily public traffic for the last 30 days: page views and unique visitors.
 *
 * Bots are excluded, matching every other traffic number on the dashboard.
 * The aggregate runs once per cache window, not once per chart render.
 */
class TrafficChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Traffic 30 hari';

    protected ?string $description = 'Kunjungan halaman publik dan pengunjung unik per hari';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = 30;

        $series = Cache::remember(
            'dashboard:traffic:chart:'.$days,
            config('traffic.cache_seconds', 60),
            fn (): array => $this->dailySeries($days),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Kunjungan',
                    'data' => $series['visits'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Pengunjung unik',
                    'data' => $series['visitors'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    /**
     * @return array{labels: array<int, string>, visits: array<int, int>, visitors: array<int, int>}
     */
    private function dailySeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        try {
            $rows = DB::table('site_traffic')
                ->where('is_bot', false)
                ->where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as day, COUNT(*) as visits, COUNT(DISTINCT visitor_hash) as visitors')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->keyBy('day');
        } catch (Throwable) {
            $rows = collect();
        }

        $labels = [];
        $visits = [];
        $visitors = [];

        for ($i = 0; $i < $days; $i++) {
            $day = CarbonImmutable::instance($start)->addDays($i)->toDateString();
            $row = $rows->get($day);

            $labels[] = CarbonImmutable::parse($day)->translatedFormat('d M');
            $visits[] = (int) ($row->visits ?? 0);
            $visitors[] = (int) ($row->visitors ?? 0);
        }

        return [
            'labels' => $labels,
            'visits' => $visits,
            'visitors' => $visitors,
        ];
    }
}
