<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_partner_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_partner_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unique(['business_partner_id', 'role']);
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_partner_roles');
        Schema::dropIfExists('business_partners');
    }
};
