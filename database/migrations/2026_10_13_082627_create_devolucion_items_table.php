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
        Schema::create('devolucion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->restrictOnDelete();
            $table->foreignId('venta_item_id')->constrained('venta_items')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            // Share of what was actually charged for the sale item, and its breakdown.
            $table->decimal('importe', 14, 2);
            $table->decimal('neto', 14, 2);
            $table->decimal('iva', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('venta_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devolucion_items');
    }
};
