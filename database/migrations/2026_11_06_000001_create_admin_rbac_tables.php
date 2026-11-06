<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('admin_access_enabled')->default(false)->after('is_admin');
        });

        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });

        Schema::create('admin_role_user', function (Blueprint $table): void {
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['admin_role_id', 'user_id']);
        });

        $catalog = ['panel.access'];
        foreach (['categories', 'brands', 'products'] as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $catalog[] = "{$resource}.{$action}";
            }
        }

        $orders = ['panel.access', 'orders.view', 'orders.update', 'payments.view', 'payments.update'];
        $inventory = ['panel.access', 'inventories.view', 'inventories.update', 'inventory_movements.view'];
        $content = ['panel.access'];
        foreach (['banners', 'pages', 'menu_items', 'store_settings'] as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $content[] = "{$resource}.{$action}";
            }
        }

        $roles = [
            ['name' => 'Superadministrador', 'slug' => 'superadmin', 'description' => 'Control total del panel y de los permisos.', 'permissions' => ['*'], 'is_system' => true],
            ['name' => 'Administrador', 'slug' => 'admin', 'description' => 'Gestión general de la tienda, sin administrar roles ni accesos.', 'permissions' => $this->allExceptAccessManagement(), 'is_system' => true],
            ['name' => 'Gestor de catálogo', 'slug' => 'catalog-manager', 'description' => 'Gestión de categorías, marcas y productos.', 'permissions' => $catalog, 'is_system' => true],
            ['name' => 'Gestor de pedidos', 'slug' => 'order-manager', 'description' => 'Consulta y gestión de pedidos y pagos.', 'permissions' => $orders, 'is_system' => true],
            ['name' => 'Gestor de inventario', 'slug' => 'inventory-manager', 'description' => 'Consulta y actualización de existencias.', 'permissions' => $inventory, 'is_system' => true],
            ['name' => 'Gestor de contenido', 'slug' => 'content-manager', 'description' => 'Gestión de banners, páginas, menús y contenido de tienda.', 'permissions' => $content, 'is_system' => true],
            ['name' => 'Atención al cliente', 'slug' => 'customer-support', 'description' => 'Consulta de pedidos y pagos para asistencia.', 'permissions' => ['panel.access', 'orders.view', 'payments.view'], 'is_system' => true],
        ];

        foreach ($roles as $role) {
            DB::table('admin_roles')->insert([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'permissions' => json_encode($role['permissions'], JSON_THROW_ON_ERROR),
                'is_system' => $role['is_system'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->where('is_admin', true)->update(['admin_access_enabled' => true]);

        $superadminId = DB::table('admin_roles')->where('slug', 'superadmin')->value('id');
        foreach (DB::table('users')->where('is_admin', true)->pluck('id') as $userId) {
            DB::table('admin_role_user')->insertOrIgnore([
                'admin_role_id' => $superadminId,
                'user_id' => $userId,
            ]);
        }
    }

    private function allExceptAccessManagement(): array
    {
        $permissions = ['panel.access', 'dashboard.view'];
        $resources = [
            'categories', 'brands', 'products', 'inventories', 'inventory_movements',
            'orders', 'payments', 'payment_methods', 'shipping_zones',
            'notification_templates', 'notification_logs', 'banners', 'pages',
            'menu_items', 'store_settings',
        ];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_user_permissions');
        Schema::dropIfExists('admin_roles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_access_enabled');
        });
    }
};
