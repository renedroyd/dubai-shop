<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_terminal_id')->constrained('pos_terminals')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->char('currency', 3);
            $table->unsignedBigInteger('opening_cash_amount')->default(0);
            $table->unsignedBigInteger('expected_cash_amount')->nullable();
            $table->unsignedBigInteger('counted_cash_amount')->nullable();
            $table->bigInteger('difference_amount')->nullable();
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['pos_terminal_id', 'status']);
            $table->index(['status', 'opened_at']);
        });

        Schema::create('pos_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_cash_session_id')->constrained('pos_cash_sessions')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['pos_cash_session_id', 'type']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('channel', 20)->default('web')->after('number');
            $table->foreignId('pos_terminal_id')->nullable()->after('channel')->constrained('pos_terminals')->restrictOnDelete();
            $table->foreignId('pos_warehouse_id')->nullable()->after('pos_terminal_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('pos_cash_session_id')->nullable()->after('pos_terminal_id')->constrained('pos_cash_sessions')->restrictOnDelete();
            $table->foreignId('cashier_id')->nullable()->after('pos_cash_session_id')->constrained('users')->nullOnDelete();
            $table->index(['channel', 'placed_at']);
            $table->index(['pos_terminal_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['channel', 'placed_at']);
            $table->dropIndex(['pos_terminal_id', 'placed_at']);
            $table->dropConstrainedForeignId('cashier_id');
            $table->dropConstrainedForeignId('pos_cash_session_id');
            $table->dropConstrainedForeignId('pos_warehouse_id');
            $table->dropConstrainedForeignId('pos_terminal_id');
            $table->dropColumn('channel');
        });

        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_cash_sessions');
    }
};