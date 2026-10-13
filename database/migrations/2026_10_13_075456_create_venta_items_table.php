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
        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            // Unit list price with VAT and its net, at the moment of the sale.
            $table->decimal('precio_final', 14, 2);
            $table->decimal('precio_neto', 14, 2);
            $table->decimal('alicuota', 5, 2);
            // Line amounts: own discount, share of the general discount and of the instalment surcharge.
            $table->decimal('descuento', 14, 2)->default(0);
            $table->foreignId('descuento_autorizado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('descuento_venta', 14, 2)->default(0);
            $table->decimal('recargo', 14, 2)->default(0);
            // Taxable line total (final with VAT) and its breakdown: neto + iva = total.
            $table->decimal('total', 14, 2);
            $table->decimal('neto', 14, 2);
            $table->decimal('iva', 14, 2);
            // Promotions arrive in phase 2: no foreign key yet.
            $table->unsignedBigInteger('promocion_id')->nullable();
            $table->decimal('costo_unitario', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('variante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_items');
    }
};
