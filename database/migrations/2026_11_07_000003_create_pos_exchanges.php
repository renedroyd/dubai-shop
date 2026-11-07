<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_exchanges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('original_order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('replacement_order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('pos_refund_id')->unique()->constrained('pos_refunds')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('returned_amount');
            $table->unsignedBigInteger('replacement_amount');
            $table->bigInteger('difference_amount');
            $table->char('currency', 3);
            $table->text('reason');
            $table->timestamp('exchanged_at');
            $table->timestamps();
            $table->index(['original_order_id', 'exchanged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_exchanges');
    }
};
