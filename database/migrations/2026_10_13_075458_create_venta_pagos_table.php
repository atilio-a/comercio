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
        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('medio_pago_id')->constrained('medios_pago')->restrictOnDelete();
            $table->foreignId('plan_cuota_id')->nullable()->constrained('planes_cuota')->restrictOnDelete();
            // Snapshot of the instalment plan.
            $table->unsignedTinyInteger('cuotas')->nullable();
            $table->decimal('recargo_pct', 5, 2)->default(0);
            // Part of the sale covered by this payment; the medium charges importe + recargo.
            $table->decimal('importe', 14, 2);
            $table->decimal('recargo', 14, 2)->default(0);
            $table->string('referencia', 100)->nullable();
            $table->string('lote', 20)->nullable();
            $table->string('cupon', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
    }
};
