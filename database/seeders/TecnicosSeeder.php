<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\Tecnico;
use Illuminate\Database\Seeder;

class TecnicosSeeder extends Seeder
{
    /**
     * 5 técnicos de ejemplo con datos venezolanos ficticios.
     * La zona asignada se modela como pivot N:M con municipios.
     */
    public function run(): void
    {
        $tecnicos = [
            [
                'nombre_apellido' => 'Carlos Mendoza',
                'cedula' => 'V-18.456.789',
                'telefono' => '0414-7123456',
                'especialidad' => 'Horticultura',
                'estado' => 'activo',
                'municipios' => ['San Cristóbal'],
            ],
            [
                'nombre_apellido' => 'María González',
                'cedula' => 'V-20.123.456',
                'telefono' => '0424-5678901',
                'especialidad' => 'Fruticultura',
                'estado' => 'activo',
                'municipios' => ['Capacho Nuevo'],
            ],
            [
                'nombre_apellido' => 'José Ramírez',
                'cedula' => 'V-15.789.012',
                'telefono' => '0416-3456789',
                'especialidad' => 'Agroecología',
                'estado' => 'activo',
                'municipios' => ['Junín'],
            ],
            [
                'nombre_apellido' => 'Ana Pérez',
                'cedula' => 'V-22.345.678',
                'telefono' => '0412-9876543',
                'especialidad' => 'Cultivos orgánicos',
                'estado' => 'activo',
                'municipios' => ['San Cristóbal', 'Capacho Nuevo'], // multi-zona
            ],
            [
                'nombre_apellido' => 'Luis Contreras',
                'cedula' => 'V-19.012.345',
                'telefono' => '0414-2345678',
                'especialidad' => 'Riego y drenaje',
                'estado' => 'inactivo',
                'municipios' => ['Junín'],
            ],
        ];

        foreach ($tecnicos as $data) {
            $municipiosNombres = $data['municipios'];
            unset($data['municipios']);

            $tecnico = Tecnico::updateOrCreate(
                ['cedula' => $data['cedula']],
                $data
            );

            $municipioIds = Municipio::whereIn('nombre', $municipiosNombres)->pluck('id');
            $tecnico->municipios()->sync($municipioIds);
        }
    }
}
