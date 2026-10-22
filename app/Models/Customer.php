<?php

namespace App\Models;

use App\Notifications\Customer\ResetPassword;
use App\Notifications\Customer\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A public visitor: courses, events, job applications, own projects.
 *
 * Deliberately separate from App\Models\User (decision D1). User backs the
 * Filament panel and its canAccessPanel() returns true for role 'editor',
 * which is the column default -- so a public account in that table would be an
 * admin. The legacy site shipped exactly that bug via reg.php.
 *
 * Two things here are load-bearing and must not be "tidied":
 *
 *   1. This class does NOT implement Filament\Models\Contracts\FilamentUser.
 *      Without that contract there is no code path from the customer guard to
 *      the admin panel. CustomerGuardIsolationTest asserts its absence.
 *
 *   2. There is no `role` column. Customers are authorised by ownership
 *      (Policies), not by tier. Adding roles here would recreate the coupling
 *      D1 exists to break.
 */
class Customer extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'phone'];

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

    /**
     * Deactivated by staff (P8). Checked at login rather than enforced by a
     * global scope: the record must stay readable for support and for the
     * records that reference it.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function canEnroll(): bool
    {
        return $this->is_active && $this->hasVerifiedEmail();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Password resets go through the `customers` broker and its own token
     * table, never the framework default -- see config/auth.php.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }
}
