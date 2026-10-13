<?php

namespace App\Enums;

/**
 * Estados de una orden de compra (COM-01).
 */
enum EstadoOrdenCompra: string
{
    case Borrador = 'borrador';
    case Emitida = 'emitida';
    case Parcial = 'parcial';
    case Recibida = 'recibida';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Emitida => 'Emitida',
            self::Parcial => 'Recibida en parte',
            self::Recibida => 'Recibida',
            self::Anulada => 'Anulada',
        };
    }

    /**
     * Solo un borrador se edita.
     */
    public function editable(): bool
    {
        return $this === self::Borrador;
    }

    /**
     * Admite recepciones: emitida o recibida en parte.
     */
    public function recibible(): bool
    {
        return $this === self::Emitida || $this === self::Parcial;
    }

    /**
     * Se anula solo si no tiene mercadería recibida.
     */
    public function anulable(): bool
    {
        return $this === self::Borrador || $this === self::Emitida;
    }

    /**
     * Clases Tailwind de la etiqueta de estado.
     */
    public function clases(): string
    {
        return match ($this) {
            self::Borrador => 'bg-gray-100 text-gray-800',
            self::Emitida => 'bg-blue-100 text-blue-800',
            self::Parcial => 'bg-amber-100 text-amber-800',
            self::Recibida => 'bg-green-100 text-green-800',
            self::Anulada => 'bg-red-100 text-red-800',
        };
    }
}
