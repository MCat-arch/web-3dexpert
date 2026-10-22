<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Base resource for every admin screen.
 *
 * Each concrete resource declares an access area; all CRUD entry points are
 * denied unless the signed-in staff member can access that area. Resources
 * that are intentionally read-only (registrations, payments, audit, media)
 * override canCreate/canDelete/canDeleteAny to false.
 */
abstract class AdminResource extends Resource
{
    protected static string $accessArea = User::AREA_CONTENT;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(static::$accessArea) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(static::$accessArea) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can(static::$accessArea) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can(static::$accessArea) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can(static::$accessArea) ?? false;
    }
}
