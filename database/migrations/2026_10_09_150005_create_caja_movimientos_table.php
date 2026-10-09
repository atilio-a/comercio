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
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_sesion_id')->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20);
            $table->foreignId('medio_pago_id')->constrained('medios_pago')->restrictOnDelete();
            $table->decimal('importe', 14, 2);
            $table->string('motivo')->nullable();
            $table->string('referencia', 100)->nullable();
            $table->nullableMorphs('origen');
            // A movement can be reversed only once.
            $table->foreignId('reversa_de_id')->nullable()->unique()->constrained('caja_movimientos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sucursal_id', 'fecha']);
            $table->index(['caja_sesion_id', 'medio_pago_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};
