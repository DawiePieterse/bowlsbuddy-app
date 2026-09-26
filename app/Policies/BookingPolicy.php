<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('admin.booking');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->hasPrivilege('admin.booking');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('admin.booking');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->hasPrivilege('admin.booking');
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->hasPrivilege('admin.booking');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPrivilege('admin.booking');
    }
}
