<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_sources', function (Blueprint $table): void {
            $table->string('source_type', 20)->default('SUPPLIER')->index()->after('deal_id');
            $table->foreignId('warehouse_id')->nullable()->after('supplier_id')->constrained()->restrictOnDelete();
        });

        Schema::table('deal_destinations', function (Blueprint $table): void {
            $table->string('destination_type', 20)->default('CUSTOMER')->index()->after('deal_id');
            $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
        });

        Schema::create('deal_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('deal_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_destination_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_kg', 18, 3);
            $table->decimal('allocated_cost_usd', 18, 2);
            $table->string('allocation_method', 20)->default('MANUAL')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['deal_source_id', 'deal_destination_id'], 'deal_allocation_source_destination_unique');
            $table->index(['deal_id', 'deal_destination_id']);
        });

        Schema::create('deal_quantity_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('deal_source_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('adjustment_type', 20)->index();
            $table->decimal('quantity_kg', 18, 3);
            $table->decimal('cost_amount_usd', 18, 2)->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('category', 30)->index();
            $table->date('expense_date')->index();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('USD')->index();
            $table->decimal('exchange_rate_to_usd', 18, 6)->nullable();
            $table->decimal('amount_usd', 18, 2);
            $table->foreignId('payee_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->decimal('source_quantity_kg', 18, 3);
            $table->decimal('customer_quantity_kg', 18, 3);
            $table->decimal('storage_in_quantity_kg', 18, 3);
            $table->decimal('storage_out_quantity_kg', 18, 3);
            $table->decimal('shortage_quantity_kg', 18, 3);
            $table->decimal('excess_quantity_kg', 18, 3);
            $table->decimal('unexplained_quantity_kg', 18, 3);
            $table->decimal('source_value_usd', 18, 2);
            $table->decimal('sale_revenue_usd', 18, 2);
            $table->decimal('direct_expenses_usd', 18, 2);
            $table->decimal('shortage_cost_usd', 18, 2);
            $table->decimal('final_result_usd', 18, 2);
            $table->json('snapshot');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['deal_id', 'revision']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('deal_source_id')->nullable()->unique()->after('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_destination_id')->nullable()->unique()->after('deal_source_id')->constrained()->restrictOnDelete();
            $table->decimal('cost_amount_usd', 18, 2)->nullable()->after('quantity_kg');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deal_destination_id');
            $table->dropConstrainedForeignId('deal_source_id');
            $table->dropColumn('cost_amount_usd');
        });
        Schema::dropIfExists('deal_settlements');
        Schema::dropIfExists('deal_expenses');
        Schema::dropIfExists('deal_quantity_adjustments');
        Schema::dropIfExists('deal_allocations');
        Schema::table('deal_destinations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('destination_type');
        });
        Schema::table('deal_sources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('source_type');
        });
    }
};
