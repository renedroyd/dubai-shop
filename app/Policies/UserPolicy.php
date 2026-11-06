<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageAdminAccess();
    }

    public function view(User $user, User $record): bool
    {
        return $user->canManageAdminAccess();
    }

    public function create(User $user): bool
    {
        return $user->canManageAdminAccess();
    }

    public function update(User $user, User $record): bool
    {
        return $user->canManageAdminAccess() && ! $record->isSuperAdmin();
    }

    public function delete(User $user, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
