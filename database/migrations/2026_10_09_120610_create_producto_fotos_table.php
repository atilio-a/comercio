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
        Schema::create('producto_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colores')->nullOnDelete();
            $table->string('ruta');
            $table->string('ruta_miniatura');
            $table->boolean('es_principal')->default(false);
            $table->unsignedSmallInteger('orden');
            $table->unsignedSmallInteger('ancho');
            $table->unsignedSmallInteger('alto');
            $table->unsignedInteger('bytes');
            $table->timestamps();

            $table->index(['producto_id', 'orden']);
            $table->index(['producto_id', 'color_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_fotos');
    }
};
