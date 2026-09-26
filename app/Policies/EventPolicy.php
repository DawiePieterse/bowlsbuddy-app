<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('admin.event');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPrivilege('admin.event');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('admin.event');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPrivilege('admin.event');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasPrivilege('admin.event');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPrivilege('admin.event');
    }
}
