<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BLOQUE 4.5 — Especialidades múltiples por técnico.
 *
 * Cambio de modelo: un técnico puede tener UNA o VARIAS especialidades
 * (ej: "Agronomía urbana" + "Hidroponía"). Antes solo guardábamos una.
 *
 * Estrategia:
 *  1. Agrega columna `especialidades` (JSON nullable)
 *  2. Migra cada `especialidad` (string) → `especialidades` ([string]) — array de 1
 *  3. Elimina la columna vieja `especialidad`
 *
 * Por qué JSON y no tabla pivote:
 *  - Lista cerrada de 11 valores predefinidos + "Otra" libre
 *  - Nunca filtramos técnicos por especialidad con joins complejos
 *  - JSON column = 1 tabla, sin riesgo de N+1, sin lookups innecesarios
 *
 * Down: revierte concatenando con coma (mejor esfuerzo — si había varias
 * especialidades se preservan en el string como "A, B, C").
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna nueva (nullable para no romper en este paso)
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->json('especialidades')->nullable()->after('telefono');
        });

        // 2. Migrar datos: cada técnico con `especialidad` → array de 1 elemento.
        //    Usamos withTrashed implícito (DB facade no aplica SoftDeletes scope).
        $tecnicos = DB::table('tecnicos')->whereNotNull('especialidad')->get(['id', 'especialidad']);
        foreach ($tecnicos as $t) {
            DB::table('tecnicos')
                ->where('id', $t->id)
                ->update([
                    'especialidades' => json_encode([$t->especialidad], JSON_UNESCAPED_UNICODE),
                ]);
        }

        // 3. Eliminar columna vieja
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropColumn('especialidad');
        });
    }

    public function down(): void
    {
        // 1. Recrear columna vieja
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('especialidad', 120)->nullable()->after('telefono');
        });

        // 2. Migrar de regreso: array → string concatenado con coma.
        //    Si había varias, se preservan unidas. El form viejo era single-select pero
        //    al menos no perdemos la información (admin podrá leer lo que había).
        $tecnicos = DB::table('tecnicos')->whereNotNull('especialidades')->get(['id', 'especialidades']);
        foreach ($tecnicos as $t) {
            $arr = json_decode($t->especialidades, true);
            if (\is_array($arr) && \count($arr) > 0) {
                DB::table('tecnicos')
                    ->where('id', $t->id)
                    ->update(['especialidad' => implode(', ', $arr)]);
            }
        }

        // 3. Eliminar columna nueva
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropColumn('especialidades');
        });
    }
};
