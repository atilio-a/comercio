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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('vendedor_id')->constrained('vendedores')->restrictOnDelete();
            // Null when no payment goes through the cash register (only customer account / credit).
            $table->foreignId('caja_sesion_id')->nullable()->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('punto_venta_id')->constrained('puntos_venta')->restrictOnDelete();
            $table->string('estado', 10);
            $table->decimal('subtotal_neto', 14, 2);
            $table->decimal('iva', 14, 2);
            // Item discounts plus the general discount.
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('recargo', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignId('descuento_autorizado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('anulada_at')->nullable();
            $table->string('motivo_anulacion', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha']);
            $table->index(['sucursal_id', 'estado']);
            $table->index('vendedor_id');
            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
