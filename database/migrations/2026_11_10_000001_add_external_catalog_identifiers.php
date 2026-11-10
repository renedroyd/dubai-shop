<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants', 'warehouses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('external_id')->nullable();
            });

            DB::table($tableName)
                ->whereNull('external_id')
                ->orderBy('id')
                ->chunkById(500, function ($records) use ($tableName): void {
                    foreach ($records as $record) {
                        DB::table($tableName)->where('id', $record->id)->update([
                            'external_id' => (string) Str::uuid(),
                        ]);
                    }
                });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('external_id');
            });
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('external_revision')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('external_revision');
        });

        foreach (['warehouses', 'product_variants', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique($tableName.'_external_id_unique');
                $table->dropColumn('external_id');
            });
        }
    }
};
