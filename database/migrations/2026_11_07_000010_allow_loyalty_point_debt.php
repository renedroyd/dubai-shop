<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_loyalty_wallets', function (Blueprint $table): void {
            $table->bigInteger('points_balance')->default(0)->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('pos_loyalty_wallets')->where('points_balance', '<', 0)->exists()) {
            throw new RuntimeException('Cannot restore an unsigned loyalty balance while customer wallets contain point debt.');
        }

        Schema::table('pos_loyalty_wallets', function (Blueprint $table): void {
            $table->unsignedBigInteger('points_balance')->default(0)->change();
        });
    }
};
