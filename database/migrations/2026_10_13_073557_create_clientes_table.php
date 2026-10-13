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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->index();
            // DNI (7-8 digits) or CUIT (11 digits), digits only; a unique index admits many NULLs.
            $table->string('dni_cuit', 11)->nullable()->unique();
            $table->string('telefono', 50)->nullable()->index();
            $table->string('email', 150)->nullable();
            // Same length as variantes.talle; hidden (not erased) when sizes are disabled.
            $table->string('talle_habitual', 10)->nullable();
            $table->string('condicion_iva', 25)->default('consumidor_final');
            $table->string('observaciones', 500)->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
