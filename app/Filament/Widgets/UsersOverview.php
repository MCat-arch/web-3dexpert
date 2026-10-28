<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Staff + customer account health, for the superadmin dashboard.
 *
 * Answers the two questions support actually gets: "how many customers can
 * log in and enrol right now?" (active + verified) and "how many accounts are
 * stuck half-registered?" (active but never verified the email).
 */
class UsersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    protected function getStats(): array
    {
        $data = Cache::remember(
            'dashboard:users:summary',
            config('traffic.cache_seconds', 60),
            fn (): array => $this->summarise(),
        );

        return [
            Stat::make('Pelanggan aktif', $this->format($data['customers_active']))
                ->description($data['customers_verified_active'].' terverifikasi · '.$data['customers_unverified_active'].' belum verifikasi')
                ->descriptionIcon('heroicon-o-users')
                ->color($data['customers_unverified_active'] > 0 ? 'warning' : 'success'),

            Stat::make('Pelanggan aktif terverifikasi', $this->format($data['customers_verified_active']))
                ->description('Bisa login dan mendaftar kursus')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Pelanggan aktif belum verifikasi', $this->format($data['customers_unverified_active']))
                ->description('Email belum dikonfirmasi')
                ->descriptionIcon('heroicon-o-envelope')
                ->color($data['customers_unverified_active'] > 0 ? 'warning' : 'gray'),

            Stat::make('Pelanggan baru (7 hari)', $this->format($data['customers_new_7d']))
                ->description('Registrasi 7 hari terakhir')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('info'),

            Stat::make('Pelanggan aktif login 7 hari', $this->format($data['customers_logged_7d']))
                ->description('Dari '.$this->format($data['customers_active']).' pelanggan aktif')
                ->descriptionIcon('heroicon-o-arrow-right-on-rectangle')
                ->color('primary'),

            Stat::make('Staf aktif', $this->format($data['staff_active']))
                ->description($data['staff_verified'].' terverifikasi · '.$data['staff_unverified'].' belum verifikasi')
                ->descriptionIcon('heroicon-o-identification')
                ->color($data['staff_unverified'] > 0 ? 'warning' : 'success'),

            Stat::make('Staf belum verifikasi', $this->format($data['staff_unverified']))
                ->description($data['staff_role_breakdown'])
                ->descriptionIcon('heroicon-o-shield-exclamation')
                ->color($data['staff_unverified'] > 0 ? 'danger' : 'gray'),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function summarise(): array
    {
        $activeCustomers = Customer::query()->where('is_active', true);

        $roles = User::query()
            ->where('is_active', true)
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $labels = User::roleLabels();

        $breakdown = $roles
            ->map(fn ($total, string $role): string => ($labels[$role] ?? $role).': '.$total)
            ->implode(' · ');

        return [
            'customers_active' => (clone $activeCustomers)->count(),
            'customers_verified_active' => (clone $activeCustomers)->whereNotNull('email_verified_at')->count(),
            'customers_unverified_active' => (clone $activeCustomers)->whereNull('email_verified_at')->count(),
            'customers_new_7d' => Customer::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'customers_logged_7d' => (clone $activeCustomers)->where('last_login_at', '>=', now()->subDays(7))->count(),
            'staff_active' => User::query()->where('is_active', true)->count(),
            'staff_verified' => User::query()->where('is_active', true)->whereNotNull('email_verified_at')->count(),
            'staff_unverified' => User::query()->where('is_active', true)->whereNull('email_verified_at')->count(),
            'staff_role_breakdown' => $breakdown === '' ? 'Belum ada staf aktif' : $breakdown,
        ];
    }

    private function format(int|string $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }
}
