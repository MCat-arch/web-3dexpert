<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;

/**
 * Staff authentication trail. The customer guard is deliberately ignored:
 * public account logins are high-volume and not part of the admin audit.
 */
class LogStaffAuthentication
{
    public function __construct(private AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->log('auth.login', $event->user, [], ['guard' => 'web']);

        // Cheap online marker for the Users table without a new column.
        Cache::put('staff.online.'.$event->user->getKey(), true, now()->addMinutes(15));
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        Cache::forget('staff.online.'.$event->user->getKey());

        $this->audit->log('auth.logout', $event->user, [], ['guard' => 'web']);
    }
}
