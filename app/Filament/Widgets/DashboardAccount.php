<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

/**
 * Filament's account card ("Selamat Datang ... / Keluar") shown at the top of
 * the dashboard.
 *
 * The superadmin dashboard is deliberately only the summary grid plus the
 * traffic chart, so the card is hidden for that role. Profile and logout stay
 * reachable from the avatar menu in the top-right corner. Every other role
 * keeps the card.
 */
class DashboardAccount extends AccountWidget
{
    public static function canView(): bool
    {
        return parent::canView() && ! (auth()->user()?->isSuperadmin() ?? false);
    }
}
