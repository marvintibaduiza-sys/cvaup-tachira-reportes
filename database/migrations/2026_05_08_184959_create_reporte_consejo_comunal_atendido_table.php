<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOQUE 5 — Pivote: consejos comunales atendidos adicionales en un reporte.
 *
 * Modelo:
 *  - El reporte mantiene `consejo_comunal_id` (FK directa) como el CC PRINCIPAL.
 *  - Esta tabla guarda CCs ADICIONALES atendidos en la misma jornada
 *    (pueden ser de cualquier parroquia/comuna, validación a nivel de UI/Request).
 *  - La cantidad total = 1 (principal) + count(esta tabla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_consejo_comunal_atendido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')
                ->constrained('reportes')
                ->cascadeOnDelete();
            $table->foreignId('consejo_comunal_id')
                ->constrained('consejos_comunales')
                ->cascadeOnDelete();
            $table->timestamps();

            // Un CC no puede aparecer 2 veces en el mismo reporte
            $table->unique(['reporte_id', 'consejo_comunal_id'], 'reporte_cc_unique');
            // Index para queries inversas: "qué reportes tocaron tal CC"
            $table->index('consejo_comunal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_consejo_comunal_atendido');
    }
};
