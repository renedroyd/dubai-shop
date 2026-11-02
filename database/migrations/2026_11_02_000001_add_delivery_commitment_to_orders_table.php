<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('delivery_commitment_hours')->default(24);
            $table->timestamp('delivery_due_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['delivery_due_at']);
            $table->dropColumn(['delivery_commitment_hours', 'delivery_due_at']);
        });
    }
};
