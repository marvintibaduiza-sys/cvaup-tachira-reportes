<?php

namespace Database\Seeders;

use App\Services\UbicacionesImporter;
use Illuminate\Database\Seeder;

/**
 * UbicacionesSeeder — carga la jerarquía territorial REAL de Táchira.
 *
 * Origen: docs/CVAUP_TACHIRA.xlsx (commiteado al repo) — fuente oficial de
 * 29 municipios → ~80 parroquias → ~150 comunas → ~2200 consejos comunales.
 *
 * Lógica:
 *  1. Si el archivo existe → lo importa via UbicacionesImporter (idempotente)
 *  2. Si NO existe → cae a un dataset MÍNIMO de 3 municipios para que el sistema
 *     no quede sin data alguna en entornos donde el Excel no esté disponible
 *     (ej: pipelines de CI sin acceso a docs/).
 *
 * El importador usa firstOrCreate por nivel — re-ejecutar este seeder NO duplica
 * registros, solo agrega los que falten.
 */
class UbicacionesSeeder extends Seeder
{
    /**
     * Ruta absoluta esperada al archivo Excel de ubicaciones de Táchira.
     */
    private const EXCEL_PATH = 'docs/CVAUP_TACHIRA.xlsx';

    public function run(): void
    {
        $excelFullPath = base_path(self::EXCEL_PATH);

        if (file_exists($excelFullPath)) {
            $this->importarDesdeExcel($excelFullPath);
            return;
        }

        // Fallback: si el Excel no está disponible, creamos un dataset mínimo
        // para que el sistema arranque (modo dev sin Excel, CI/CD, etc.).
        $this->command->warn(self::EXCEL_PATH . ' no encontrado. Cargando dataset mínimo de fallback.');
        $this->command->warn('Para datos territoriales completos, asegúrate de que el archivo esté presente.');
        $this->cargarDatasetFallback();
    }

    /**
     * Carga la jerarquía completa desde docs/CVAUP_TACHIRA.xlsx.
     */
    private function importarDesdeExcel(string $path): void
    {
        $this->command->info("Importando ubicaciones desde {$path}...");

        $importer = app(UbicacionesImporter::class);
        $stats = $importer->import($path, dryRun: false, onProgress: null);

        $this->command->info(\sprintf(
            '  ✓ %d municipios, %d parroquias, %d comunas, %d consejos comunales creados.',
            $stats['creados']['municipios'],
            $stats['creados']['parroquias'],
            $stats['creados']['comunas'],
            $stats['creados']['consejos'],
        ));

        if ($stats['creados']['municipios'] === 0 && $stats['omitidos']['municipios'] > 0) {
            $this->command->info('  (Datos ya existían — seeder idempotente.)');
        }
    }

    /**
     * Dataset mínimo de fallback. NO refleja la división política real —
     * son solo 3 municipios de muestra para desarrollo sin el Excel.
     */
    private function cargarDatasetFallback(): void
    {
        $tachira = \App\Models\Estado::firstOrCreate(['nombre' => 'Táchira']);

        $data = [
            'San Cristóbal' => [
                'La Concordia' => [
                    'Comuna Pueblo Nuevo' => ['CC Barrio El Progreso', 'CC Urbanización Los Pinos'],
                    'Comuna Libertador' => ['CC Sector La Ermita'],
                ],
                'San Juan Bautista' => [
                    'Comuna San Juan' => ['CC Barrio San Juan'],
                ],
                'Pedro María Morantes' => [],
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
            $municipio = \App\Models\Municipio::firstOrCreate([
                'estado_id' => $tachira->id,
                'nombre' => $municipioNombre,
            ]);

            foreach ($parroquias as $parroquiaNombre => $comunas) {
                $parroquia = \App\Models\Parroquia::firstOrCreate([
                    'municipio_id' => $municipio->id,
                    'nombre' => $parroquiaNombre,
                ]);

                foreach ($comunas as $comunaNombre => $consejos) {
                    $comuna = \App\Models\Comuna::firstOrCreate([
                        'parroquia_id' => $parroquia->id,
                        'nombre' => $comunaNombre,
                    ]);

                    foreach ($consejos as $consejoNombre) {
                        \App\Models\ConsejoComunal::firstOrCreate([
                            'comuna_id' => $comuna->id,
                            'nombre' => $consejoNombre,
                        ]);
                    }
                }
            }
        }
    }
}
