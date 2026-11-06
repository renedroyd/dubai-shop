<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class AdminResourcePolicy
{
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission("{$this->resource}.view");
    }

    public function view(User $user, Model $record): bool
    {
        return $user->hasAdminPermission("{$this->resource}.view");
    }

    public function create(User $user): bool
    {
        return $user->hasAdminPermission("{$this->resource}.create");
    }

    public function update(User $user, Model $record): bool
    {
        return $user->hasAdminPermission("{$this->resource}.update");
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->hasAdminPermission("{$this->resource}.delete");
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasAdminPermission("{$this->resource}.delete");
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->hasAdminPermission("{$this->resource}.update");
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->hasAdminPermission("{$this->resource}.delete");
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasAdminPermission("{$this->resource}.delete");
    }

    public function reorder(User $user): bool
    {
        return $user->hasAdminPermission("{$this->resource}.update");
    }
}
