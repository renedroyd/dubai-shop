<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key', 80);
            $table->string('recipient', 255);
            $table->string('subject')->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'event_key', 'recipient'], 'notification_log_event_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
