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

        $addedPermissions = [
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'warehouse_locations.view', 'warehouse_locations.create', 'warehouse_locations.update', 'warehouse_locations.delete',
            'warehouse_transfers.view', 'warehouse_transfers.create', 'warehouse_transfers.update', 'warehouse_transfers.delete',
            'warehouse_transfer_rules.view', 'warehouse_transfer_rules.create', 'warehouse_transfer_rules.update', 'warehouse_transfer_rules.delete',
            'pos_locations.view', 'pos_locations.create', 'pos_locations.update', 'pos_locations.delete',
            'pos_terminals.view', 'pos_terminals.create', 'pos_terminals.update', 'pos_terminals.delete',
            'pos_cash_sessions.view', 'pos_cash_sessions.create', 'pos_cash_sessions.update',
            'inventory_location_balances.view', 'inventory_location_balances.update', 'pos_reports.view',
        ];

        $roleGrants = [
            'admin' => $addedPermissions,
            'inventory-manager' => [
                'warehouses.view', 'warehouse_locations.view',
                'warehouse_transfers.view', 'warehouse_transfers.create', 'warehouse_transfers.update',
                'warehouse_transfer_rules.view',
                'inventory_location_balances.view', 'inventory_location_balances.update',
                'pos_reports.view',
            ],
        ];

        foreach ($roleGrants as $roleSlug => $grants) {
            $role = DB::table('admin_roles')->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $permissions = array_values(array_unique([
                ...(json_decode($role->permissions, true) ?: []),
                ...$grants,
            ]));

            DB::table('admin_roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            ]);
        }

        $systemRoles = [
            [
                'name' => 'Gestor de almacenes',
                'slug' => 'warehouse-manager',
                'description' => 'Gestiona inventario, ubicaciones y transferencias en los almacenes que tenga asignados.',
                'permissions' => [
                    'panel.access', 'inventories.view', 'inventories.update',
                    'inventory_movements.view', 'inventory_location_balances.view', 'inventory_location_balances.update',
                    'warehouses.view', 'warehouse_locations.view', 'warehouse_locations.create', 'warehouse_locations.update',
                    'warehouse_transfers.view', 'warehouse_transfers.create', 'warehouse_transfers.update',
                    'warehouse_transfer_rules.view', 'pos_reports.view',
                ],
            ],
            [
                'name' => 'Cajero POS',
                'slug' => 'pos-cashier',
                'description' => 'Opera sesiones, ventas y devoluciones en los terminales y almacenes que tenga asignados.',
                'permissions' => [
                    'panel.access', 'pos_locations.view', 'pos_terminals.view',
                    'pos_cash_sessions.view', 'pos_cash_sessions.create', 'pos_cash_sessions.update',
                    'orders.view', 'orders.update', 'payments.view', 'pos_reports.view',
                ],
            ],
        ];

        foreach ($systemRoles as $role) {
            DB::table('admin_roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'permissions' => json_encode($role['permissions'], JSON_THROW_ON_ERROR),
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('admin_roles')->whereIn('slug', ['warehouse-manager', 'pos-cashier'])->delete();

        $removePermissions = [
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'warehouse_locations.view', 'warehouse_locations.create', 'warehouse_locations.update', 'warehouse_locations.delete',
            'warehouse_transfers.view', 'warehouse_transfers.create', 'warehouse_transfers.update', 'warehouse_transfers.delete',
            'warehouse_transfer_rules.view', 'warehouse_transfer_rules.create', 'warehouse_transfer_rules.update', 'warehouse_transfer_rules.delete',
            'pos_locations.view', 'pos_locations.create', 'pos_locations.update', 'pos_locations.delete',
            'pos_terminals.view', 'pos_terminals.create', 'pos_terminals.update', 'pos_terminals.delete',
            'pos_cash_sessions.view', 'pos_cash_sessions.create', 'pos_cash_sessions.update',
            'inventory_location_balances.view', 'inventory_location_balances.update', 'pos_reports.view',
        ];

        foreach (['admin', 'inventory-manager'] as $roleSlug) {
            $role = DB::table('admin_roles')->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $permissions = array_values(array_diff(json_decode($role->permissions, true) ?: [], $removePermissions));
            DB::table('admin_roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::dropIfExists('warehouse_user_accesses');
    }
};
