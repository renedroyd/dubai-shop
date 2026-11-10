<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_event_outbox', function (Blueprint $table): void {
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->index(['failed_at', 'available_at'], 'integration_outbox_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('integration_event_outbox', function (Blueprint $table): void {
            $table->dropIndex('integration_outbox_retry_idx');
            $table->dropColumn('failed_at');
        });
    }
};
