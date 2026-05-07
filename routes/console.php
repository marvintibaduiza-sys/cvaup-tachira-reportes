<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * ──────────────────────────────────────────────────────────────────
 *  CRONOGRAMA AUTOMÁTICO DE RESPALDOS (Fase 13.3)
 * ──────────────────────────────────────────────────────────────────
 *
 *  Estas tareas requieren que el system cron (Linux) o Windows Task Scheduler
 *  ejecute `php artisan schedule:run` cada minuto. Ver:
 *  docs/BACKUP-AUTOMATICO-WINDOWS.md para configurarlo en Windows/Laragon.
 *
 *  Hora elegida: 2-3 AM (franja de menor uso del sistema).
 */

// 02:00 AM diario — limpia backups antiguos según política (mantiene últimos 10)
Schedule::command('backup:clean')
    ->dailyAt('02:00')
    ->name('backup-clean-daily')
    ->onOneServer() // safety si en el futuro hay múltiples servidores
    ->withoutOverlapping(); // si se atrasa, no encolar otra ejecución

// 02:30 AM diario — genera el backup completo (BD + fotos + .env)
Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->name('backup-run-daily')
    ->onOneServer()
    ->withoutOverlapping();

// Cada lunes 03:00 AM — verifica salud (alerta si pasaron días sin backups)
Schedule::command('backup:monitor')
    ->mondays()
    ->at('03:00')
    ->name('backup-monitor-weekly')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * ──────────────────────────────────────────────────────────────────
 *  Cleanup de imports abandonados (post-auditoría LOW #2)
 * ──────────────────────────────────────────────────────────────────
 *  El flujo importarPreview → importarConfirmar guarda el .xlsx en
 *  storage/app/import-temp/{token}.xlsx y solo se limpia si la confirmación
 *  o el catch lo borran. Si el admin abandona el flujo, el archivo queda.
 *
 *  Este job barre cada hora y borra archivos con mtime > 1h.
 */
Schedule::call(function () {
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    if (!$disk->exists('import-temp')) {
        return;
    }
    $unaHoraAtras = now()->subHour()->getTimestamp();
    foreach ($disk->files('import-temp') as $file) {
        if ($disk->lastModified($file) < $unaHoraAtras) {
            $disk->delete($file);
        }
    }
})->hourly()->name('cleanup-import-temp-hourly')->onOneServer();
