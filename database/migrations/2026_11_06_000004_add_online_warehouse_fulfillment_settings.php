<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->boolean('online_fulfillment_enabled')->default(false)->after('is_primary');
            $table->unsignedInteger('online_fulfillment_priority')->default(100)->after('online_fulfillment_enabled');
        });

        DB::table('warehouses')->where('is_primary', true)->update([
            'online_fulfillment_enabled' => true,
            'online_fulfillment_priority' => 10,
        ]);
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropColumn(['online_fulfillment_enabled', 'online_fulfillment_priority']);
        });
    }
};
