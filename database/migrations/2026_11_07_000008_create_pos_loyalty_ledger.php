<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_loyalty_settings', function (Blueprint $table): void {
            $table->id();
            $table->char('currency', 3)->unique();
            $table->unsignedBigInteger('earning_amount')->default(100);
            $table->unsignedInteger('points_per_earning_unit')->default(1);
            $table->unsignedSmallInteger('expiration_months')->default(12);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('pos_loyalty_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('points_balance')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'currency']);
            $table->index(['currency', 'points_balance']);
        });

        Schema::create('pos_loyalty_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('pos_loyalty_wallets')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->bigInteger('points_delta');
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'expires_at']);
            $table->index(['order_id', 'type']);
        });

        DB::table('pos_loyalty_settings')->insert([
            ['currency' => 'CUP', 'earning_amount' => 100, 'points_per_earning_unit' => 1, 'expiration_months' => 12, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['currency' => 'USD', 'earning_amount' => 100, 'points_per_earning_unit' => 1, 'expiration_months' => 12, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['currency' => 'EUR', 'earning_amount' => 100, 'points_per_earning_unit' => 1, 'expiration_months' => 12, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_loyalty_entries');
        Schema::dropIfExists('pos_loyalty_wallets');
        Schema::dropIfExists('pos_loyalty_settings');
    }
};
