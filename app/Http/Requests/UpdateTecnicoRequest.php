<?php

namespace App\Http\Requests;

use App\Models\Tecnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalización pre-validación (igual que StoreTecnicoRequest).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('cedula')) {
            $this->merge([
                'cedula' => preg_replace('/\D/', '', (string) $this->input('cedula')),
            ]);
        }
        if ($this->has('tipo_documento')) {
            $this->merge([
                'tipo_documento' => strtoupper(trim((string) $this->input('tipo_documento'))),
            ]);
        }
        // BLOQUE 4.5: limpieza de especialidades — strings, sin vacíos, únicos.
        if ($this->has('especialidades') && \is_array($this->input('especialidades'))) {
            $clean = collect($this->input('especialidades'))
                ->map(fn ($e) => \is_string($e) ? trim($e) : '')
                ->filter(fn ($e) => $e !== '')
                ->unique()
                ->values()
                ->all();
            $this->merge(['especialidades' => $clean]);
        }
    }

    public function rules(): array
    {
        /** @var \App\Models\Tecnico|null $tecnico */
        $tecnico = $this->route('tecnico');
        $tecnicoId = $tecnico?->id;

        return [
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],

            'tipo_documento' => ['required', 'string', Rule::in(Tecnico::TIPOS_DOCUMENTO)],

            'cedula' => [
                'required',
                'string',
                'regex:/^\d{6,10}$/',
                // Solo bloquea si OTRO técnico (excluyendo el actual) tiene la misma combinación
                Rule::unique('tecnicos')
                    ->ignore($tecnicoId)
                    ->where(fn ($q) =>
                        $q->where('tipo_documento', $this->input('tipo_documento'))
                          ->whereNull('deleted_at')
                    ),
            ],

            'telefono' => ['nullable', 'string', 'max:20'],

            // BLOQUE 4.5: especialidades como array (multi-select).
            'especialidades' => ['nullable', 'array', 'max:11'],
            'especialidades.*' => ['string', 'max:120'],

            'estado' => ['required', 'in:activo,inactivo'],

            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'eliminar_foto' => ['nullable', 'boolean'],

            'municipio_ids' => ['nullable', 'array'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'tipo_documento.required' => 'Selecciona el tipo de documento.',
            'tipo_documento.in' => 'Tipo de documento inválido (debe ser V, E, J, G o P).',
            'cedula.required' => 'La cédula es obligatoria.',
            'cedula.regex' => 'La cédula debe tener entre 6 y 10 dígitos (sin puntos ni guion).',
            'cedula.unique' => 'Ya existe otro técnico con ese tipo de documento y cédula.',
            'foto.max' => 'La foto no puede pesar más de 5 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'apellido' => 'apellido',
            'tipo_documento' => 'tipo de documento',
            'cedula' => 'cédula',
            'telefono' => 'teléfono',
            'especialidades' => 'especialidades',
            'foto' => 'foto de perfil',
            'municipio_ids' => 'zonas asignadas',
        ];
    }
}
