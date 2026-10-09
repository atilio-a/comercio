<?php

namespace App\Rules;

use App\Services\GaleriaProducto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Acepta solo JPEG, PNG o WebP según el contenido real del archivo (fileinfo),
 * sin confiar en la extensión ni en el tipo que informa el navegador.
 */
class ImagenPermitida implements ValidationRule
{
    public const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! in_array(GaleriaProducto::tipoReal($value), self::TIPOS, true)) {
            $fail('Solo se admiten imágenes JPEG, PNG o WebP.');
        }
    }
}
