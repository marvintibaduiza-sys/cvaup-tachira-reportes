<?php

namespace App\Console\Commands;

use App\Models\Reporte;
use App\Models\Tecnico;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Elimina TODOS los datos demo creados por `demo:poblar`.
 *
 * Identifica los registros demo por el marcador "[DEMO]" en `nombre_apellido`
 * del técnico. Borra todos los reportes asociados a esos técnicos (sin importar
 * el prefijo del título — defensa frente a reportes huérfanos creados manualmente
 * sobre técnicos demo durante testing) y luego force-elimina los técnicos.
 *
 * Mismas guardas que demo:poblar (no producción + confirmación).
 *
 * IMPORTANTE — orden del borrado:
 *  La FK reportes.tecnico_id usa restrictOnDelete (post-auditoría MEDIUM #6).
 *  Eso significa que NO se puede force-delete un técnico que aún tenga reportes.
 *  Por eso primero borramos reportes (en cascada por tecnico_id), después técnicos.
 *  Todo dentro de una transacción para atomicidad.
 */
class DemoLimpiar extends Command
{
    protected $signature = 'demo:limpiar {--force : Saltar la confirmación interactiva}';

    protected $description = '[SOLO DESARROLLO] Elimina todos los técnicos y reportes marcados con [DEMO].';

    public function handle(): int
    {
        // ── GUARD 1: bloquear producción ─────────────────────────────────
        if (app()->isProduction()) {
            $this->error('❌ Este comando NO se puede ejecutar en producción.');
            return self::FAILURE;
        }

        // ── Identificación de qué se va a borrar ────────────────────────
        // Estrategia: el marcador autoritativo está en Tecnico.nombre_apellido.
        // Cualquier reporte asociado a un técnico demo se considera demo (incluso
        // si por error no tiene el prefijo en su título — escenario común durante
        // tests manuales).
        //
        // IMPORTANTE — SoftDeletes:
        //  Reporte usa SoftDeletes. Las queries Eloquent normales NO incluyen
        //  filas soft-deleted, pero MySQL las ve físicamente y la FK restrictOnDelete
        //  bloquea el borrado del técnico padre. Por eso usamos withTrashed() en TODOS
        //  los conteos y queries de borrado, y forceDelete() para borrado físico.
        $tecnicosDemoIds = Tecnico::withTrashed()
            ->where('nombre_apellido', 'LIKE', DemoPoblar::MARCADOR . '%')
            ->pluck('id');

        $reportesAsociadosCount = Reporte::withTrashed()
            ->whereIn('tecnico_id', $tecnicosDemoIds)
            ->count();
        $reportesHuerfanosCount = Reporte::withTrashed()
            ->where('titulo_actividad', 'LIKE', DemoPoblar::MARCADOR . '%')
            ->whereNotIn('tecnico_id', $tecnicosDemoIds)
            ->count();
        $tecnicosDemoCount = $tecnicosDemoIds->count();

        $this->info('🧹 Demo limpiar — eliminación de datos ficticios');
        $this->line("   Reportes asociados a técnicos [DEMO]: {$reportesAsociadosCount}");
        $this->line("   Reportes [DEMO] huérfanos (otro técnico): {$reportesHuerfanosCount}");
        $this->line("   Técnicos [DEMO] a force-delete: {$tecnicosDemoCount}");
        $this->newLine();

        if ($reportesAsociadosCount === 0 && $reportesHuerfanosCount === 0 && $tecnicosDemoCount === 0) {
            $this->line('No hay datos demo que borrar. Nada que hacer.');
            return self::SUCCESS;
        }

        // ── GUARD 2: confirmación interactiva (saltable con --force) ─────
        if (!$this->option('force')) {
            if (!$this->confirm('Esta acción NO se puede deshacer. ¿Continuar?', false)) {
                $this->warn('Cancelado. Nada se borró.');
                return self::SUCCESS;
            }
        }

        // ── Borrado atómico (todo o nada) ───────────────────────────────
        // Usamos forceDelete() (no delete()) porque Reporte tiene SoftDeletes,
        // y necesitamos borrado FÍSICO para liberar la FK restrictOnDelete.
        try {
            DB::transaction(function () use ($tecnicosDemoIds, &$reportesAsociadosBorrados, &$reportesHuerfanosBorrados, &$tecnicosBorrados) {
                // PASO 1a: force-delete reportes asociados a técnicos demo
                // (incluyendo soft-deleted, que aún ocupan espacio en la tabla).
                $reportesAsociadosBorrados = Reporte::withTrashed()
                    ->whereIn('tecnico_id', $tecnicosDemoIds)
                    ->forceDelete();

                // PASO 1b: reportes [DEMO] bajo OTRO técnico (huérfanos).
                $reportesHuerfanosBorrados = Reporte::withTrashed()
                    ->where('titulo_actividad', 'LIKE', DemoPoblar::MARCADOR . '%')
                    ->whereNotIn('tecnico_id', $tecnicosDemoIds)
                    ->forceDelete();

                // PASO 2: ya con reportes fuera, force-delete los técnicos.
                $tecnicosBorrados = Tecnico::withTrashed()
                    ->where('nombre_apellido', 'LIKE', DemoPoblar::MARCADOR . '%')
                    ->forceDelete();
            });
        } catch (\Throwable $e) {
            $this->error('❌ Error durante la limpieza: ' . $e->getMessage());
            $this->line('  Toda la transacción fue revertida — la BD queda en el estado anterior.');
            return self::FAILURE;
        }

        $this->info("✓ Reportes asociados eliminados: {$reportesAsociadosBorrados}");
        $this->info("✓ Reportes [DEMO] huérfanos eliminados: {$reportesHuerfanosBorrados}");
        $this->info("✓ Técnicos [DEMO] eliminados: {$tecnicosBorrados}");

        $this->newLine();
        $this->line('Limpieza completa. La data REAL (no demo) está intacta.');

        return self::SUCCESS;
    }
}
