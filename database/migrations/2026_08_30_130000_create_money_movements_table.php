<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_movements', function (Blueprint $table): void {
            $table->id();
            $table->date('movement_date')->index();
            $table->string('movement_type', 30)->index();
            $table->foreignId('counterparty_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->foreignId('saraf_id')->nullable()->constrained('business_partners')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->index();
            $table->decimal('exchange_rate_to_usd', 18, 6)->nullable();
            $table->decimal('amount_usd', 18, 2);
            $table->string('reference', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['counterparty_id', 'movement_date']);
            $table->index(['saraf_id', 'movement_date']);
            $table->index(['deal_id', 'status']);
            $table->index(['financial_account_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movements');
    }
};
