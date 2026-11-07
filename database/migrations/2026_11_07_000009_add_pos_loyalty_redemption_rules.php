<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_loyalty_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('redemption_amount')->default(1)->after('points_per_earning_unit');
            $table->unsignedInteger('minimum_redemption_points')->default(100)->after('redemption_amount');
            $table->unsignedSmallInteger('maximum_redemption_basis_points')->default(2000)->after('minimum_redemption_points');
        });

        DB::table('pos_loyalty_settings')->where('currency', 'CUP')->update([
            'earning_amount' => 10000,
            'redemption_amount' => 100,
            'updated_at' => now(),
        ]);

        DB::table('pos_loyalty_settings')->whereIn('currency', ['USD', 'EUR'])->update([
            'earning_amount' => 100,
            'redemption_amount' => 1,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('pos_loyalty_settings', function (Blueprint $table): void {
            $table->dropColumn(['redemption_amount', 'minimum_redemption_points', 'maximum_redemption_basis_points']);
        });
    }
};
