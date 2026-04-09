<?php

namespace App\Services;

use App\Models\User;

class BootstrapAdminService
{
    public function ensureForUser(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! config('app.bootstrap_admin_enabled', true)) {
            return false;
        }

        $bootstrapEmail = strtolower(trim((string) config('app.bootstrap_admin_email')));
        if ($bootstrapEmail === '') {
            return false;
        }

        if (strtolower($user->email) !== $bootstrapEmail) {
            return false;
        }

        if ($user->role === 'admin') {
            return false;
        }

        $user->forceFill(['role' => 'admin'])->save();

        return true;
    }
}
