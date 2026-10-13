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
        Schema::create('cuentas_corrientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->unique()->constrained('clientes')->restrictOnDelete();
            $table->boolean('admite_credito')->default(false);
            $table->decimal('limite_credito', 14, 2)->default(0);
            $table->boolean('bloqueada')->default(false);
            // Cached: sum(debe) - sum(haber). Positive = the customer owes; negative = credit in favour.
            $table->decimal('saldo', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cc_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas_corrientes')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 5);
            // Always positive; the type gives the direction.
            $table->decimal('importe', 14, 2);
            // Account balance right after this movement.
            $table->decimal('saldo', 14, 2);
            $table->string('concepto');
            $table->nullableMorphs('origen');
            // A movement can be reversed only once.
            $table->foreignId('reversa_de_id')->nullable()->unique()->constrained('cc_movimientos')->restrictOnDelete();
            $table->date('vencimiento')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('dispositivo', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sucursal_id', 'fecha']);
            $table->index(['cuenta_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cc_movimientos');
        Schema::dropIfExists('cuentas_corrientes');
    }
};
