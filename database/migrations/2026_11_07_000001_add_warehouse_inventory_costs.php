<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->unsignedBigInteger('acquisition_cost_amount')->nullable()->after('quantity_blocked');
            $table->unsignedBigInteger('minimum_sale_price_amount')->nullable()->after('acquisition_cost_amount');
        });

        Schema::create('inventory_cost_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('previous_acquisition_cost_amount')->nullable();
            $table->unsignedBigInteger('new_acquisition_cost_amount')->nullable();
            $table->unsignedBigInteger('previous_minimum_sale_price_amount')->nullable();
            $table->unsignedBigInteger('new_minimum_sale_price_amount')->nullable();
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['inventory_id', 'created_at']);
        });

        foreach (['admin', 'inventory-manager'] as $slug) {
            $role = DB::table('admin_roles')->where('slug', $slug)->first();
            if (! $role) {
                continue;
            }

            $permissions = json_decode($role->permissions, true) ?: [];
            foreach (['inventory_costs.view', 'inventory_costs.update'] as $permission) {
                if (! in_array($permission, $permissions, true)) {
                    $permissions[] = $permission;
                }
            }

            DB::table('admin_roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_changes');

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn(['acquisition_cost_amount', 'minimum_sale_price_amount']);
        });
    }
};
