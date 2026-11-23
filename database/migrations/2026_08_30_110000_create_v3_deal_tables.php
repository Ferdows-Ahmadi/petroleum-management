<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 100)->nullable()->unique();
            $table->date('deal_date')->index();
            $table->date('requested_delivery_date')->nullable()->index();
            $table->decimal('requested_quantity_kg', 18, 3)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deal_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->decimal('quantity_kg', 18, 3)->nullable();
            $table->decimal('rate_per_ton', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('USD')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['deal_id', 'supplier_id']);
        });

        Schema::create('deal_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->decimal('requested_quantity_kg', 18, 3)->nullable();
            $table->decimal('final_quantity_kg', 18, 3)->nullable();
            $table->decimal('rate_per_ton', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 3)->default('USD')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['deal_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_destinations');
        Schema::dropIfExists('deal_sources');
        Schema::dropIfExists('deals');
    }
};
