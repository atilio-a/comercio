<?php

namespace App\Enums;

/**
 * Condición del cliente frente al IVA. Define el comprobante que corresponde emitirle.
 */
enum CondicionIva: string
{
    case ConsumidorFinal = 'consumidor_final';
    case ResponsableInscripto = 'responsable_inscripto';
    case Monotributo = 'monotributo';
    case Exento = 'exento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConsumidorFinal => 'Consumidor final',
            self::ResponsableInscripto => 'Responsable inscripto',
            self::Monotributo => 'Monotributo',
            self::Exento => 'Exento',
        };
    }

    /**
     * Todas las condiciones salvo consumidor final se identifican con CUIT.
     */
    public function exigeCuit(): bool
    {
        return $this !== self::ConsumidorFinal;
    }
}
