<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $admin = DB::table('admin_roles')->where('slug', 'admin')->first();
        if ($admin) {
            $permissions = json_decode($admin->permissions, true) ?: [];
            foreach ([
                'pos_loyalty_settings.view',
                'pos_loyalty_settings.update',
                'pos_loyalty.adjust',
            ] as $permission) {
                if (! in_array($permission, $permissions, true)) $permissions[] = $permission;
            }
            DB::table('admin_roles')->where('id', $admin->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $admin = DB::table('admin_roles')->where('slug', 'admin')->first();
        if ($admin) {
            $permissions = json_decode($admin->permissions, true) ?: [];
            $permissions = array_values(array_diff($permissions, [
                'pos_loyalty_settings.view',
                'pos_loyalty_settings.update',
                'pos_loyalty.adjust',
            ]));
            DB::table('admin_roles')->where('id', $admin->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }
};
