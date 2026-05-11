<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOQUE 8 — Simplificación del Reporte CVAUP.
 *
 * Cambios según especificación del cliente (imagen WhatsApp del 11/05/2026):
 *  1) titulo_actividad (string) → tipo_actividad (string corto, dropdown 5 opciones)
 *  2) resumen_tematico (text) → descripcion_actividad (text)
 *  3) Eliminar 10 campos institucionales no requeridos por el formato oficial:
 *     nombre_cientifico_rubro, fecha_ejecucion, ponencia_responsable, material_apoyo,
 *     organizado_por, aval_de, certificacion, participantes_acreditados,
 *     alcance_grupo, resultado.
 *
 * NOTA REVERSIBILIDAD:
 *  La función down() recrea las columnas eliminadas pero NO restaura los datos:
 *  esos datos se pierden al ejecutar up(). Es un cambio destructivo intencional
 *  porque el cliente confirmó que esa información no se usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Renombrar titulo_actividad → tipo_actividad
        Schema::table('reportes', function (Blueprint $table) {
            $table->renameColumn('titulo_actividad', 'tipo_actividad');
        });

        // 2. Renombrar resumen_tematico → descripcion_actividad
        Schema::table('reportes', function (Blueprint $table) {
            $table->renameColumn('resumen_tematico', 'descripcion_actividad');
        });

        // 3. Eliminar 10 columnas no requeridas
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_cientifico_rubro',
                'fecha_ejecucion',
                'ponencia_responsable',
                'material_apoyo',
                'organizado_por',
                'aval_de',
                'certificacion',
                'participantes_acreditados',
                'alcance_grupo',
                'resultado',
            ]);
        });
    }

    public function down(): void
    {
        // 1. Restaurar columnas eliminadas (sin datos)
        Schema::table('reportes', function (Blueprint $table) {
            $table->string('nombre_cientifico_rubro', 255)->nullable()->after('descripcion_actividad');
            $table->date('fecha_ejecucion')->nullable()->after('nombre_cientifico_rubro');
            $table->string('ponencia_responsable', 255)->nullable()->after('fecha_ejecucion');
            $table->text('material_apoyo')->nullable()->after('ponencia_responsable');
            $table->string('organizado_por', 255)->nullable()->after('material_apoyo');
            $table->string('aval_de', 255)->nullable()->after('organizado_por');
            $table->text('certificacion')->nullable()->after('aval_de');
            $table->unsignedInteger('participantes_acreditados')->nullable()->after('certificacion');
            $table->unsignedInteger('alcance_grupo')->nullable()->after('participantes_acreditados');
            $table->text('resultado')->nullable()->after('alcance_grupo');
        });

        // 2. Renombrar de vuelta descripcion_actividad → resumen_tematico
        Schema::table('reportes', function (Blueprint $table) {
            $table->renameColumn('descripcion_actividad', 'resumen_tematico');
        });

        // 3. Renombrar de vuelta tipo_actividad → titulo_actividad
        Schema::table('reportes', function (Blueprint $table) {
            $table->renameColumn('tipo_actividad', 'titulo_actividad');
        });
    }
};
