<?php

namespace App\Http\Requests;

use App\Models\Tecnico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Antes de validar, normalizamos la cédula:
     *  - Quitamos puntos, guiones, espacios
     *  - Solo dígitos
     * Esto permite al usuario escribir "12.345.678" o "12345678" indistintamente
     * y el sistema guarda siempre el formato canónico.
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
        // BLOQUE 4.5: limpiamos especialidades — strings, sin vacíos, sin duplicados,
        // sin espacios sobrantes. Si llega null o no-array, lo dejamos pasar (validación
        // se encarga). Esto previene bugs de "Agronomía urbana, " vs "Agronomía urbana".
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
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],

            'tipo_documento' => ['required', 'string', Rule::in(Tecnico::TIPOS_DOCUMENTO)],

            'cedula' => [
                'required',
                'string',
                // Solo dígitos, mínimo 6 (cédulas viejas), máximo 10 (jurídicas)
                'regex:/^\d{6,10}$/',
                // UNIQUE compuesto: misma combinación tipo+cedula no puede repetirse en activos
                Rule::unique('tecnicos')->where(fn ($q) =>
                    $q->where('tipo_documento', $this->input('tipo_documento'))
                      ->whereNull('deleted_at')
                ),
            ],

            'telefono' => ['nullable', 'string', 'max:20'],

            // BLOQUE 4.5: especialidades como array (multi-select).
            // Se permite mezcla de opciones predefinidas (ESPECIALIDADES) y texto libre,
            // pero CADA elemento debe ser un string corto y razonable. Limitamos a 11
            // para evitar que un usuario enumere 50 cosas.
            'especialidades' => ['nullable', 'array', 'max:11'],
            'especialidades.*' => ['string', 'max:120'],

            'estado' => ['required', 'in:activo,inactivo'],

            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

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
            'cedula.unique' => 'Ya existe un técnico con ese tipo de documento y cédula.',
            'foto.max' => 'La foto no puede pesar más de 5 MB.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WebP.',
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
