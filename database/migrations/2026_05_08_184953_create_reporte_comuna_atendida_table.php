<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOQUE 5 — Pivote: comunas atendidas adicionales en un reporte.
 *
 * Modelo:
 *  - El reporte mantiene `comuna_id` (FK directa) como la comuna PRINCIPAL.
 *  - Esta tabla guarda comunas ADICIONALES atendidas en la misma jornada
 *    (pueden ser de cualquier municipio, validación a nivel de UI/Request).
 *  - La cantidad total = 1 (principal) + count(esta tabla).
 *
 * FK ON DELETE CASCADE: si se borra el reporte físicamente, los pivotes desaparecen.
 * El reporte usa SoftDeletes así que en la práctica el delete normal NO toca esto.
 */
return new class extends Migration
{
    public function up(): void
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

            // Una comuna no puede aparecer 2 veces en el mismo reporte
            $table->unique(['reporte_id', 'comuna_id']);
            // Index para queries inversas: "qué reportes tocaron tal comuna"
            $table->index('comuna_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_comuna_atendida');
    }
};
