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
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('orden_compra_id')->nullable()->constrained('ordenes_compra')->restrictOnDelete();
            $table->date('fecha');
            $table->string('referencia', 100)->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motivo_anulacion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sucursal_id', 'fecha']);
        });

        Schema::create('recepcion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->foreignId('orden_compra_item_id')->nullable()->constrained('orden_compra_items')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('costo', 14, 2);
            $table->boolean('actualiza_costo')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['recepcion_id', 'variante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepcion_items');
        Schema::dropIfExists('recepciones');
    }
};
