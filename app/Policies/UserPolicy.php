<?php

namespace App\Policies;

use App\Models\User;

/**
 * Managing members needs the "admin.user" privilege (PLAN.md 5.3 rule 10). Nobody deletes or
 * demotes themselves from the admin panel.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function create(User $user): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPrivilege('admin.user');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPrivilege('admin.user') && $model->uid !== $user->uid;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPrivilege('admin.user');
    }
}
