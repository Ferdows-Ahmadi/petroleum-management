<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('account_type', 20)->index();
            $table->string('currency', 3)->index();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->date('opening_balance_date')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_partner_id')->unique()->constrained()->restrictOnDelete();
            $table->date('effective_date')->index();
            $table->string('balance_type', 20)->index();
            $table->decimal('amount_usd', 18, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('transaction_date')->index();
            $table->string('transaction_type', 30)->index();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->index();
            $table->decimal('exchange_rate_to_usd', 18, 6)->nullable();
            $table->decimal('amount_usd', 18, 2);
            $table->string('expense_category', 30)->nullable()->index();
            $table->string('payee', 150)->nullable();
            $table->string('reference', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('partner_opening_balances');
        Schema::dropIfExists('financial_accounts');
    }
};
