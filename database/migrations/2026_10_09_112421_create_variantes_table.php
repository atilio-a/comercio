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
        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('color_id')->constrained('colores')->restrictOnDelete();
            $table->string('talle', 10)->nullable();
            // A unique index allows several NULLs, so "no size" is compared as an empty string.
            $table->string('talle_clave', 10)->storedAs("COALESCE(`talle`, '')");
            $table->string('sku', 50)->unique();
            $table->string('codigo_barras', 50)->unique();
            $table->decimal('costo', 14, 2);
            $table->decimal('precio_final', 14, 2);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();

            $table->unique(['producto_id', 'color_id', 'talle_clave']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variantes');
    }
};
