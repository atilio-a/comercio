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
        Schema::create('caja_sesion_arqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_sesion_id')->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('medio_pago_id')->constrained('medios_pago')->restrictOnDelete();
            $table->decimal('esperado', 14, 2);
            $table->decimal('contado', 14, 2)->nullable();
            $table->decimal('diferencia', 14, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['caja_sesion_id', 'medio_pago_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_sesion_arqueos');
    }
};
