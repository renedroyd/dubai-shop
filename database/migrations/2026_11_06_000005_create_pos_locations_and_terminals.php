<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('pos_terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_location_id')->constrained('pos_locations')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['pos_location_id', 'code']);
            $table->index(['warehouse_id', 'is_active']);
            $table->index(['pos_location_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('pos_locations');
    }
};
