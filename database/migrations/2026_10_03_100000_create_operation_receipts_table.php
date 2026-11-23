<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('operation_key');
            $table->char('request_hash', 64);
            $table->text('location');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_receipts');
    }
};
