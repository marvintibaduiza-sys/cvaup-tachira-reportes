<?php

namespace App\Http\Requests;

use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Parroquia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $esBorrador = (bool) $this->boolean('guardar_como_borrador');
        $reqIfFinal = $esBorrador ? ['nullable'] : ['required'];

        return [
            // Meta
            'guardar_como_borrador' => ['nullable', 'boolean'],

            // Datos generales
            'tecnico_id' => ['required', 'integer', Rule::exists('tecnicos', 'id')->whereNull('deleted_at')],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'municipio_id' => [...$reqIfFinal, 'integer', 'exists:municipios,id'],
            'parroquia_id' => [...$reqIfFinal, 'integer', 'exists:parroquias,id'],
            'comuna_id' => [...$reqIfFinal, 'integer', 'exists:comunas,id'],
            'consejo_comunal_id' => [...$reqIfFinal, 'integer', 'exists:consejos_comunales,id'],

            // BLOQUE 5: comunas y CCs adicionales (multi-select)
            'comunas_adicionales_ids' => ['nullable', 'array', 'max:50'],
            'comunas_adicionales_ids.*' => ['integer', 'distinct', 'exists:comunas,id'],
            'consejos_comunales_adicionales_ids' => ['nullable', 'array', 'max:50'],
            'consejos_comunales_adicionales_ids.*' => ['integer', 'distinct', 'exists:consejos_comunales,id'],

            'lugar' => ['nullable', 'string', 'max:255'],

            // Personas atendidas
            'cantidad_personas_atendidas' => ['nullable', 'integer', 'min:0'],
            'cantidad_personas_a_beneficiar' => ['nullable', 'integer', 'min:0'],

            // BLOQUE 8: descripción simplificada de la actividad
            'tipo_actividad' => [...$reqIfFinal, 'string', Rule::in(\App\Models\Reporte::TIPOS_ACTIVIDAD)],
            'descripcion_actividad' => [...$reqIfFinal, 'string', 'max:5000'],

            // Fotos (constancia fotográfica)
            'fotos' => ['nullable', 'array', 'max:3'],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del reporte no puede ser futura.',
            'fotos.max' => 'Máximo 3 fotos por reporte.',
            'fotos.*.max' => 'Cada foto debe pesar menos de 5 MB (antes de compresión).',
        ];
    }

    public function attributes(): array
    {
        return [
            'tecnico_id' => 'técnico',
            'municipio_id' => 'municipio',
            'parroquia_id' => 'parroquia',
            'comuna_id' => 'comuna',
            'consejo_comunal_id' => 'consejo comunal',
            'tipo_actividad' => 'tipo de actividad',
            'descripcion_actividad' => 'descripción de la actividad',
            'cantidad_personas_atendidas' => 'cantidad de personas atendidas',
            'cantidad_personas_a_beneficiar' => 'cantidad de personas a beneficiar',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // 1) Día laborable (lunes a viernes)
            $fecha = $this->date('fecha');
            if ($fecha?->isWeekend()) {
                $v->errors()->add('fecha', 'La fecha del reporte debe ser un día laborable (lunes a viernes).');
            }

            // 2) Coherencia jerárquica de la ubicación
            $this->validarCoherenciaJerarquica($v);

            // 3) Unique (tecnico_id, fecha) — un técnico solo puede tener 1 reporte por fecha
            $tecnicoId = $this->input('tecnico_id');
            $fechaStr = $this->input('fecha');
            if ($tecnicoId && $fechaStr) {
                $existe = \App\Models\Reporte::where('tecnico_id', $tecnicoId)
                    ->whereDate('fecha', $fechaStr)
                    ->exists();
                if ($existe) {
                    $v->errors()->add('fecha', 'Este técnico ya tiene un reporte registrado para esa fecha.');
                }
            }

            // 4) BLOQUE 5: la comuna/CC PRINCIPAL no debe duplicarse en los pivotes.
            //    Si el técnico ya marcó la comuna X como principal, no tiene sentido
            //    que aparezca también en "otras comunas atendidas".
            $comunaPrincipal = $this->input('comuna_id');
            $comunasAdicionales = (array) $this->input('comunas_adicionales_ids', []);
            if ($comunaPrincipal && \in_array((int) $comunaPrincipal, array_map('intval', $comunasAdicionales), true)) {
                $v->errors()->add('comunas_adicionales_ids', 'La comuna principal no debe aparecer también en "otras comunas atendidas".');
            }

            $ccPrincipal = $this->input('consejo_comunal_id');
            $ccsAdicionales = (array) $this->input('consejos_comunales_adicionales_ids', []);
            if ($ccPrincipal && \in_array((int) $ccPrincipal, array_map('intval', $ccsAdicionales), true)) {
                $v->errors()->add('consejos_comunales_adicionales_ids', 'El consejo comunal principal no debe aparecer también en "otros consejos comunales atendidos".');
            }
        });
    }

    private function validarCoherenciaJerarquica(Validator $v): void
    {
        $municipioId = $this->input('municipio_id');
        $parroquiaId = $this->input('parroquia_id');
        $comunaId = $this->input('comuna_id');
        $ccId = $this->input('consejo_comunal_id');

        // Una sola query SQL por nivel — más eficiente y sin acceso a propiedades del modelo
        if ($parroquiaId && $municipioId) {
            $ok = Parroquia::where('id', $parroquiaId)->where('municipio_id', $municipioId)->exists();
            if (!$ok) {
                $v->errors()->add('parroquia_id', 'La parroquia no pertenece al municipio seleccionado.');
            }
        }

        if ($comunaId && $parroquiaId) {
            $ok = Comuna::where('id', $comunaId)->where('parroquia_id', $parroquiaId)->exists();
            if (!$ok) {
                $v->errors()->add('comuna_id', 'La comuna no pertenece a la parroquia seleccionada.');
            }
        }

        if ($ccId && $comunaId) {
            $ok = ConsejoComunal::where('id', $ccId)->where('comuna_id', $comunaId)->exists();
            if (!$ok) {
                $v->errors()->add('consejo_comunal_id', 'El consejo comunal no pertenece a la comuna seleccionada.');
            }
        }
    }
}
