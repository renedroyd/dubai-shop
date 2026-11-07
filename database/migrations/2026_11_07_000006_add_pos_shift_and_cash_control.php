<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->json('assigned_cashier_ids')->nullable();
            $table->unsignedBigInteger('cash_limit_amount')->nullable();
            $table->boolean('supervised_closing')->default(false);
        });

        Schema::table('pos_cash_sessions', function (Blueprint $table): void {
            $table->string('shift_label', 120)->nullable();
            $table->foreignId('close_requested_by')->nullable()->after('closed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('close_requested_at')->nullable();
            $table->unsignedBigInteger('close_counted_cash_amount')->nullable();
            $table->text('close_request_note')->nullable();
            $table->foreignId('close_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('close_approved_at')->nullable();
            $table->index(['status', 'close_requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_cash_sessions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'close_requested_at']);
            $table->dropConstrainedForeignId('close_approved_by');
            $table->dropColumn(['close_approved_at', 'close_request_note', 'close_counted_cash_amount', 'close_requested_at']);
            $table->dropConstrainedForeignId('close_requested_by');
            $table->dropColumn('shift_label');
        });

        Schema::table('pos_terminals', function (Blueprint $table): void {
            $table->dropColumn(['assigned_cashier_ids', 'cash_limit_amount', 'supervised_closing']);
        });
    }
};
