<?php

namespace Database\Seeders;

use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Database\Seeder;

class UbicacionesSeeder extends Seeder
{
    /**
     * Carga jerarquía Estado → Municipio → Parroquia → Comuna → Consejo Comunal
     * para el estado Táchira (Venezuela).
     *
     * Estructura tomada del chat de specs (MOCK_UBICACIONES).
     */
    public function run(): void
    {
        $tachira = Estado::firstOrCreate(['nombre' => 'Táchira']);

        $data = [
            'San Cristóbal' => [
                'La Concordia' => [
                    'Comuna Pueblo Nuevo' => ['CC Barrio El Progreso', 'CC Urbanización Los Pinos'],
                    'Comuna Libertador' => ['CC Sector La Ermita'],
                ],
                'San Juan Bautista' => [
                    'Comuna San Juan' => ['CC Barrio San Juan'],
                ],
                'Pedro María Morantes' => [
                    // Sin comunas iniciales — válido (parroquia existe pero aún no hay subdivisiones cargadas).
                ],
            ],
            'Capacho Nuevo' => [
                'Dr. Juan Germán Roscio' => [
                    'Labradores de la Montaña' => ['CC Tres Esquinas', 'CC El Mirador'],
                ],
            ],
            'Junín' => [
                'Rubio' => [
                    'Comuna Rubio Centro' => ['CC Pueblo Viejo', 'CC La Colina'],
                    'Comuna El Valle' => ['CC El Valle Norte'],
                ],
                'Bramón' => [
                    'Comuna Bramón' => ['CC Centro Bramón'],
                ],
            ],
        ];

        foreach ($data as $municipioNombre => $parroquias) {
            $municipio = Municipio::firstOrCreate([
                'estado_id' => $tachira->id,
                'nombre' => $municipioNombre,
            ]);

            foreach ($parroquias as $parroquiaNombre => $comunas) {
                $parroquia = Parroquia::firstOrCreate([
                    'municipio_id' => $municipio->id,
                    'nombre' => $parroquiaNombre,
                ]);

                foreach ($comunas as $comunaNombre => $consejos) {
                    $comuna = Comuna::firstOrCreate([
                        'parroquia_id' => $parroquia->id,
                        'nombre' => $comunaNombre,
                    ]);

                    foreach ($consejos as $consejoNombre) {
                        ConsejoComunal::firstOrCreate([
                            'comuna_id' => $comuna->id,
                            'nombre' => $consejoNombre,
                        ]);
                    }
                }
            }
        }
    }
}
