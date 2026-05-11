<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOQUE 9 — Drop de tablas pivote `reporte_comuna_atendida` y
 * `reporte_consejo_comunal_atendido` (originadas en el BLOQUE 5).
 *
 * Decisión del cliente (11/05/2026): los técnicos NO quieren seleccionar
 * comunas/CCs adicionales atendidos via multi-select. Solo quieren escribir
 * un NÚMERO TOTAL en `cantidad_comunas_atendidas` y `cantidad_consejos_comunales_atendidos`.
 *
 * Las columnas `cantidad_*` ya existen en la tabla `reportes` desde la migración
 * original (2026_01_01_000020). Esta migración SOLO elimina los pivotes que
 * el BLOQUE 5 creó pero ya no se usan.
 *
 * NOTA REVERSIBILIDAD:
 *  down() recrea las tablas pivote con la misma estructura, pero NO restaura datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reporte_consejo_comunal_atendido');
        Schema::dropIfExists('reporte_comuna_atendida');
    }

    public function down(): void
    {
        Schema::create('reporte_comuna_atendida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')
                ->constrained('reportes')
                ->cascadeOnDelete();
            $table->foreignId('comuna_id')
                ->constrained('comunas')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['reporte_id', 'comuna_id']);
            $table->index('comuna_id');
        });

        Schema::create('reporte_consejo_comunal_atendido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')
                ->constrained('reportes')
                ->cascadeOnDelete();
            $table->foreignId('consejo_comunal_id')
                ->constrained('consejos_comunales')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['reporte_id', 'consejo_comunal_id'], 'reporte_cc_unique');
            $table->index('consejo_comunal_id');
        });
    }
};
