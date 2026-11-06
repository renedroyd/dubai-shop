<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('type', 40)->default('general');
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'type']);
        });

        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('type', 30)->default('area');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'parent_id', 'is_active']);
        });

        $now = now();

        DB::table('warehouses')->insert([
            'code' => 'MAIN',
            'name' => 'Almacén principal',
            'type' => 'general',
            'is_primary' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $warehouseId = (int) DB::table('warehouses')->where('code', 'MAIN')->value('id');

        DB::table('warehouse_locations')->insert([
            'warehouse_id' => $warehouseId,
            'parent_id' => null,
            'code' => 'UNASSIGNED',
            'name' => 'Sin ubicación asignada',
            'type' => 'unassigned',
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropUnique(['stock_key']);
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        DB::table('inventories')->update(['warehouse_id' => $warehouseId]);

        Schema::table('inventories', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable(false)->change();
            $table->unsignedBigInteger('quantity_blocked')->default(0)->after('quantity_reserved');
            $table->unique(['warehouse_id', 'stock_key'], 'inventories_warehouse_stock_key_unique');
        });
    }

    public function down(): void
    {
        $duplicateKeys = DB::table('inventories')
            ->select('stock_key')
            ->groupBy('stock_key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateKeys) {
            throw new RuntimeException(
                'No se puede revertir multi almacén: existen claves de inventario repetidas entre almacenes. Consolida las existencias antes de revertir.'
            );
        }

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropUnique('inventories_warehouse_stock_key_unique');
            $table->unique('stock_key');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('quantity_blocked');
        });

        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
    }
};
