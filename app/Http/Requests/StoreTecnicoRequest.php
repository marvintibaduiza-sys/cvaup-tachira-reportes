<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // mono-usuario: cualquier autenticado
    }

    public function rules(): array
    {
        return [
            'nombre_apellido' => ['required', 'string', 'max:255'],
            'cedula' => [
                'required',
                'string',
                'max:20',
                // Solo bloquea si existe un técnico ACTIVO (no soft-deleted) con esa cédula
                Rule::unique('tecnicos', 'cedula')->whereNull('deleted_at'),
                // Formato venezolano: V-XX.XXX.XXX (V/E/J/G + 1-2 dígitos + 3 dígitos + 3 dígitos)
                'regex:/^[VEJG]-\d{1,2}\.\d{3}\.\d{3}$/i',
            ],
            'telefono' => ['nullable', 'string', 'max:20'],
            'especialidad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:activo,inactivo'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
            'municipio_ids' => ['nullable', 'array'],
            'municipio_ids.*' => ['integer', 'exists:municipios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.regex' => 'La cédula debe tener formato V-XX.XXX.XXX (ej: V-18.456.789).',
            'cedula.unique' => 'Ya existe un técnico con esta cédula.',
            'foto.max' => 'La foto no puede pesar más de 5 MB (antes de compresión).',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WebP.',
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
