<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfer_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->unsignedBigInteger('min_quantity')->nullable();
            $table->unsignedBigInteger('max_quantity')->nullable();
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->boolean('auto_approve')->default(false);
            $table->boolean('requester_can_approve')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        Schema::create('warehouse_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending_approval');
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'requested_at']);
            $table->index(['source_warehouse_id', 'destination_warehouse_id', 'status']);
        });

        Schema::create('warehouse_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('source_inventory_id')->constrained('inventories')->restrictOnDelete();
            $table->foreignId('destination_inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->foreignId('source_location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->unsignedBigInteger('quantity_requested');
            $table->unsignedBigInteger('quantity_dispatched')->default(0);
            $table->unsignedBigInteger('quantity_received')->default(0);
            $table->unsignedBigInteger('quantity_damaged')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['source_inventory_id', 'destination_inventory_id']);
        });

        Schema::create('warehouse_transfer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 20);
            $table->text('comments')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['warehouse_transfer_id', 'user_id'], 'warehouse_transfer_approval_user_unique');
        });

        Schema::create('warehouse_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['warehouse_transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_events');
        Schema::dropIfExists('warehouse_transfer_approvals');
        Schema::dropIfExists('warehouse_transfer_items');
        Schema::dropIfExists('warehouse_transfers');
        Schema::dropIfExists('warehouse_transfer_rules');
    }
};
