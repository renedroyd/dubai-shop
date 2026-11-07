<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_refund_items', function (Blueprint $table): void {
            $table->string('disposition', 30)->default('resellable')->after('amount');
            $table->text('condition_note')->nullable()->after('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('pos_refund_items', function (Blueprint $table): void {
            $table->dropColumn(['disposition', 'condition_note']);
        });
    }
};
