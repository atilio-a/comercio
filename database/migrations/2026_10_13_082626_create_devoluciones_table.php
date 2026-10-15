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
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            // Customer credited with the balance; the sale's one or, for walk-in sales, the one chosen on return.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->date('fecha');
            $table->string('motivo', 200);
            $table->string('resolucion', 30);
            // Returned amount (final with VAT) and its breakdown: neto + iva = importe.
            $table->decimal('importe', 14, 2);
            $table->decimal('neto', 14, 2);
            $table->decimal('iva', 14, 2);
            // Only for cash refunds: payment method and cash session of the outflow.
            $table->foreignId('medio_pago_id')->nullable()->constrained('medios_pago')->restrictOnDelete();
            $table->foreignId('caja_sesion_id')->nullable()->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sucursal_id', 'fecha']);
            $table->index('venta_id');
            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
