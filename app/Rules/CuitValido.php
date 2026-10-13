<?php

namespace App\Rules;

use App\Services\Cuit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * CUIT argentino con prefijo admitido y dígito verificador correcto.
 */
class CuitValido implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cuit::esValido($value)) {
            $fail('El CUIT no es válido.');
        }
    }
}
