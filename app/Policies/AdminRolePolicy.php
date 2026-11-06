<?php

namespace App\Policies;

use App\Models\AdminRole;
use App\Models\User;

class AdminRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageAdminAccess();
    }

    public function view(User $user, AdminRole $role): bool
    {
        return $user->canManageAdminAccess();
    }

    public function create(User $user): bool
    {
        return $user->canManageAdminAccess();
    }

    public function update(User $user, AdminRole $role): bool
    {
        return $user->canManageAdminAccess() && $role->slug !== 'superadmin';
    }

    public function delete(User $user, AdminRole $role): bool
    {
        return $user->canManageAdminAccess() && ! $role->is_system && $role->slug !== 'superadmin';
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
