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
        Schema::create('planes_cuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medio_pago_id')->constrained('medios_pago')->restrictOnDelete();
            $table->unsignedTinyInteger('cuotas');
            $table->decimal('recargo_pct', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['medio_pago_id', 'cuotas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_cuota');
    }
};
