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
        Schema::create('caja_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('fecha');
            $table->timestamp('apertura')->useCurrent();
            $table->decimal('fondo_inicial', 14, 2)->default(0);
            $table->timestamp('cierre')->nullable();
            $table->string('estado', 10)->default('abierta');
            $table->string('justificacion', 500)->nullable();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamps();

            // A unique index admits many NULLs: only open sessions take part in these two.
            $table->unsignedBigInteger('caja_abierta_id')->nullable()->storedAs("IF(`estado` = 'abierta', `caja_id`, NULL)");
            $table->unsignedBigInteger('usuario_abierto_id')->nullable()->storedAs("IF(`estado` = 'abierta', `user_id`, NULL)");

            $table->unique('caja_abierta_id');
            $table->unique(['sucursal_id', 'usuario_abierto_id']);
            $table->index(['sucursal_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_sesiones');
    }
};
