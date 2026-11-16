<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_installation_identity', function (Blueprint $table): void {
            $table->string('pairing_status', 20)->default('paired');
            $table->string('webhook_key_id', 80)->nullable();
            $table->text('webhook_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('integration_installation_identity', function (Blueprint $table): void {
            $table->dropColumn(['pairing_status', 'webhook_key_id', 'webhook_secret']);
        });
    }
};
