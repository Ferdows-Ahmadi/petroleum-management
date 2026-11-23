<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->decimal('second_weight_kg', 18, 3)->nullable()->change();
            $table->decimal('net_weight_kg', 18, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('weighing_records')
            ->whereNull('second_weight_kg')
            ->orWhereNull('net_weight_kg')
            ->exists()) {
            throw new RuntimeException('Cannot restore required weighing columns while pending weighing records exist.');
        }

        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->decimal('second_weight_kg', 18, 3)->nullable(false)->change();
            $table->decimal('net_weight_kg', 18, 3)->nullable(false)->change();
        });
    }
};
