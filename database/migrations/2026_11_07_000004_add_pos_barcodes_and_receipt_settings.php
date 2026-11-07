<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('barcode', 100)->nullable()->unique();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('barcode', 100)->nullable()->unique();
        });

        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->string('receipt_width', 3)->default('80');
            $table->unsignedTinyInteger('receipt_copies')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->dropColumn(['receipt_width', 'receipt_copies']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
