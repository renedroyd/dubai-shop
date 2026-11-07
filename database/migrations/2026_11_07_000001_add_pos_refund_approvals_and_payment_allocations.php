<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_refunds', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->after('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::create('pos_refund_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_refund_id')->constrained('pos_refunds')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('driver', 80);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('reference', 120)->nullable();
            $table->timestamps();
            $table->unique(['pos_refund_id', 'payment_id']);
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_refund_payments');
        Schema::table('pos_refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
