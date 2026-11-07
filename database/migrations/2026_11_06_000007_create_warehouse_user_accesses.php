<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_user_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['user_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_user_accesses');
    }
};
