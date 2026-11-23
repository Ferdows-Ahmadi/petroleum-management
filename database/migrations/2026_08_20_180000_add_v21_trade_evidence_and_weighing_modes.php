<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('ticket_image_path', 500)->nullable()->after('notes');
        });

        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->string('weighing_mode', 20)->default('TWO_STAGE')->after('weighing_at')->index();
            $table->decimal('first_weight_kg', 18, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('weighing_records', function (Blueprint $table): void {
            $table->dropIndex(['weighing_mode']);
            $table->dropColumn('weighing_mode');
            $table->decimal('first_weight_kg', 18, 3)->nullable(false)->change();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('ticket_image_path');
        });
    }
};
