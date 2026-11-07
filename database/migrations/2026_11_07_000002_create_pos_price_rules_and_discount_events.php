<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_price_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('scope', 24);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3)->nullable();
            $table->string('rule_type', 32);
            $table->unsignedBigInteger('value_amount');
            $table->integer('priority')->default(0);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'target_id', 'is_active']);
            $table->index(['warehouse_id', 'currency', 'is_active']);
            $table->index(['customer_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_amount')->default(0)->after('subtotal_amount');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_unit_price_amount')->nullable()->after('unit_price_amount');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('line_total_amount');
            $table->unsignedBigInteger('unit_cost_amount')->nullable()->after('discount_amount');
            $table->foreignId('pos_price_rule_id')->nullable()->after('unit_cost_amount')->constrained('pos_price_rules')->nullOnDelete();
        });

        Schema::create('pos_discount_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pos_price_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 24);
            $table->unsignedBigInteger('discount_amount');
            $table->json('rule_snapshot');
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        $admin = DB::table('admin_roles')->where('slug', 'admin')->first();
        if ($admin) {
            $permissions = json_decode($admin->permissions, true) ?: [];
            foreach ([
                'pos_price_rules.view', 'pos_price_rules.create', 'pos_price_rules.update', 'pos_price_rules.delete',
                'pos_discounts.approve', 'pos_discounts.override_floor',
            ] as $permission) {
                if (! in_array($permission, $permissions, true)) $permissions[] = $permission;
            }
            DB::table('admin_roles')->where('id', $admin->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_discount_events');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_price_rule_id');
            $table->dropColumn(['original_unit_price_amount', 'discount_amount', 'unit_cost_amount']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('pos_price_rules');
    }
};
