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
        Schema::create('medios_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('tipo', 30);
            $table->boolean('impacta_caja')->default(false);
            $table->unsignedSmallInteger('dias_acreditacion')->default(0);
            $table->boolean('requiere_referencia')->default(false);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medios_pago');
    }
};
