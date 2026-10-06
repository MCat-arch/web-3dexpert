<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

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
    public const ROLE_EDITOR = 'editor';

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

    public static function roles(): array
    {
        return [self::ROLE_SUPERADMIN, self::ROLE_ADMIN, self::ROLE_EDITOR];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /** Can manage content (everything except users and settings). */
    public function canManageContent(): bool
    {
        return $this->is_active && $this->hasRole(
            self::ROLE_SUPERADMIN, self::ROLE_ADMIN, self::ROLE_EDITOR
        );
    }

    /** Can manage other users -- superadmin only. */
    public function canManageUsers(): bool
    {
        return $this->is_active && $this->isSuperadmin();
    }

    /** Can change site-wide settings. */
    public function canManageSettings(): bool
    {
        return $this->is_active && $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canManageContent();
    }
}
