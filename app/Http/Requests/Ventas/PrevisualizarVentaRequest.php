<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Previsualización del carrito (JSON): los pagos son opcionales y pueden no sumar el total.
 */
class PrevisualizarVentaRequest extends DatosVentaRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasCarrito(importeObligatorio: false);
    }
}
