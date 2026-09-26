<?php

namespace App\Policies;

use App\Models\Rink;
use App\Models\User;

/**
 * Rinks are configuration, so they follow the "admin.config" privilege. They are edited, never
 * created or deleted from the panel: the club's rinks come from the setup, and bookings hang
 * off them.
 */
class RinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('admin.config');
    }

    public function view(User $user, Rink $rink): bool
    {
        return $user->hasPrivilege('admin.config');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Rink $rink): bool
    {
        return $user->hasPrivilege('admin.config');
    }

    public function delete(User $user, Rink $rink): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
