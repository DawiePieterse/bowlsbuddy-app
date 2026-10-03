<?php

namespace App\Policies;

use App\Models\MemberPayment;
use App\Models\User;

/** Membership payments belong with member management: the "admin.user" privilege. */
class MemberPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function view(User $user, MemberPayment $payment): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function update(User $user, MemberPayment $payment): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function delete(User $user, MemberPayment $payment): bool
    {
        return $user->hasPrivilege('admin.user');
    }
}
