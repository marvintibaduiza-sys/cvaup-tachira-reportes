<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite recrear un técnico con cédula que pertenece a un registro soft-deleted.
 *
 * Cambia el unique de `cedula` (simple) a `(cedula, deleted_at)` (compuesto).
 * En MySQL, NULL ≠ NULL en índices únicos, así que:
 *   - Solo puede haber 1 registro activo (deleted_at = NULL) por cédula
 *   - Puede haber N registros borrados (deleted_at = timestamp) con la misma cédula
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->unique(['cedula', 'deleted_at'], 'tecnicos_cedula_deleted_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropUnique('tecnicos_cedula_deleted_at_unique');
            $table->unique('cedula');
        });
    }
};
