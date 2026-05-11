<?php

namespace App\Http\Requests;

use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Parroquia;
use App\Models\Reporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReporteRequest extends FormRequest
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
            'guardar_como_borrador' => ['nullable', 'boolean'],

            'tecnico_id' => ['required', 'integer', Rule::exists('tecnicos', 'id')->whereNull('deleted_at')],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'municipio_id' => [...$reqIfFinal, 'integer', 'exists:municipios,id'],
            'parroquia_id' => [...$reqIfFinal, 'integer', 'exists:parroquias,id'],
            'comuna_id' => [...$reqIfFinal, 'integer', 'exists:comunas,id'],
            'consejo_comunal_id' => [...$reqIfFinal, 'integer', 'exists:consejos_comunales,id'],

            // BLOQUE 9: cantidades manuales (no calculadas desde pivote).
            // El técnico escribe el TOTAL (incluida la comuna/CC principal).
            // Mínimo 1 porque siempre hay al menos la principal del reporte.
            'cantidad_comunas_atendidas' => [...$reqIfFinal, 'integer', 'min:1', 'max:9999'],
            'cantidad_consejos_comunales_atendidos' => [...$reqIfFinal, 'integer', 'min:1', 'max:9999'],

            'lugar' => ['nullable', 'string', 'max:255'],

            'cantidad_personas_atendidas' => ['nullable', 'integer', 'min:0'],
            'cantidad_personas_a_beneficiar' => ['nullable', 'integer', 'min:0'],

            'tipo_actividad' => [...$reqIfFinal, 'string', Rule::in(\App\Models\Reporte::TIPOS_ACTIVIDAD)],
            'descripcion_actividad' => [...$reqIfFinal, 'string', 'max:5000'],

            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'fotos_eliminar' => ['nullable', 'array'],
            'fotos_eliminar.*' => ['integer', 'exists:fotos_reporte,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del reporte no puede ser futura.',
            'fotos.*.max' => 'Cada foto debe pesar menos de 5 MB.',
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Día laborable
            $fecha = $this->date('fecha');
            if ($fecha?->isWeekend()) {
                $v->errors()->add('fecha', 'La fecha del reporte debe ser un día laborable (lunes a viernes).');
            }

            // Coherencia jerárquica — usa exists() en una sola query SQL en vez de cargar el modelo
            $municipioId = $this->input('municipio_id');
            $parroquiaId = $this->input('parroquia_id');
            $comunaId = $this->input('comuna_id');
            $ccId = $this->input('consejo_comunal_id');

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

            // Unique (tecnico_id, fecha) excluyendo este mismo reporte
            $tecnicoId = $this->input('tecnico_id');
            $fechaStr = $this->input('fecha');
            $reporteActual = $this->route('reporte');
            $reporteActualId = $reporteActual instanceof Reporte ? $reporteActual->getKey() : null;

            if ($tecnicoId && $fechaStr) {
                $query = Reporte::where('tecnico_id', $tecnicoId)->whereDate('fecha', $fechaStr);
                if ($reporteActualId !== null) {
                    $query->where('id', '!=', $reporteActualId);
                }
                if ($query->exists()) {
                    $v->errors()->add('fecha', 'Este técnico ya tiene otro reporte registrado para esa fecha.');
                }
            }

        });
    }
}
