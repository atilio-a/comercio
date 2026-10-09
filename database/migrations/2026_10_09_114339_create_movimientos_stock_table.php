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
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('variantes')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20);
            $table->integer('cantidad');
            $table->integer('saldo');
            $table->string('motivo')->nullable();
            $table->nullableMorphs('origen');
            $table->foreignId('reversa_de_id')->nullable()->constrained('movimientos_stock')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sucursal_id', 'fecha']);
            $table->index(['variante_id', 'sucursal_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
