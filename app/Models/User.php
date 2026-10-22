<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * Roles, coarsest to finest privilege.
     *
     * Legacy had NO role column: edit.php and editkarir.php gated only on
     * isset($_SESSION["user_id"]), and reg.php let anyone register. Every
     * account was therefore a superadmin.
     */
    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_LMS_ADMIN = 'lms_admin';

    public const ROLE_EDITOR = 'editor';

    /*
     * Access areas. These are the single source of truth for staff
     * authorization; resources and policies call User::can() rather than
     * comparing role strings directly.
     */
    public const AREA_CONTENT = 'content';

    public const AREA_EVENTS = 'events';

    public const AREA_REGISTRATIONS = 'registrations';

    public const AREA_CURRICULUM = 'curriculum';

    public const AREA_PROJECTS = 'projects';

    public const AREA_HIRING = 'hiring';

    public const AREA_CUSTOMERS = 'customers';

    public const AREA_ENROLLMENTS = 'enrollments';

    public const AREA_ASSIGNMENTS = 'assignments';

    public const AREA_PAYMENTS = 'payments';

    public const AREA_MEDIA = 'media';

    public const AREA_SETTINGS = 'settings';

    public const AREA_USERS = 'users';

    public const AREA_AUDIT = 'audit';

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<int, string> */
    public static function roles(): array
    {
        return [
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_LMS_ADMIN,
            self::ROLE_EDITOR,
        ];
    }

    /** @return array<string, string> */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_SUPERADMIN => 'Superadmin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_LMS_ADMIN => 'Admin LMS',
            self::ROLE_EDITOR => 'Editor',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * Central access matrix (plan §3). Returning false for inactive users is
     * deliberate: deactivation must deny every area even if a session survives.
     *
     * The signature stays compatible with Laravel's Authorizable::can() so an
     * ordinary Gate check (can('update', $model)) keeps working. Area strings
     * are resolved here; everything else is delegated to the Gate.
     */
    public function can($ability, $arguments = []): bool
    {
        if (is_string($ability) && $arguments === [] && in_array($ability, self::areas(), true)) {
            return $this->canAccessArea($ability);
        }

        return Gate::forUser($this)->check($ability, $arguments);
    }

    /** @return array<int, string> */
    public static function areas(): array
    {
        return [
            self::AREA_CONTENT,
            self::AREA_EVENTS,
            self::AREA_REGISTRATIONS,
            self::AREA_CURRICULUM,
            self::AREA_PROJECTS,
            self::AREA_HIRING,
            self::AREA_CUSTOMERS,
            self::AREA_ENROLLMENTS,
            self::AREA_ASSIGNMENTS,
            self::AREA_PAYMENTS,
            self::AREA_MEDIA,
            self::AREA_SETTINGS,
            self::AREA_USERS,
            self::AREA_AUDIT,
        ];
    }

    public function canAccessArea(string $area): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->role === self::ROLE_SUPERADMIN) {
            return true;
        }

        return match ($area) {
            self::AREA_CONTENT,
            self::AREA_EVENTS,
            self::AREA_PROJECTS => $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_EDITOR,

            // LMS admins can browse/download media but the resource itself is
            // read-only and deletion stays admin+.
            self::AREA_MEDIA => in_array($this->role, [
                self::ROLE_ADMIN,
                self::ROLE_EDITOR,
                self::ROLE_LMS_ADMIN,
            ], true),

            self::AREA_REGISTRATIONS => $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_EDITOR,

            self::AREA_CURRICULUM,
            self::AREA_ENROLLMENTS,
            self::AREA_ASSIGNMENTS => $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_LMS_ADMIN,

            self::AREA_HIRING,
            self::AREA_CUSTOMERS,
            self::AREA_PAYMENTS,
            self::AREA_SETTINGS,
            self::AREA_AUDIT => $this->role === self::ROLE_ADMIN,

            self::AREA_USERS => false,

            default => false,
        };
    }

    /** Can manage content (everything except users and settings). */
    public function canManageContent(): bool
    {
        return $this->canAccessArea(self::AREA_CONTENT);
    }

    /** Can manage hiring (vacancies, applications, applicant documents). */
    public function canManageHiring(): bool
    {
        return $this->canAccessArea(self::AREA_HIRING);
    }

    /** Can manage other users -- superadmin only. */
    public function canManageUsers(): bool
    {
        return $this->canAccessArea(self::AREA_USERS);
    }

    /** Can change site-wide settings. */
    public function canManageSettings(): bool
    {
        return $this->canAccessArea(self::AREA_SETTINGS);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasRole(
            self::ROLE_SUPERADMIN,
            self::ROLE_ADMIN,
            self::ROLE_LMS_ADMIN,
            self::ROLE_EDITOR,
        );
    }
}
