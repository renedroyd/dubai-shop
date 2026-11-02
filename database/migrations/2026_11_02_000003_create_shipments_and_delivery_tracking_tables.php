<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tracking_code', 40)->unique();
            $table->string('status', 40)->default('assigned');
            $table->string('courier_name', 160)->nullable();
            $table->string('courier_phone', 40)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('recipient_name', 160)->nullable();
            $table->text('delivery_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'dispatched_at']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('result', 30);
            $table->string('recipient_name', 160)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at');
            $table->timestamps();
            $table->unique(['shipment_id', 'attempt_number']);
            $table->index(['result', 'attempted_at']);
        });

        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 60);
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('shipments');
    }
};
