<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_locality', 160)->nullable()->after('shipping_city');
            $table->foreignId('shipping_zone_id')->nullable()->after('shipping_country')->constrained('shipping_zones')->nullOnDelete();
            $table->string('shipping_zone_label')->nullable()->after('shipping_zone_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_zone_id');
            $table->dropColumn(['shipping_locality', 'shipping_zone_label']);
        });
    }
};
