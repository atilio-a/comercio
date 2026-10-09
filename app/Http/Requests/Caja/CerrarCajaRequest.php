<?php

namespace App\Http\Requests\Caja;

use App\Enums\Permiso;
use App\Models\CajaSesion;
use App\Services\Formato;
use App\Services\Importes;
use App\Services\MovimientosCaja;
use App\Services\SesionesCaja;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Cierre de la caja propia con arqueo: lo contado de cada medio que impacta en caja y
 * la justificación si hay diferencias (CAJ-02).
 */
class CerrarCajaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::CajaOperar->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contado' => ['nullable', 'array'],
            'contado.*' => ['nullable', 'numeric', Importes::REGLA],
            'justificacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Cada medio que se cuenta necesita su importe, y una diferencia necesita justificación.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $sesion = $this->sesion();

                if (! $sesion || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $diferencias = [];

                foreach (app(SesionesCaja::class)->arqueoPrevisto($sesion) as ['medio' => $medio, 'esperado' => $esperado, 'cuenta' => $cuenta]) {
                    if (! $cuenta) {
                        continue;
                    }

                    $contado = (string) $this->input('contado.'.$medio->id);

                    if ($contado === '') {
                        $validator->errors()->add('contado.'.$medio->id, "Indicá lo contado en {$medio->nombre}.");

                        continue;
                    }

                    $diferencia = Importes::aCentavos($contado) - Importes::aCentavos($esperado);

                    if ($diferencia !== 0) {
                        $diferencias[] = $medio->nombre.': '.Formato::importe(Importes::aDecimal($diferencia));
                    }
                }

                if ($diferencias !== [] && trim((string) $this->input('justificacion')) === '') {
                    $validator->errors()->add('justificacion', 'Hay diferencias ('.implode('; ', $diferencias).'). Justificá la diferencia para cerrar.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contado.*.regex' => 'Lo contado debe ser un importe mayor o igual a 0, con hasta dos decimales.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contado.*' => 'contado',
            'justificacion' => 'justificación',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contados(): array
    {
        $contados = [];

        foreach ((array) $this->validated('contado', []) as $medioId => $valor) {
            if ($valor !== null && $valor !== '') {
                $contados[(int) $medioId] = (string) $valor;
            }
        }

        return $contados;
    }

    public function sesion(): ?CajaSesion
    {
        return app(MovimientosCaja::class)->sesionAbierta();
    }
}
