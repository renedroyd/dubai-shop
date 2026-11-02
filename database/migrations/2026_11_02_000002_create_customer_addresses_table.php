<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->default('Casa');
            $table->string('recipient_name');
            $table->string('phone', 40);
            $table->string('address');
            $table->string('address_line_2')->nullable();
            $table->string('city', 120);
            $table->string('locality', 160)->nullable();
            $table->string('region', 120);
            $table->string('postal_code', 30)->nullable();
            $table->char('country', 2)->default('CU');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
