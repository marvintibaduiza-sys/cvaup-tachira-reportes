<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la FK reportes.tecnico_id de cascadeOnDelete a restrictOnDelete.
 *
 * MOTIVO (post-auditoría 2026-05-06, MEDIUM #6):
 *  - Tecnico tiene SoftDeletes. soft-delete NO dispara la cascada (solo marca deleted_at).
 *  - PERO un forceDelete() — accidentalmente disparado por un comando con LIKE muy amplio
 *    o por algún script futuro — SÍ dispara cascadeOnDelete y borraría TODOS los reportes
 *    asociados sin posibilidad de recovery.
 *  - restrictOnDelete bloquea el borrado en DB si hay reportes hijos. Es la última línea
 *    de defensa además del check en TecnicoController::destroy.
 *
 * EFECTO PRÁCTICO:
 *  Un técnico con reportes asociados NO podrá ser force-deleted. Para borrarlo en serio,
 *  primero hay que borrar/reasignar sus reportes. Esto es DESEABLE en un sistema institucional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            // 1. Eliminar la FK actual (cascadeOnDelete)
            $table->dropForeign(['tecnico_id']);

            // 2. Re-crearla con restrictOnDelete
            $table->foreign('tecnico_id')
                ->references('id')
                ->on('tecnicos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropForeign(['tecnico_id']);

            $table->foreign('tecnico_id')
                ->references('id')
                ->on('tecnicos')
                ->cascadeOnDelete();
        });
    }
};
