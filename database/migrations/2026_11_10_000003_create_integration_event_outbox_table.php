<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_event_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 80);
            $table->string('aggregate_type', 80);
            $table->uuid('aggregate_external_id');
            $table->unsignedBigInteger('revision');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'available_at', 'locked_at'], 'integration_outbox_delivery_idx');
            $table->index(['aggregate_external_id', 'revision'], 'integration_outbox_aggregate_revision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_event_outbox');
    }
};
