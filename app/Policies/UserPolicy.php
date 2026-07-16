<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAdmin(User $user): bool
    {
        return in_array($user->role, [
            'super_admin',
            'admin',
        ], true);
    }

    public function writeAdmin(User $user): bool
    {
        return in_array($user->role, [
            'super_admin',
            'admin',
        ], true);
    }

    public function manageUsers(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true);
    }
}
