<?php

namespace App\Rules;

use App\Services\DocumentoCliente;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * DNI de 7 u 8 dígitos o CUIT válido, con o sin puntos, guiones o espacios.
 */
class DocumentoClienteValido implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! DocumentoCliente::esValido($value)) {
            $fail('El documento debe ser un DNI de 7 u 8 dígitos o un CUIT válido.');
        }
    }
}
