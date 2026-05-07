<?php

namespace App\Console\Commands;

use App\Services\UbicacionesImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Comando: php artisan ubicaciones:import {file} [--dry]
 *
 * Importa la jerarquía Estado→Municipio→Parroquia→Comuna→Consejo Comunal
 * desde un archivo Excel. Usa el mismo Service que la UI de Fase 9.
 */
class ImportarUbicaciones extends Command
{
    protected $signature = 'ubicaciones:import
                            {file : Ruta absoluta al archivo .xlsx}
                            {--dry : Modo dry-run (no persiste cambios)}';

    protected $description = 'Importa ubicaciones (estados→municipios→parroquias→comunas→CC) desde un Excel.';

    public function handle(UbicacionesImporter $importer): int
    {
        $file = $this->argument('file');
        $dry = (bool) $this->option('dry');

        $this->newLine();
        $this->info('Archivo: ' . $file);
        if ($dry) {
            $this->warn('🔍 MODO DRY-RUN: simulación, no se persistirán cambios.');
        } else {
            $this->info('💾 MODO REAL: los datos se guardarán en BD.');
        }
        $this->newLine();

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% filas procesadas | %elapsed:6s%');
        $bar->start();

        try {
            $stats = $importer->import($file, $dry, function ($rowNum) use ($bar) {
                $bar->advance();
            });
            $bar->finish();
            $this->newLine(2);

            // Tabla de resultados
            $this->table(
                ['Nivel', 'Creados', 'Duplicados (omitidos)'],
                [
                    ['Estados',           $stats['creados']['estados'],    $stats['omitidos']['estados']],
                    ['Municipios',        $stats['creados']['municipios'], $stats['omitidos']['municipios']],
                    ['Parroquias',        $stats['creados']['parroquias'], $stats['omitidos']['parroquias']],
                    ['Comunas',           $stats['creados']['comunas'],    $stats['omitidos']['comunas']],
                    ['Consejos Comunales', $stats['creados']['consejos'],   $stats['omitidos']['consejos']],
                ]
            );

            $this->info("Filas procesadas correctamente: {$stats['filas_procesadas']}");
            $this->info("Filas vacías saltadas: {$stats['filas_saltadas_vacias']}");

            if (! empty($stats['errores'])) {
                $this->newLine();
                $this->warn('⚠️  Filas con errores: ' . count($stats['errores']));
                foreach (array_slice($stats['errores'], 0, 10) as $err) {
                    $this->line('   • ' . $err);
                }
                if (count($stats['errores']) > 10) {
                    $this->line('   … y ' . (count($stats['errores']) - 10) . ' errores más');
                }
            }

            if ($dry) {
                $this->newLine();
                $this->warn('Dry-run: ningún cambio fue persistido. Re-corre sin --dry para importar realmente.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('❌ ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        }
    }
}
