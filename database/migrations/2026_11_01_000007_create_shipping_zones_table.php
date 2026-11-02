<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('province', 100)->index();
            $table->string('municipality', 120)->nullable();
            $table->string('locality', 160)->nullable();
            $table->decimal('shipping_amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['province', 'municipality', 'locality', 'is_active'], 'shipping_zones_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
