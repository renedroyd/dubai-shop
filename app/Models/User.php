<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password', 'is_admin', 'admin_access_enabled', 'storefront_locale', 'admin_locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'admin_access_enabled' => 'boolean',
        ];
    }

    public function directPermissions(): HasMany
    {
        return $this->hasMany(AdminUserPermission::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user');
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('slug', 'superadmin')->exists();
    }

    public function canManageAdminAccess(): bool
    {
        return (bool) $this->admin_access_enabled && $this->isSuperAdmin();
    }

    public function hasAdminPermission(string $permission): bool
    {
        if (! $this->admin_access_enabled) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $override = $this->directPermissions()->where('permission', $permission)->value('allowed');

        if ($override !== null) {
            return (bool) $override;
        }

        return $this->roles()
            ->get()
            ->contains(fn (AdminRole $role): bool => $role->grants($permission));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->admin_access_enabled
            && ($this->isSuperAdmin() || $this->hasAdminPermission('panel.access'));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
