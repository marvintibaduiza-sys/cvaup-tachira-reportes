<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var \App\Models\Tecnico|null $tecnico */
        $tecnico = $this->route('tecnico');
        $tecnicoId = $tecnico?->id;

        return [
            'nombre_apellido' => ['required', 'string', 'max:255'],
            'cedula' => [
                'required',
                'string',
                'max:20',
                // Solo bloquea si OTRO técnico activo (no soft-deleted) tiene esa cédula
                Rule::unique('tecnicos', 'cedula')
                    ->ignore($tecnicoId)
                    ->whereNull('deleted_at'),
                'regex:/^[VEJG]-\d{1,2}\.\d{3}\.\d{3}$/i',
            ],
            'telefono' => ['nullable', 'string', 'max:20'],
            'especialidad' => ['nullable', 'string', 'max:255'],
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
            'cedula.regex' => 'La cédula debe tener formato V-XX.XXX.XXX (ej: V-18.456.789).',
            'cedula.unique' => 'Ya existe otro técnico con esta cédula.',
            'foto.max' => 'La foto no puede pesar más de 5 MB (antes de compresión).',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_apellido' => 'nombre y apellido',
            'cedula' => 'cédula',
            'telefono' => 'teléfono',
            'foto' => 'foto de perfil',
            'municipio_ids' => 'zonas asignadas',
        ];
    }
}
