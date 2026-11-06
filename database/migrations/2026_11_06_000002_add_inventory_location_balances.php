<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_location_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->unsignedBigInteger('quantity_on_hand')->default(0);
            $table->timestamps();
            $table->unique(['inventory_id', 'warehouse_location_id'], 'inventory_location_balance_unique');
            $table->index(['warehouse_location_id', 'quantity_on_hand']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('from_location_id')->nullable()->after('inventory_id')
                ->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')
                ->constrained('warehouse_locations')->nullOnDelete();
        });

        $now = now();

        DB::table('warehouses')->orderBy('id')->get()->each(function ($warehouse) use ($now): void {
            DB::table('warehouse_locations')->insertOrIgnore([
                'warehouse_id' => $warehouse->id,
                'parent_id' => null,
                'code' => 'UNASSIGNED',
                'name' => 'Sin ubicación asignada',
                'type' => 'unassigned',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $unassignedLocations = DB::table('warehouse_locations')
            ->where('type', 'unassigned')
            ->pluck('id', 'warehouse_id');

        DB::table('inventories')->orderBy('id')->chunk(500, function ($inventories) use ($unassignedLocations): void {
            foreach ($inventories as $inventory) {
                $locationId = $unassignedLocations[$inventory->warehouse_id] ?? null;

                if (! $locationId) {
                    continue;
                }

                DB::table('inventory_location_balances')->insert([
                    'inventory_id' => $inventory->id,
                    'warehouse_location_id' => $locationId,
                    'quantity_on_hand' => $inventory->quantity_on_hand,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
        });

        Schema::dropIfExists('inventory_location_balances');
    }
};
