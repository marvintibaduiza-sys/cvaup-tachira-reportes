<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\Tecnico;
use Illuminate\Database\Seeder;

/**
 * 5 técnicos de ejemplo con datos venezolanos ficticios.
 *
 * BLOQUE 4 / 4.5:
 *  - nombre y apellido como columnas separadas
 *  - tipo_documento (V/E/J/G/P) + cedula sin puntos ni guion
 *  - especialidades como array (multi-select)
 *
 * La zona asignada se modela como pivot N:M con municipios.
 */
class TecnicosSeeder extends Seeder
{
    public function run(): void
    {
        $tecnicos = [
            [
                'nombre' => 'Carlos',
                'apellido' => 'Mendoza',
                'tipo_documento' => 'V',
                'cedula' => '18456789',
                'telefono' => '0414-7123456',
                'especialidades' => ['Agronomía urbana', 'Hidroponía'],
                'estado' => 'activo',
                'municipios' => ['San Cristóbal'],
            ],
            [
                'nombre' => 'María',
                'apellido' => 'González',
                'tipo_documento' => 'V',
                'cedula' => '20123456',
                'telefono' => '0424-5678901',
                'especialidades' => ['Cultivos protegidos (invernaderos)'],
                'estado' => 'activo',
                'municipios' => ['Capacho Nuevo'],
            ],
            [
                'nombre' => 'José',
                'apellido' => 'Ramírez',
                'tipo_documento' => 'V',
                'cedula' => '15789012',
                'telefono' => '0416-3456789',
                'especialidades' => ['Educación ambiental comunitaria', 'Compostaje y aprovechamiento orgánico'],
                'estado' => 'activo',
                'municipios' => ['Junín'],
            ],
            [
                'nombre' => 'Ana',
                'apellido' => 'Pérez',
                'tipo_documento' => 'V',
                'cedula' => '22345678',
                'telefono' => '0412-9876543',
                'especialidades' => ['Semilleros y viveros', 'Sanidad vegetal', 'Acuaponía'],
                'estado' => 'activo',
                'municipios' => ['San Cristóbal', 'Capacho Nuevo'], // multi-zona
            ],
            [
                'nombre' => 'Luis',
                'apellido' => 'Contreras',
                'tipo_documento' => 'V',
                'cedula' => '19012345',
                'telefono' => '0414-2345678',
                'especialidades' => ['Riego y manejo del agua'],
                'estado' => 'inactivo',
                'municipios' => ['Junín'],
            ],
        ];

        foreach ($tecnicos as $data) {
            $municipiosNombres = $data['municipios'];
            unset($data['municipios']);

            // updateOrCreate por (tipo_documento, cedula) — el UNIQUE compuesto del BLOQUE 4
            $tecnico = Tecnico::updateOrCreate(
                [
                    'tipo_documento' => $data['tipo_documento'],
                    'cedula' => $data['cedula'],
                ],
                $data
            );

            $municipioIds = Municipio::whereIn('nombre', $municipiosNombres)->pluck('id');
            $tecnico->municipios()->sync($municipioIds);
        }
    }
}
