<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->unsignedInteger('minimum_stock_quantity')->nullable()->after('quantity_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn('minimum_stock_quantity');
        });
    }
};
