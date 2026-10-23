<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon(asset('favicon.ico'))
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.25rem')
            // Pinned explicitly rather than inheriting auth.defaults.guard.
            // The point of D1 is that staff and public auth cannot touch; a
            // future change to the default guard must not silently widen who
            // can reach this panel.
            ->authGuard('web')
            ->login(Login::class)
            ->profile()
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Filament's built-in action labels depend on a matching locale pack.
     * This panel is intentionally Indonesian-only, so set the labels once
     * here instead of repeating ->label() on every resource table. Explicit
     * labels in a resource still win because configureUsing only supplies the
     * default before the action is customised.
     */
    public function boot(): void
    {
        ViewAction::configureUsing(fn (ViewAction $action) => $action->label('Lihat'));
        EditAction::configureUsing(fn (EditAction $action) => $action->label('Ubah'));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->label('Hapus'));
        CreateAction::configureUsing(fn (CreateAction $action) => $action->label('Tambah'));

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action->label('Hapus yang dipilih'));
        ForceDeleteAction::configureUsing(fn (ForceDeleteAction $action) => $action->label('Hapus permanen'));
        ForceDeleteBulkAction::configureUsing(fn (ForceDeleteBulkAction $action) => $action->label('Hapus permanen yang dipilih'));
        RestoreAction::configureUsing(fn (RestoreAction $action) => $action->label('Pulihkan'));
        RestoreBulkAction::configureUsing(fn (RestoreBulkAction $action) => $action->label('Pulihkan yang dipilih'));
    }
}
