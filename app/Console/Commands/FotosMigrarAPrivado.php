<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Migra fotos del disk 'public' (legacy, expuesto en /storage/...) al disk
 * 'fotos_privadas' (servido vía FotoController autenticado).
 *
 * Contexto: la auditoría de seguridad identificó que las fotos en
 * `storage/app/public/fotos/...` eran accesibles sin auth por enumeración
 * (CRITICAL #2). La corrección movió la lógica de PhotoCompressor para escribir
 * al nuevo disk privado, pero las fotos YA EXISTENTES siguen en el disk viejo.
 *
 * Este comando es idempotente: solo mueve archivos que NO existen ya en el destino.
 * No altera la BD (las rutas guardadas son relativas y compatibles con ambos disks).
 *
 * Uso:
 *   php artisan fotos:migrar-a-privado [--dry-run] [--force]
 */
class FotosMigrarAPrivado extends Command
{
    protected $signature = 'fotos:migrar-a-privado
                            {--dry-run : Solo simular, no mover nada}
                            {--force : Saltar la confirmación interactiva}';

    protected $description = 'Migra las fotos del disk público (legacy) al disk privado (post-CRITICAL #2 fix).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('🔒 Migrando fotos del disk PÚBLICO al disk PRIVADO');
        $this->line('   Origen:  storage/app/public/fotos/');
        $this->line('   Destino: storage/app/fotos-privadas/fotos/');
        if ($dryRun) {
            $this->warn('   MODO DRY-RUN — no se moverá ningún archivo.');
        }
        $this->newLine();

        // Encontrar TODAS las fotos del disk público bajo el subdirectorio "fotos/"
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('fotos_privadas');

        if (!$publicDisk->exists('fotos')) {
            $this->info('No hay carpeta `fotos/` en el disk público. Nada que migrar.');
            return self::SUCCESS;
        }

        $files = $publicDisk->allFiles('fotos');
        $total = \count($files);

        if ($total === 0) {
            $this->info('No hay archivos en `fotos/` para migrar.');
            return self::SUCCESS;
        }

        $this->info("Archivos detectados a migrar: {$total}");

        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('¿Continuar?', false)) {
                $this->warn('Cancelado.');
                return self::SUCCESS;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $movidos = 0;
        $omitidos = 0;
        $errores = 0;

        foreach ($files as $relativePath) {
            // Si ya existe en destino, no sobrescribimos
            if ($privateDisk->exists($relativePath)) {
                $omitidos++;
                $bar->advance();
                continue;
            }

            try {
                if (!$dryRun) {
                    // Copiar binario crudo (preserva exact bytes — no recomprime)
                    $contents = $publicDisk->get($relativePath);
                    $privateDisk->put($relativePath, $contents);

                    // Eliminar del disk público después de copiar (atómico desde el punto de vista lógico)
                    $publicDisk->delete($relativePath);
                }
                $movidos++;
            } catch (\Throwable $e) {
                $errores++;
                $this->newLine();
                $this->error("  ✗ Error con {$relativePath}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Migrados:  {$movidos}");
        $this->line("  Omitidos: {$omitidos} (ya existían en destino)");
        if ($errores > 0) {
            $this->error("  ✗ Errores:  {$errores}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry-run terminado. Ejecuta SIN --dry-run para hacer la migración real.');
        } else {
            $this->newLine();
            $this->line('Limpieza completa. Las fotos ahora están en el disk privado.');
            $this->line('Si quedó algo huérfano en storage/app/public/fotos/, puedes eliminarlo manualmente.');
        }

        return self::SUCCESS;
    }
}
