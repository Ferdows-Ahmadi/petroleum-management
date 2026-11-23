<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_locks', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
        });
        DB::table('operation_locks')->insert(['name' => 'inline-party']);
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_locks');
    }
};
