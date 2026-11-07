<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->string('printer_name', 255)->nullable();
            $table->boolean('cash_drawer_enabled')->default(false);
            $table->unsignedTinyInteger('cash_drawer_pin')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->dropColumn(['printer_name', 'cash_drawer_enabled', 'cash_drawer_pin']);
        });
    }
};
