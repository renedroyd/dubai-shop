<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('driver', 80);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->char('public_token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 40);
            $table->string('shipping_address');
            $table->string('shipping_address_line_2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_region')->nullable();
            $table->string('shipping_postal_code', 30)->nullable();
            $table->string('shipping_country', 2);
            $table->text('customer_note')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 40)->default('pending');
            $table->string('payment_status', 40)->default('awaiting_payment');
            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'payment_status']);
            $table->index(['customer_email', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount');
            $table->unsignedBigInteger('line_total_amount');
            $table->char('currency', 3);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver', 80);
            $table->string('status', 40)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->text('customer_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['driver', 'status']);
        });

        DB::table('payment_methods')->insert([
            ['name' => 'Zelle', 'code' => 'zelle', 'driver' => 'zelle_manual', 'description' => 'Transferencia Zelle con revisión manual.', 'instructions' => 'Realiza la transferencia y adjunta el comprobante para que podamos verificarla.', 'settings' => json_encode([]), 'is_enabled' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Efectivo contra entrega', 'code' => 'cash-on-delivery', 'driver' => 'cash_on_delivery', 'description' => 'Paga en efectivo al recibir tu pedido.', 'instructions' => 'El pago se realizará al momento de la entrega.', 'settings' => json_encode([]), 'is_enabled' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('payment_methods');
    }
};
