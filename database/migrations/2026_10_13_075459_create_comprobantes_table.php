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
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            // The returns table arrives with the returns change, which adds the foreign key.
            $table->unsignedBigInteger('devolucion_id')->nullable()->index();
            $table->string('tipo', 30);
            $table->foreignId('punto_venta_id')->constrained('puntos_venta')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->string('cae', 20)->nullable();
            $table->date('cae_vto')->nullable();
            $table->string('emisor', 20);
            // An internal cancellation (or a future credit note) references the voided document once.
            $table->foreignId('comprobante_anulado_id')->nullable()->unique()->constrained('comprobantes')->restrictOnDelete();
            // Immutable snapshot of the issued document: printing reads only this.
            $table->json('datos_json');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tipo', 'punto_venta_id', 'numero']);
            $table->index(['sucursal_id', 'fecha']);
            $table->index('venta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
