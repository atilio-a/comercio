<?php

namespace App\Enums;

/**
 * Parámetros del sistema (RNF-06). Todos son de tipo sí/no.
 */
enum Parametro: string
{
    case MultiSucursal = 'multi_sucursal';
    case StockNegativo = 'stock_negativo';
    case FacturacionElectronica = 'facturacion_electronica';
    case UsarTalles = 'usar_talles';

    /**
     * Valor con el que se crea el parámetro en la instalación.
     */
    public function valorPorDefecto(): bool
    {
        return match ($this) {
            self::UsarTalles => true,
            self::MultiSucursal, self::StockNegativo, self::FacturacionElectronica => false,
        };
    }

    /**
     * Nombre visible en la pantalla de parametrización.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::MultiSucursal => 'Multi-sucursal',
            self::StockNegativo => 'Permitir venta con stock negativo',
            self::FacturacionElectronica => 'Facturación electrónica',
            self::UsarTalles => 'Usar talles en productos',
        };
    }

    /**
     * Explicación del efecto del parámetro.
     */
    public function ayuda(): string
    {
        return match ($this) {
            self::MultiSucursal => 'Muestra el selector de sucursal y permite operar con más de una sucursal.',
            self::StockNegativo => 'Permite vender aunque no haya stock suficiente. Cada venta que deja stock negativo queda auditada.',
            self::FacturacionElectronica => 'Emite comprobantes fiscales electrónicos. Todavía no está disponible: hoy solo se emiten comprobantes internos.',
            self::UsarTalles => 'Pide talle en las variantes de producto, en la venta y en la ficha del cliente. Deshabilitarlo oculta el talle sin borrar datos.',
        };
    }

    /**
     * Indica si el parámetro se puede habilitar en esta versión del sistema.
     */
    public function sePuedeHabilitar(): bool
    {
        return $this !== self::FacturacionElectronica;
    }
}
