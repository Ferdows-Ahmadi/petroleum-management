<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('phone', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('transaction_date')->index();
            $table->string('transaction_type', 30)->index();
            $table->string('adjustment_direction', 20)->nullable();
            $table->decimal('amount_usd', 18, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_partner_id')->constrained()->restrictOnDelete();
            $table->string('contract_number', 100)->unique();
            $table->string('contract_type', 20)->index();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->decimal('agreed_amount', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('terms');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('capital_transactions');
        Schema::dropIfExists('capital_partners');
    }
};
