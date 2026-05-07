<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * BackupController — gestiona los respaldos del sistema usando spatie/laravel-backup.
 *
 * Estrategia:
 *  - El listado se lee directamente del filesystem (no de una tabla DB) — la fuente
 *    de verdad son los archivos en storage/app/private/cvaup-tachira/.
 *  - Generar: ejecuta `backup:run` de Spatie + cleanup automático (mantiene últimos 10).
 *  - Descargar / Eliminar: validan que el filename no escape del directorio (path traversal safe).
 */
class BackupController extends Controller
{
    /**
     * Mantener este número de backups más recientes; el resto se elimina automáticamente
     * después de cada nueva generación.
     */
    private const MAX_BACKUPS_RETAINED = 10;

    /**
     * Listado de backups disponibles + estadísticas + estado del cronograma automático.
     */
    public function index(): InertiaResponse
    {
        $backups = $this->listarBackups();
        $totalSizeMb = collect($backups)->sum('size_mb');

        return Inertia::render('Backup/Index', [
            'backups' => $backups,
            'estadisticas' => [
                'total' => \count($backups),
                'total_size_mb' => round($totalSizeMb, 2),
                'max_retenidos' => self::MAX_BACKUPS_RETAINED,
                'directorio' => 'storage/app/private/cvaup-tachira/',
            ],
            'cronograma' => $this->cronogramaInfo($backups),
        ]);
    }

    /**
     * Página de confirmación / disparador de generación de backup.
     */
    public function generarForm(): InertiaResponse
    {
        return Inertia::render('Backup/Generar', [
            'estadisticas' => [
                'total_actual' => \count($this->listarBackups()),
                'max_retenidos' => self::MAX_BACKUPS_RETAINED,
            ],
        ]);
    }

    /**
     * Ejecuta `backup:run` de Spatie y aplica cleanup de backups antiguos.
     */
    public function generar(): RedirectResponse
    {
        // GUARD (post-auditoría HIGH #3): NO permitir backup sin password de cifrado.
        // El backup contiene dump completo de BD + fotos privadas — debe ir cifrado AES-256.
        if (empty(config('backup.backup.password'))) {
            return back()->with(
                'error',
                'No se puede generar el backup: falta configurar BACKUP_ARCHIVE_PASSWORD en .env. '
                    . 'Contacta a TI para que lo configure (debe ser un secreto fuerte generado con openssl).'
            );
        }

        try {
            // Spatie genera el .zip en el disco 'local' bajo el slug configurado en config/backup.php
            Artisan::call('backup:run');

            // Cleanup: mantener solo los últimos N backups
            $eliminados = $this->cleanupOldBackups();

            $msg = 'Respaldo generado exitosamente.';
            if ($eliminados > 0) {
                $msg .= " Se eliminaron {$eliminados} backups antiguos para mantener los últimos " . self::MAX_BACKUPS_RETAINED . '.';
            }

            return redirect()->route('backup.index')->with('success', $msg);
        } catch (\Throwable $e) {
            // Log detallado server-side, mensaje GENÉRICO al cliente.
            // Los stack traces y paths absolutos NO deben llegar al navegador del admin
            // (un atacante con captura de pantalla podría inferir versiones de librerías).
            Log::error('[Backup] Falló la generación', [
                // Log internamente con detalles, NO se muestra al cliente.
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with(
                'error',
                'No se pudo generar el respaldo. Revisa los logs del sistema o contacta a soporte.'
            );
        }
    }

    /**
     * Descarga un backup específico.
     */
    public function descargar(string $filename): BinaryFileResponse
    {
        $path = $this->resolveBackupPathOrFail($filename);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Elimina un backup específico del disco.
     */
    public function eliminar(string $filename): RedirectResponse
    {
        $path = $this->resolveBackupPathOrFail($filename);

        if (@unlink($path)) {
            return back()->with('success', "Backup '{$filename}' eliminado.");
        }

        return back()->with('error', "No se pudo eliminar el backup '{$filename}'.");
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────

    /**
     * Directorio físico donde Spatie guarda los .zip.
     */
    private function backupDir(): string
    {
        $name = config('backup.backup.name', 'cvaup-tachira');
        return storage_path("app/private/{$name}");
    }

    /**
     * Información del cronograma automático para mostrar en la UI.
     *
     * Calcula próximas/últimas ejecuciones del backup diario (2:30 AM) e
     * intenta detectar si el último backup tiene "huella" de ejecución automática
     * (timestamp coincide con la franja 2:00-3:00 AM).
     */
    private function cronogramaInfo(array $backups): array
    {
        $now = Carbon::now();

        // Próxima ejecución teórica: hoy 2:30 AM si aún no pasó, sino mañana
        $proxima = Carbon::today()->setTime(2, 30);
        if ($proxima->isPast()) {
            $proxima->addDay();
        }

        // Detectar si el último backup parece automático (creado entre 2:00 y 3:00 AM)
        $ultimoBackup = $backups[0] ?? null;
        $ultimoFueAutomatico = false;
        $ultimoTimestampLegible = null;

        if ($ultimoBackup) {
            $ts = Carbon::parse($ultimoBackup['created_at_iso']);
            $ultimoTimestampLegible = $ts->format('d/m/Y H:i');
            $ultimoFueAutomatico = $ts->hour >= 2 && $ts->hour < 3;
        }

        // El backup automático SOLO está realmente activo en producción
        // (donde el cron del servidor dispara `php artisan schedule:run` cada minuto).
        // En desarrollo NO tiene sentido tener Task Scheduler configurado.
        $esProduccion = app()->isProduction();

        return [
            'entorno' => $esProduccion ? 'produccion' : 'desarrollo',
            'automatico_activo' => $esProduccion,
            'proxima_ejecucion' => $proxima->format('d/m/Y H:i'),
            'proxima_ejecucion_relativa' => $proxima->diffForHumans($now, [
                'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                'parts' => 2,
            ]),
            'ultimo_backup' => $ultimoTimestampLegible,
            'ultimo_fue_automatico' => $ultimoFueAutomatico,
            'horario_diario' => '02:30 AM',
            'horario_limpieza' => '02:00 AM',
            'horario_monitor' => 'Lunes 03:00 AM',
            'docs_despliegue' => 'docs/DEPLOYMENT-BACKUP-PRODUCCION.md',
        ];
    }

    /**
     * Lista los backups disponibles ordenados por fecha de creación (más recientes primero).
     */
    private function listarBackups(): array
    {
        $dir = $this->backupDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: [];

        $backups = array_map(function (string $path) {
            $sizeBytes = (int) filesize($path);
            $mtime = (int) filemtime($path);

            return [
                'filename' => basename($path),
                'size_bytes' => $sizeBytes,
                'size_kb' => round($sizeBytes / 1024, 2),
                'size_mb' => round($sizeBytes / (1024 * 1024), 2),
                'created_at' => Carbon::createFromTimestamp($mtime)->format('d/m/Y H:i:s'),
                'created_at_iso' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
                'mtime' => $mtime,
            ];
        }, $files);

        // Orden DESC por fecha
        usort($backups, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        // Quitar 'mtime' del payload final (era solo para sort)
        return array_map(fn ($b) => collect($b)->except('mtime')->all(), $backups);
    }

    /**
     * Mantiene solo los últimos N backups. Devuelve cuántos eliminó.
     */
    private function cleanupOldBackups(): int
    {
        $backups = $this->listarBackups(); // ya viene ordenado DESC por fecha
        if (\count($backups) <= self::MAX_BACKUPS_RETAINED) {
            return 0;
        }

        $toDelete = \array_slice($backups, self::MAX_BACKUPS_RETAINED);
        $eliminados = 0;

        foreach ($toDelete as $b) {
            $path = $this->backupDir() . DIRECTORY_SEPARATOR . $b['filename'];
            if (file_exists($path) && @unlink($path)) {
                $eliminados++;
            }
        }

        return $eliminados;
    }

    /**
     * Resuelve un filename a su path absoluto, validando que:
     *  - El nombre tenga formato seguro (alfanumérico + guiones + .zip)
     *  - El path resuelto esté DENTRO del directorio de backups (anti directory-traversal)
     *  - El archivo exista
     */
    private function resolveBackupPathOrFail(string $filename): string
    {
        // 1. Validar formato del nombre — solo permite caracteres seguros
        if (!preg_match('/^[A-Za-z0-9_\-\.]+\.zip$/', $filename)) {
            abort(400, 'Nombre de archivo inválido.');
        }

        $backupDir = realpath($this->backupDir());
        if ($backupDir === false) {
            abort(404, 'Directorio de backups no encontrado.');
        }

        $fullPath = realpath($backupDir . DIRECTORY_SEPARATOR . $filename);

        // 2. Anti directory-traversal: asegurar que el path resuelto esté dentro del directorio de backups
        if ($fullPath === false || !str_starts_with($fullPath, $backupDir . DIRECTORY_SEPARATOR)) {
            abort(404, 'Backup no encontrado.');
        }

        // 3. El archivo debe ser un .zip real
        if (!is_file($fullPath)) {
            abort(404, 'Backup no encontrado.');
        }

        return $fullPath;
    }
}
