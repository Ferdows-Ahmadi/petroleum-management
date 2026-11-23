<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->renameColumn('quantity_kg', 'final_quantity_kg');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('final_quantity_kg', 18, 3)->nullable()->change();
        });

        DB::table('sale_items')
            ->whereIn('sale_id', DB::table('sales')->select('id')->where('status', 'DRAFT'))
            ->update(['final_quantity_kg' => null, 'amount' => '0.00']);

        Schema::create('weighing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->unique()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('ticket_number', 100)->nullable()->index();
            $table->string('station_name', 150)->nullable();
            $table->dateTime('weighing_at')->index();
            $table->decimal('first_weight_kg', 18, 3);
            $table->decimal('second_weight_kg', 18, 3);
            $table->decimal('net_weight_kg', 18, 3);
            $table->string('vehicle_number', 100)->nullable()->index();
            $table->string('driver_name', 150)->nullable();
            $table->string('ticket_image_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weighing_records');

        DB::table('sale_items')
            ->whereNull('final_quantity_kg')
            ->update(['final_quantity_kg' => DB::raw('COALESCE(requested_quantity_kg, 0)')]);

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('final_quantity_kg', 18, 3)->nullable(false)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->renameColumn('final_quantity_kg', 'quantity_kg');
        });
    }
};
