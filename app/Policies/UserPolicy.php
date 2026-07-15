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
            'moderator',
            'editor',
            'translator',
            'viewer',
        ], true);
    }

    public function writeAdmin(User $user): bool
    {
        return in_array($user->role, [
            'super_admin',
            'admin',
            'moderator',
            'editor',
            'translator',
        ], true);
    }

    public function manageUsers(User $user): bool
    {
        return $user->role === 'super_admin';
    }
}
