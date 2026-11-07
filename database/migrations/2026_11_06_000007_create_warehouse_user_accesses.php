<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_user_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'user_id']);
        });

        foreach (['admin', 'inventory-manager'] as $roleSlug) {
            $role = DB::table('admin_roles')->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $permissions = json_decode($role->permissions, true) ?: [];
            if (! in_array('pos_reports.view', $permissions, true)) {
                $permissions[] = 'pos_reports.view';
                DB::table('admin_roles')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_user_accesses');
    }
};
