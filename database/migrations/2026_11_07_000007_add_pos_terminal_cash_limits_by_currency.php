<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->json('cash_limits')->nullable()->after('cash_limit_amount');
        });

        DB::table('pos_terminals')
            ->whereNotNull('cash_limit_amount')
            ->orderBy('id')
            ->chunkById(200, function ($terminals): void {
                foreach ($terminals as $terminal) {
                    DB::table('pos_terminals')
                        ->where('id', $terminal->id)
                        ->update(['cash_limits' => json_encode(['CUP' => (int) $terminal->cash_limit_amount])]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->dropColumn('cash_limits');
        });
    }
};
