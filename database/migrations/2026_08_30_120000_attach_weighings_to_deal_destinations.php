<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->foreignId('deal_destination_id')->nullable()->after('sale_item_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deal_destination_id');
            $table->foreignId('sale_item_id')->nullable(false)->change();
        });
    }
};
