<?php

namespace App\Backup;

use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/**
 * KeepLastNStrategy — política de retención FIFO simple.
 *
 * Mantiene los últimos N backups (los más recientes) y borra el resto.
 * Cuando se agrega el #N+1, el más antiguo se elimina automáticamente.
 *
 * POR QUÉ esta y no la DefaultStrategy de Spatie:
 *  La default mantiene 7 días + 16 daily + 8 weekly + 4 monthly + 2 yearly =
 *  retention temporal complejo. Para el contexto institucional CVAUP Táchira
 *  la operadora prefiere lógica simple "últimos 10" — fácil de entender,
 *  predecible, y consistente con el flujo manual desde la UI.
 *
 * Cantidad configurable vía `config('backup.cleanup.keep_last_n.count')`.
 *
 * Uso (config/backup.php):
 *   'strategy' => \App\Backup\KeepLastNStrategy::class,
 *   'keep_last_n' => ['count' => 10],
 *
 * Aplicada por:
 *  - Comando `backup:clean` (cron diario en producción)
 *  - Cualquier llamada a strategy->deleteOldBackups() de Spatie
 *
 * El flujo MANUAL desde BackupController también respeta MAX_BACKUPS_RETAINED=10
 * (en su propio cleanupOldBackups), así que ambos paths convergen al mismo número.
 */
class KeepLastNStrategy extends CleanupStrategy
{
    public function deleteOldBackups(BackupCollection $backups): void
    {
        $count = (int) config('backup.cleanup.keep_last_n.count', 10);

        // BackupCollection llega ordenada de más nuevo a más antiguo.
        // Mantener los primeros N (los más nuevos), borrar el resto.
        if ($backups->count() <= $count) {
            return;
        }

        $toDelete = $backups->slice($count);

        foreach ($toDelete as $backup) {
            $backup->delete();
        }
    }
}
