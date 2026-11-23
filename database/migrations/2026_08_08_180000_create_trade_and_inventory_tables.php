<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_partner_id')->constrained()->restrictOnDelete();
            $table->date('purchase_date')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('currency', 3)->default('USD')->index();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_kg', 18, 3);
            $table->decimal('rate_per_ton', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_partner_id')->constrained()->restrictOnDelete();
            $table->date('sale_date')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('currency', 3)->default('USD')->index();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity_kg', 18, 3)->nullable();
            $table->decimal('quantity_kg', 18, 3);
            $table->decimal('rate_per_ton', 18, 4);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 30)->index();
            $table->decimal('quantity_kg', 18, 3);
            $table->timestamp('occurred_at')->index();
            $table->foreignId('purchase_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
